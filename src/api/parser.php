<?php

/**
 * Convierte la respuesta del catálogo (JSON o HTML) en una lista de productos normalizada.
 * El HTML del catálogo se lee con heurísticas genéricas; ajustar los selectores
 * de SELECTORS cuando se conozca el marcado real de la plataforma.
 */
class HSEY_PARSER
{
    const SELECTORS = [
        'card' => "//*[contains(concat(' ', normalize-space(@class), ' '), ' product-card ') or contains(concat(' ', normalize-space(@class), ' '), ' product ') or @data-product-id or @data-sku]",
        'name' => ".//*[contains(@class, 'name') or contains(@class, 'title')] | .//h2 | .//h3 | .//h4",
        'sku' => ".//*[contains(@class, 'sku') or contains(@class, 'ref') or contains(@class, 'code')]",
        'price' => ".//*[contains(@class, 'price') or contains(@class, 'precio')]",
        'stock' => ".//*[contains(@class, 'stock') or contains(@class, 'existencia')]",
        'image' => ".//img",
        'link' => ".//a[@href]",
    ];

    public static function parse($res, $baseUrl)
    {
        $body = trim($res['body'] ?? '');
        if ($body === '') {
            return [];
        }
        if (strpos($res['content_type'] ?? '', 'json') !== false || $body[0] === '[' || $body[0] === '{') {
            return self::parseJson($body);
        }
        return self::parseHtml($body, $baseUrl);
    }

    public static function parseJson($body)
    {
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return [];
        }
        foreach (['data', 'products', 'productos', 'items', 'results'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return array_values($data[$key]);
            }
        }
        return array_values($data);
    }

    public static function parseHtml($html, $baseUrl)
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $products = [];
        $cards = $xpath->query(self::SELECTORS['card']);
        foreach ($cards as $card) {
            // Evitar tarjetas anidadas dentro de otra tarjeta ya detectada
            if (self::hasCardAncestor($card, $cards)) {
                continue;
            }
            $product = [
                'id' => $card->getAttribute('data-product-id') ?: $card->getAttribute('data-id'),
                'sku' => $card->getAttribute('data-sku') ?: self::text($xpath, self::SELECTORS['sku'], $card),
                'name' => $card->getAttribute('data-name') ?: self::text($xpath, self::SELECTORS['name'], $card),
                'price' => $card->getAttribute('data-price') ?: self::text($xpath, self::SELECTORS['price'], $card),
                'stock' => $card->getAttribute('data-stock') ?: self::text($xpath, self::SELECTORS['stock'], $card),
                'image' => self::absUrl(self::attr($xpath, self::SELECTORS['image'], $card, ['data-src', 'src']), $baseUrl),
                'url' => self::absUrl(self::attr($xpath, self::SELECTORS['link'], $card, ['href']), $baseUrl),
            ];
            if ($product['name'] === '' && $product['sku'] === '') {
                continue;
            }
            $products[] = $product;
        }
        return $products;
    }

    /**
     * URL de la siguiente página del catálogo, si existe.
     */
    public static function nextPage($html, $baseUrl)
    {
        if (!preg_match('/<a[^>]+(?:rel="next"|class="[^"]*next[^"]*")[^>]*href="([^"]+)"/i', $html, $m)
            && !preg_match('/<a[^>]+href="([^"]+)"[^>]*(?:rel="next"|class="[^"]*next[^"]*")/i', $html, $m)) {
            return '';
        }
        return self::absUrl(html_entity_decode($m[1]), $baseUrl);
    }

    private static function hasCardAncestor($node, $cards)
    {
        for ($parent = $node->parentNode; $parent; $parent = $parent->parentNode) {
            foreach ($cards as $card) {
                if ($card->isSameNode($parent)) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function text($xpath, $query, $context)
    {
        $node = $xpath->query($query, $context)->item(0);
        return $node ? trim(preg_replace('/\s+/', ' ', $node->textContent)) : '';
    }

    private static function attr($xpath, $query, $context, $attrs)
    {
        $node = $xpath->query($query, $context)->item(0);
        if (!$node) {
            return '';
        }
        foreach ($attrs as $attr) {
            if ($node->getAttribute($attr) !== '') {
                return $node->getAttribute($attr);
            }
        }
        return '';
    }

    private static function absUrl($url, $baseUrl)
    {
        if ($url === '' || preg_match('#^(https?:)?//#', $url)) {
            return $url;
        }
        if ($url[0] === '?') {
            return strtok($baseUrl, '?') . $url;
        }
        $parts = wp_parse_url($baseUrl);
        $origin = $parts['scheme'] . '://' . $parts['host'];
        if ($url[0] === '/') {
            return $origin . $url;
        }
        return trailingslashit($baseUrl) . $url;
    }
}
