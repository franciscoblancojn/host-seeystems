<?php

/**
 * Busca en Google (vía la API de Serper.dev) la descripción de un producto que no la tiene.
 *
 * 1. Consulta «marca nombre referencia» y toma los primeros resultados orgánicos.
 * 2. Abre cada página (máx. MAX_PAGES) y solo acepta la que contiene la referencia exacta,
 *    para no traer la descripción de otro modelo.
 * 3. De esa página toma la descripción del JSON-LD Product; si no, og:description o
 *    meta description. Si ninguna página sirve, usa el snippet de Google que contenga la referencia.
 *
 * Devuelve ['text' => descripción en texto plano, 'source' => URL] o WP_Error.
 * La Custom Search JSON API de Google no admite clientes nuevos y se apaga el 2027-01-01:
 * por eso se usa Serper.dev (resultados de Google por API).
 */
class HSY_DESCRIBER
{
    const ENDPOINT = 'https://google.serper.dev/search';
    const MAX_PAGES = 4;
    const MIN_LENGTH = 60;
    const TIMEOUT = 12;

    /** Dominios que no aportan una descripción propia del producto. */
    const SKIP_HOSTS = ['youtube.com', 'facebook.com', 'instagram.com', 'pinterest.com', 'tiktok.com', 'x.com', 'twitter.com'];

    public static function enabled($config = null)
    {
        $config = $config ?: (new HSY_USE_DATA_CONFIG())->get();
        return !empty($config['describe']) && '' !== $config['serper_key'];
    }

    public static function find($brand, $name, $ref, $config = null)
    {
        $config = $config ?: (new HSY_USE_DATA_CONFIG())->get();
        if (!self::enabled($config)) {
            return new WP_Error('hsy_describe_off', 'La búsqueda de descripciones está desactivada o falta la API key de Serper.');
        }
        if ('' === trim((string) $ref)) {
            return new WP_Error('hsy_describe_ref', 'Sin referencia no se puede buscar la descripción con seguridad.');
        }

        $results = self::search(trim($brand . ' ' . $name . ' ' . $ref), $config['serper_key']);
        if (is_wp_error($results)) {
            return $results;
        }

        $pages = 0;
        $snippet = null;
        foreach ($results as $result) {
            $url = (string) ($result['link'] ?? '');
            if (!self::usable_url($url)) {
                continue;
            }
            if (!$snippet && self::contains_ref(($result['title'] ?? '') . ' ' . ($result['snippet'] ?? ''), $ref)
                && mb_strlen((string) ($result['snippet'] ?? '')) >= self::MIN_LENGTH) {
                $snippet = ['text' => self::clean($result['snippet']), 'source' => $url];
            }
            if ($pages >= self::MAX_PAGES) {
                continue;
            }
            $pages++;
            $text = self::from_page($url, $ref);
            if ($text) {
                return ['text' => $text, 'source' => $url];
            }
        }
        if ($snippet) {
            return $snippet;
        }
        return new WP_Error('hsy_describe_none', 'No se encontró una descripción con la referencia ' . $ref . '.');
    }

    // ------------------------------------------------------------------ internos

    private static function search($query, $key)
    {
        $response = wp_remote_post(self::ENDPOINT, [
            'timeout' => self::TIMEOUT,
            'headers' => ['X-API-KEY' => $key, 'Content-Type' => 'application/json'],
            'body' => wp_json_encode(['q' => $query, 'gl' => 'co', 'hl' => 'es', 'num' => 10]),
        ]);
        if (is_wp_error($response)) {
            return new WP_Error('hsy_describe_http', 'Serper: ' . $response->get_error_message());
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (200 !== $code || !is_array($body)) {
            $message = is_array($body) && !empty($body['message']) ? $body['message'] : 'HTTP ' . $code;
            return new WP_Error('hsy_describe_http', 'Serper: ' . $message);
        }
        return (array) ($body['organic'] ?? []);
    }

    private static function usable_url($url)
    {
        if (!preg_match('#^https?://#i', $url) || HSY_CLIENT::is_same_host($url)) {
            return false;
        }
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        foreach (self::SKIP_HOSTS as $skip) {
            if ($host === $skip || substr($host, -strlen('.' . $skip)) === '.' . $skip) {
                return false;
            }
        }
        return !preg_match('/\.pdf($|\?)/i', $url);
    }

    /**
     * Descripción de una página que mencione la referencia, o ''.
     */
    private static function from_page($url, $ref)
    {
        $response = wp_safe_remote_get($url, [
            'timeout' => self::TIMEOUT,
            'redirection' => 3,
            'limit_response_size' => 2 * MB_IN_BYTES,
            'user-agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . ')',
        ]);
        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            return '';
        }
        $html = (string) wp_remote_retrieve_body($response);
        if (!self::contains_ref(wp_strip_all_tags($html) . ' ' . $html, $ref)) {
            return '';
        }

        $candidates = [];
        // JSON-LD Product.description (la más fiable).
        if (preg_match_all('#<script[^>]+application/ld\+json[^>]*>(.*?)</script>#is', $html, $blocks)) {
            foreach ($blocks[1] as $json) {
                $data = json_decode(trim($json), true);
                foreach (self::ld_products($data) as $product) {
                    if (!empty($product['description']) && is_string($product['description'])) {
                        $candidates[] = $product['description'];
                    }
                }
            }
        }
        foreach (['og:description', 'description', 'twitter:description'] as $meta) {
            if (preg_match('#<meta[^>]+(?:property|name)=["\']' . preg_quote($meta, '#') . '["\'][^>]*content=["\']([^"\']+)["\']#i', $html, $m)
                || preg_match('#<meta[^>]+content=["\']([^"\']+)["\'][^>]*(?:property|name)=["\']' . preg_quote($meta, '#') . '["\']#i', $html, $m)) {
                $candidates[] = $m[1];
            }
        }
        foreach ($candidates as $text) {
            $text = self::clean($text);
            if (mb_strlen($text) >= self::MIN_LENGTH) {
                return $text;
            }
        }
        return '';
    }

    private static function ld_products($data)
    {
        $found = [];
        if (!is_array($data)) {
            return $found;
        }
        $type = $data['@type'] ?? '';
        if ('Product' === $type || (is_array($type) && in_array('Product', $type, true))) {
            $found[] = $data;
        }
        foreach ($data as $value) {
            if (is_array($value)) {
                $found = array_merge($found, self::ld_products($value));
            }
        }
        return $found;
    }

    /**
     * ¿El texto contiene la referencia? Ignora mayúsculas, espacios y guiones (AX7186SET = AX-7186 SET).
     */
    private static function contains_ref($text, $ref)
    {
        $norm = function ($s) {
            return preg_replace('/[\s\-_.\/]+/u', '', mb_strtoupper(html_entity_decode((string) $s, ENT_QUOTES, 'UTF-8'), 'UTF-8'));
        };
        $ref = $norm($ref);
        return '' !== $ref && false !== strpos($norm($text), $ref);
    }

    private static function clean($text)
    {
        $text = html_entity_decode(wp_strip_all_tags((string) $text), ENT_QUOTES, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
