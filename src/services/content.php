<?php

/**
 * Descripción y descripción corta de los productos importados que las tienen pendientes.
 *
 * Única fuente de la regla «pendiente» y de quién escribe esos textos:
 * - Pendiente = producto importado sin descripción, o cuya descripción corta sigue siendo la
 *   lista automática de características (meta HSY_SHORT_AUTO_META = 'yes'). Lo escrito a mano
 *   o generado antes nunca se reemplaza.
 * - Con IA (plugin IA Conector, clase IACON_AI) se generan las dos, una llamada por campo, con los
 *   prompts editables ai_prompt_description y ai_prompt_short (variables en VARIABLES). Si la
 *   búsqueda en Google (HSY_DESCRIBER) está activa, lo encontrado llega en {info_web}.
 * - Sin IA y con Google activo, la descripción es el texto encontrado en Google (como antes).
 *
 * Se ejecuta por AJAX de a un producto (HSY_API_IMPORT::content): las llamadas a la IA tardan.
 */
class HSY_CONTENT
{
    /** HTML permitido en lo que devuelve la IA. */
    const ALLOWED_TAGS = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'em' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'h3' => [],
        'h4' => [],
    ];

    /** ¿Está el plugin IA Conector activo? */
    public static function ai_available()
    {
        return class_exists('IACON_AI');
    }

    /**
     * Conexiones activas del IA Conector: [key => etiqueta].
     */
    public static function ai_connections()
    {
        if (!self::ai_available()) {
            return [];
        }
        $list = [];
        foreach ((array) IACON_AI::getListIas() as $conn) {
            $list[$conn['key']] = $conn['label'] . ' (' . $conn['type'] . ')';
        }
        return $list;
    }

    /** ¿Hay algún generador configurado (IA o Google)? */
    public static function enabled($config = null)
    {
        $config = $config ?: (new HSY_USE_DATA_CONFIG())->get();
        return self::ai_enabled($config) || HSY_DESCRIBER::enabled($config);
    }

    public static function ai_enabled($config)
    {
        return !empty($config['ai_enabled']) && '' !== $config['ai_connection'] && self::ai_available();
    }

    public static function is_pending($product)
    {
        if (!HSY_STOCK::is_imported($product)) {
            return false;
        }
        return '' === trim(wp_strip_all_tags($product->get_description())) || self::short_pending($product);
    }

    /**
     * ¿La descripción corta falta o es la lista automática? Los productos importados antes de que
     * existiera HSY_SHORT_AUTO_META se reconocen por la forma de la lista (<ul><li><strong>X:</strong> …).
     */
    public static function short_pending($product)
    {
        $short = trim((string) $product->get_short_description());
        if ('' === trim(wp_strip_all_tags($short))) {
            return true;
        }
        $auto = $product->get_meta(HSY_SHORT_AUTO_META);
        if ('' === $auto) {
            return self::is_auto_list($short);
        }
        return 'yes' === $auto;
    }

    public static function is_auto_list($html)
    {
        return (bool) preg_match('#^<ul>(<li><strong>[^<]+:</strong>[^<]*</li>)+</ul>$#u', trim((string) $html));
    }

    /**
     * IDs de los productos importados con contenido pendiente (una consulta).
     */
    public static function pending_ids($limit = 2000)
    {
        global $wpdb;
        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} imp ON imp.post_id = p.ID AND imp.meta_key = %s AND imp.meta_value = 'yes'
             LEFT JOIN {$wpdb->postmeta} sa ON sa.post_id = p.ID AND sa.meta_key = %s
             WHERE p.post_type = 'product' AND p.post_status <> 'trash'
               AND (TRIM(p.post_content) = '' OR TRIM(p.post_excerpt) = '' OR sa.meta_value = 'yes'
                    OR (sa.meta_value IS NULL AND p.post_excerpt LIKE '<ul><li><strong>%%'))
             ORDER BY p.ID ASC LIMIT %d",
            HSY_IMPORTED_META,
            HSY_SHORT_AUTO_META,
            $limit
        )));
    }

    /**
     * Variables de los prompts: {clave} => qué contiene.
     */
    const VARIABLES = [
        'nombre' => 'Nombre del producto en el catálogo (LEILA SET)',
        'nombre_tienda' => 'Nombre del producto en la tienda (según la plantilla del nombre)',
        'marca' => 'Marca (Armani Exchange)',
        'referencia' => 'Referencia / SKU (AX7186SET)',
        'genero' => 'Género (DAMA)',
        'correa' => 'Correa (Acero Dorado)',
        'caja' => 'Caja (Acero, Dorado)',
        'tamano' => 'Tamaño (27 mm)',
        'resistencia_agua' => 'Resistencia al agua',
        'caracteristicas' => 'Todas las características, una por línea («- Género: DAMA»)',
        'info_web' => 'Texto encontrado en Google con la referencia (vacío si no hay o si la búsqueda está apagada)',
        'fuente_web' => 'URL de donde salió {info_web}',
        'descripcion' => 'Descripción del producto (la recién generada o la que ya tenía; útil en el prompt de la corta)',
    ];

    const DEFAULT_PROMPT_DESCRIPTION = "Eres redactor de fichas de producto para una tienda online en español.
