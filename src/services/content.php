<?php

/**
 * Descripción y descripción corta de los productos importados que las tienen pendientes.
 *
 * Única fuente de la regla «pendiente» y de quién escribe esos textos:
 * - Pendiente = producto importado sin descripción, o cuya descripción corta sigue siendo la
 *   lista automática de características (meta HSY_SHORT_AUTO_META = 'yes'). Lo escrito a mano
 *   o generado antes nunca se reemplaza.
 * - Con IA (plugin IA Conector, clase IACON_AI) se generan las dos. Si la búsqueda en Google
 *   (HSY_DESCRIBER) está activa, lo encontrado se pasa a la IA como contexto.
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
        $data = self::product_data($product);
        $warnings = [];

        // Contexto de Google (opcional).
        $web = null;
        if (HSY_DESCRIBER::enabled($config)) {
            $web = HSY_DESCRIBER::find($data['marca'], $data['nombre'], $data['referencia'], $config);
            if (is_wp_error($web)) {
                $warnings[] = $web->get_error_message();
                $web = null;
            }
        }

        $fields = [];
        $source = '';
        if (self::ai_enabled($config)) {
            $generated = self::generate($data, $web, $config);
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
     * Prompt para la IA (se puede ver en la pestaña Importación).
     */
    public static function prompt($data, $web, $instructions)
    {
        $lines = [];
        foreach ($data as $key => $value) {
            if ('' !== (string) $value) {
                $lines[] = '- ' . $key . ': ' . $value;
            }
        }
        $prompt = "Eres redactor de fichas de producto para una tienda online en español (Colombia).\n"
            . "Escribe la descripción y la descripción corta de este producto.\n\n"
            . "DATOS DEL PRODUCTO (fuente fiable):\n" . implode("\n", $lines) . "\n";
        if ($web) {
            $prompt .= "\nINFORMACIÓN ENCONTRADA EN LA WEB (puede ser de otra tienda; úsala solo si coincide con la referencia y reescríbela, no la copies):\n"
                . $web['text'] . "\n";
        }
        $prompt .= "\nREGLAS:\n"
            . "- No inventes características técnicas que no estén en los datos o en la información de la web (movimiento, materiales, resistencia, garantía…).\n"
            . "- No menciones precios, descuentos, stock, envíos ni el nombre de ninguna tienda.\n"
            . "- Español neutro, tono comercial y claro, sin exageraciones ni emojis.\n"
            . "- descripcion: 2 o 3 párrafos en HTML (<p>, puedes usar <strong> y una lista <ul><li> de características).\n"
            . "- descripcion_corta: 1 o 2 frases en un solo <p>, máximo 300 caracteres.\n";
        if ('' !== trim((string) $instructions)) {
            $prompt .= "- " . trim($instructions) . "\n";
        }
        $prompt .= "\nResponde SOLO con un JSON válido, sin texto antes ni después:\n"
            . '{"descripcion": "<p>…</p>", "descripcion_corta": "<p>…</p>"}';
        return $prompt;
    }

    // ------------------------------------------------------------------ internos

    private static function generate($data, $web, $config)
    {
        $ai = IACON_AI::get($config['ai_connection']);
        if (!$ai) {
            return new WP_Error('hsy_ai_connection', 'La conexión de IA «' . $config['ai_connection'] . '» no existe o está desactivada en IA Conector.');
        }
        $response = $ai->sendPrompt(self::prompt($data, $web, $config['ai_instructions']));
        if (!is_array($response) || 'ok' !== ($response['status'] ?? '')) {
            return new WP_Error('hsy_ai', 'IA: ' . (is_array($response) ? ($response['message'] ?? 'error') : 'respuesta vacía'));
        }
        $json = self::parse_json((string) $response['data']);
        if (!$json) {
            return new WP_Error('hsy_ai_json', 'La IA no devolvió un JSON válido.');
        }
        return [
            'description' => self::html($json['descripcion'] ?? ''),
            'short_description' => self::html($json['descripcion_corta'] ?? ''),
        ];
    }

    /**
     * JSON de la respuesta, aunque venga envuelto en ```json … ``` o con texto alrededor.
     */
    private static function parse_json($text)
    {
        $text = preg_replace('/^\s*```(?:json)?\s*|\s*```\s*$/i', '', trim($text));
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if (false === $start || false === $end || $end <= $start) {
            return null;
        }
        $data = json_decode(substr($text, $start, $end - $start + 1), true);
        return is_array($data) ? $data : null;
    }

    private static function html($html)
    {
        $html = trim(wp_kses((string) $html, self::ALLOWED_TAGS));
        if ('' !== $html && false === strpos($html, '<p') && false === strpos($html, '<ul')) {
            $html = wpautop($html);
        }
        return $html;
    }

    /**
     * Datos del producto tal como están en la tienda (nombre del catálogo, marca, características).
     */
    private static function product_data($product)
    {
        $brands = taxonomy_exists('product_brand') ? wp_get_post_terms($product->get_id(), 'product_brand', ['fields' => 'names']) : [];
        $data = [
            'nombre' => (string) $product->get_meta('_hsy_source_name') ?: $product->get_name(),
            'nombre en la tienda' => $product->get_name(),
            'marca' => is_array($brands) && $brands ? implode(', ', $brands) : '',
            'referencia' => $product->get_sku(),
        ];
        foreach ($product->get_attributes() as $attribute) {
            if ($attribute instanceof WC_Product_Attribute && !$attribute->is_taxonomy()) {
                $data[mb_strtolower($attribute->get_name(), 'UTF-8')] = implode(', ', $attribute->get_options());
            }
        }
        return $data;
    }
}