Escribe la descripción del producto {marca} {nombre} (referencia {referencia}).

Datos del producto (fuente fiable):
- Nombre: {nombre}
- Marca: {marca}
{caracteristicas}

Información encontrada en la web (puede ser de otra tienda; si está vacía, ignórala; úsala solo como referencia y reescríbela, no la copies):
{info_web}

Reglas:
- No inventes características técnicas que no estén en los datos o en la información de la web (movimiento, materiales, resistencia, garantía…).
- No menciones precios, descuentos, stock, envíos ni el nombre de ninguna tienda.
- Español neutro, tono comercial y claro, sin exageraciones ni emojis.
- Formato: 2 o 3 párrafos en HTML con <p>; puedes usar <strong> y una lista <ul><li> con las características.

Responde solo con el HTML de la descripción, sin explicaciones ni bloques de código.";

    const DEFAULT_PROMPT_SHORT = "Eres redactor de fichas de producto para una tienda online en español.
Escribe la descripción corta del producto {marca} {nombre} (referencia {referencia}).

Datos del producto:
{caracteristicas}

Descripción completa del producto (resúmela si existe):
{descripcion}

Reglas:
- 1 o 2 frases, máximo 300 caracteres, en un solo <p>.
- No inventes datos ni menciones precios, stock ni tiendas.
- Sin emojis.

Responde solo con el HTML, sin explicaciones ni bloques de código.";

    /**
     * Completa lo pendiente de un producto. Devuelve ['fields' => [...], 'source' => ..., 'warnings' => [...]] o WP_Error.
     */
    public static function fill($product_id, $config = null)
    {
        $config = $config ?: (new HSY_USE_DATA_CONFIG())->get();
        $product = wc_get_product($product_id);
        if (!$product || !HSY_STOCK::is_imported($product)) {
            return new WP_Error('hsy_content_product', 'El producto #' . (int) $product_id . ' no es un producto importado.');
        }
        if (!self::is_pending($product)) {
            return ['fields' => [], 'source' => '', 'warnings' => []];
        }
        if (!self::enabled($config)) {
            return new WP_Error('hsy_content_off', 'No hay generador de descripciones: activa la IA o la búsqueda en Google en Importación.');
        }

        $need_description = '' === trim(wp_strip_all_tags($product->get_description()));
        $need_short = self::short_pending($product);
        $web = self::web($product, $config);
        $warnings = $web['warnings'];
        $web = $web['web'];

        $fields = [];
        $source = '';
        if (self::ai_enabled($config)) {
            $generated = self::generate($product, $web, $config, $need_description, $need_short);
            if (is_wp_error($generated)) {
                HSY_LOG_SERVICE::add('content_error', ['product_id' => $product->get_id(), 'message' => $generated->get_error_message()]);
                return $generated;
            }
            if ($need_description && '' !== $generated['description']) {
                $product->set_description($generated['description']);
                $fields[] = 'description';
            }
            if ($need_short && '' !== $generated['short_description']) {
                $product->set_short_description($generated['short_description']);
                $product->update_meta_data(HSY_SHORT_AUTO_META, 'no');
                $fields[] = 'short_description';
            }
            $source = 'ia:' . $config['ai_connection'];
            $product->update_meta_data('_hsy_content_ai', $config['ai_connection']);
        } elseif ($web && $need_description) {
            $product->set_description(wpautop(esc_html($web['text'])));
            $fields[] = 'description';
            $source = 'google';
        }

        if ($web) {
            $product->update_meta_data('_hsy_description_source', $web['source']);
        }
        if ($fields) {
            $product->update_meta_data('_hsy_content_at', time());
            $product->save();
        } elseif (!$warnings) {
            $warnings[] = 'No se generó contenido.';
        }

        HSY_LOG_SERVICE::add('content', [
            'product_id' => $product->get_id(),
            'fields' => $fields,
            'source' => $source,
            'web' => $web ? $web['source'] : '',
            'warnings' => $warnings,
        ]);
        return ['fields' => $fields, 'source' => $source, 'warnings' => $warnings];
    }

    /**
     * Prueba de los prompts (los del formulario, sin guardar) con un producto: no escribe nada.
     * Devuelve prompts finales y textos generados, o WP_Error.
     */
    public static function preview($product_id, $prompts, $config = null)
    {
        $config = $config ?: (new HSY_USE_DATA_CONFIG())->get();
        $config = array_merge($config, array_filter($prompts, 'strlen'));
        $product = wc_get_product($product_id);
        if (!$product) {
            return new WP_Error('hsy_preview_product', 'No existe el producto #' . (int) $product_id . '.');
        }
        if (!self::ai_enabled($config)) {
            return new WP_Error('hsy_preview_ai', 'Activa la IA, elige una conexión y guarda antes de probar.');
        }
        $web = self::web($product, $config);
        $vars = self::variables($product, $web['web']);
        $prompt_description = self::render($config['ai_prompt_description'], $vars);
        $description = self::ask($prompt_description, $config);
        if (is_wp_error($description)) {
            return $description;
        }
        $vars['descripcion'] = self::text($description);
        $prompt_short = self::render($config['ai_prompt_short'], $vars);
        $short = self::ask($prompt_short, $config);
        if (is_wp_error($short)) {
            return $short;
        }
        return [
            'product' => $product->get_name(),
            'prompt_description' => $prompt_description,
            'description' => $description,
            'prompt_short' => $prompt_short,
            'short_description' => $short,
            'web' => $web['web'] ? $web['web']['source'] : '',
            'warnings' => $web['warnings'],
        ];
    }

    /**
     * Valores de las variables de los prompts para un producto.
     */
    public static function variables($product, $web = null, $description = null)
    {
        $brands = taxonomy_exists('product_brand') ? wp_get_post_terms($product->get_id(), 'product_brand', ['fields' => 'names']) : [];
        $attrs = [];
        $lines = [];
        foreach ($product->get_attributes() as $attribute) {
            if ($attribute instanceof WC_Product_Attribute && !$attribute->is_taxonomy()) {
                $value = implode(', ', $attribute->get_options());
                $attrs[sanitize_title($attribute->get_name())] = $value;
                $lines[] = '- ' . $attribute->get_name() . ': ' . $value;
            }
        }
        // Nombre del catálogo: meta (productos importados desde 1.1) o el catálogo guardado (anteriores).
        $source_name = (string) $product->get_meta('_hsy_source_name');
        if ('' === $source_name) {
            $item = (new HSY_USE_DATA_CATALOG())->item($product->get_meta(HSY_SOURCE_META));
            $source_name = $item ? (string) $item['name'] : '';
        }
        $current = null === $description ? $product->get_description() : $description;

        return [
            'nombre' => '' !== $source_name ? $source_name : $product->get_name(),
            'nombre_tienda' => $product->get_name(),
            'marca' => is_array($brands) && $brands ? implode(', ', $brands) : '',
            'referencia' => $product->get_sku(),
            'genero' => $attrs['genero'] ?? '',
            'correa' => $attrs['correa'] ?? '',
            'caja' => $attrs['caja'] ?? '',
            'tamano' => $attrs['tamano'] ?? '',
            'resistencia_agua' => $attrs['resistencia-al-agua'] ?? '',
            'caracteristicas' => implode("\n", $lines),
            'info_web' => $web ? $web['text'] : '',
            'fuente_web' => $web ? $web['source'] : '',
            'descripcion' => self::text($current),
        ];
    }

    /**
     * Sustituye {variable} por su valor. Las variables desconocidas se dejan tal cual.
     */
    public static function render($template, $vars)
    {
        $map = [];
        foreach ($vars as $key => $value) {
            $map['{' . $key . '}'] = (string) $value;
        }
        return trim(strtr((string) $template, $map));
    }

    // ------------------------------------------------------------------ internos

    /**
     * Búsqueda en Google (opcional). Devuelve ['web' => ['text','source']|null, 'warnings' => []].
     */
    private static function web($product, $config)
    {
        if (!HSY_DESCRIBER::enabled($config)) {
            return ['web' => null, 'warnings' => []];
        }
        $vars = self::variables($product);
        $web = HSY_DESCRIBER::find($vars['marca'], $vars['nombre'], $vars['referencia'], $config);
        return is_wp_error($web)
            ? ['web' => null, 'warnings' => [$web->get_error_message()]]
            : ['web' => $web, 'warnings' => []];
    }

    /**
     * Una llamada por campo: primero la descripción y después la corta (que puede usar {descripcion}).
     */
    private static function generate($product, $web, $config, $need_description, $need_short)
    {
        $vars = self::variables($product, $web);
        $out = ['description' => '', 'short_description' => ''];
        if ($need_description) {
            $out['description'] = self::ask(self::render($config['ai_prompt_description'], $vars), $config);
            if (is_wp_error($out['description'])) {
                return $out['description'];
            }
            $vars['descripcion'] = self::text($out['description']);
        }
        if ($need_short) {
            $out['short_description'] = self::ask(self::render($config['ai_prompt_short'], $vars), $config);
            if (is_wp_error($out['short_description'])) {
                return $out['short_description'];
            }
        }
        return $out;
    }

    /**
     * Envía un prompt a la conexión elegida y devuelve el HTML limpio, o WP_Error.
     */
    private static function ask($prompt, $config)
    {
        $ai = IACON_AI::get($config['ai_connection']);
        if (!$ai) {
            return new WP_Error('hsy_ai_connection', 'La conexión de IA «' . $config['ai_connection'] . '» no existe o está desactivada en IA Conector.');
        }
        $response = $ai->sendPrompt($prompt);
        if (!is_array($response) || 'ok' !== ($response['status'] ?? '')) {
            return new WP_Error('hsy_ai', 'IA: ' . (is_array($response) ? ($response['message'] ?? 'error') : 'respuesta vacía'));
        }
        $html = self::html((string) $response['data']);
        if ('' === $html) {
            return new WP_Error('hsy_ai_empty', 'La IA devolvió una respuesta vacía.');
        }
        return $html;
    }

    /**
     * HTML → texto para las variables: un párrafo / elemento de lista por línea.
     */
    private static function text($html)
    {
        $html = preg_replace('#<br\s*/?>|</(p|li|h[1-6]|ul|ol)>#i', "\n", (string) $html);
        $html = preg_replace('#<li[^>]*>#i', '- ', $html);
        $text = html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8');
        return trim(preg_replace("/\n\s*\n+/", "\n", preg_replace('/[ \t]+/', ' ', $text)));
    }

    /**
     * Limpia la respuesta: quita bloques ```html … ```, deja solo ALLOWED_TAGS y añade <p> si es texto plano.
     */
    private static function html($text)
    {
        $text = preg_replace('/^\s*```[a-z]*\s*|\s*```\s*$/i', '', trim($text));
        $html = trim(wp_kses($text, self::ALLOWED_TAGS));
        if ('' !== $html && false === strpos($html, '<p') && false === strpos($html, '<ul')) {
            $html = wpautop($html);
        }
        return $html;
    }
}
