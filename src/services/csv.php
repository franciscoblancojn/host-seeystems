<?php

/**
 * Contenido por CSV: exportar los productos importados para redactar sus textos fuera del sitio
 * (en local, con otra IA o a mano) e importarlos después.
 *
 * Exportar: una fila por producto con sus datos (contexto, lo que le falta) y columnas vacías para
 * rellenar: intro, beneficios (uno por línea o separados por « || »), caracteristicas, descripcion_corta.
 * Importar: con intro (+ beneficios + caracteristicas) se arma la descripción con la estructura fija de
 * HSY_LAYOUT (tabla de especificaciones y cierre incluidos), igual que con la IA. También se acepta una
 * columna descripcion_html ya armada. Modo «faltantes» (por defecto): solo se escribe lo que está vacío
 * o pendiente; modo «reemplazar»: se escribe aunque ya tenga.
 */
class HSY_CSV
{
    const COLUMNS = [
        'product_id', 'sku', 'estado', 'nombre', 'nombre_tienda', 'tipo_producto', 'marca', 'genero',
        'categorias', 'falta', 'contexto',
        'intro', 'beneficios', 'caracteristicas', 'descripcion_corta',
    ];

    /** Filas por petición al aplicar la importación. */
    const APPLY_BATCH = 25;

    /** Tiempo que se guarda un CSV subido a la espera de «Aplicar». */
    const TTL = 2 * HOUR_IN_SECONDS;

    // ------------------------------------------------------------------ exportar

    /**
     * Filas del CSV. $only_missing: solo los que tienen contenido faltante (descripción, corta o categorías).
     */
    public static function export_rows($only_missing = true)
    {
        global $wpdb;
        $ids = array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} imp ON imp.post_id = p.ID AND imp.meta_key = %s AND imp.meta_value = 'yes'
             WHERE p.post_type = 'product' AND p.post_status NOT IN ('trash', 'auto-draft')
             ORDER BY p.ID ASC",
            HSY_IMPORTED_META
        )));
        $rows = [];
        foreach ($ids as $id) {
            $product = wc_get_product($id);
            if (!$product) {
                continue;
            }
            $missing = self::missing($product);
            if ($only_missing && !$missing) {
                continue;
            }
            $vars = HSY_CONTENT::variables($product);
            $rows[] = [
                'product_id' => $id,
                'sku' => $product->get_sku(),
                'estado' => $product->get_status(),
                'nombre' => $vars['nombre'],
                'nombre_tienda' => $product->get_name(),
                'tipo_producto' => $vars['tipo_producto'],
                'marca' => $vars['marca'],
                'genero' => $vars['genero'],
                'categorias' => implode(', ', wp_list_pluck(array_filter(array_map(function ($cid) {
                    $term = get_term($cid, 'product_cat');
                    return $term instanceof WP_Term ? $term : null;
                }, $product->get_category_ids())), 'name')),
                'falta' => implode(', ', $missing),
                'contexto' => $vars['contexto'],
                'intro' => '',
                'beneficios' => '',
                'caracteristicas' => '',
                'descripcion_corta' => '',
            ];
            // Liberar memoria en catálogos grandes.
            wc_delete_product_transients($id);
        }
        return $rows;
    }

    /**
     * Qué le falta al producto: descripcion, descripcion_corta, categorias.
     */
    public static function missing($product)
    {
        $missing = [];
        if ('' === trim(wp_strip_all_tags($product->get_description()))) {
            $missing[] = 'descripcion';
        }
        if (HSY_CONTENT::short_pending($product)) {
            $missing[] = 'descripcion_corta';
        }
        if (!HSY_CATEGORIES::has_categories($product)) {
            $missing[] = 'categorias';
        }
        return $missing;
    }

    /**
     * Escribe el CSV (UTF-8 con BOM, para que Excel muestre bien los acentos) en la salida.
     */
    public static function output($rows, $filename)
    {
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, self::COLUMNS);
        foreach ($rows as $row) {
            $line = [];
            foreach (self::COLUMNS as $col) {
                $line[] = self::cell($row[$col] ?? '');
            }
            fputcsv($out, $line);
        }
        fclose($out);
    }

    // ------------------------------------------------------------------ importar

    /**
     * Lee un CSV subido y prepara la importación (sin escribir nada): valida cada fila y guarda el
     * resultado para «Aplicar». Devuelve ['token', 'summary', 'rows' => muestra] o WP_Error.
     */
    public static function preview($file, $mode)
    {
        $rows = self::read($file);
        if (is_wp_error($rows)) {
            return $rows;
        }
        $mode = 'replace' === $mode ? 'replace' : 'missing';
        $plan = [];
        $summary = ['rows' => count($rows), 'update' => 0, 'skip' => 0, 'error' => 0, 'description' => 0, 'short' => 0];
        foreach ($rows as $n => $row) {
            $item = self::plan_row($row, $mode);
            $item['line'] = $n + 2; // +1 encabezado, +1 base 1
            $plan[] = $item;
            $summary[$item['action']]++;
            if ('update' === $item['action']) {
                $summary['description'] += in_array('description', $item['fields'], true) ? 1 : 0;
                $summary['short'] += in_array('short_description', $item['fields'], true) ? 1 : 0;
            }
        }
        $token = wp_generate_password(20, false);
        set_transient(self::key($token), ['mode' => $mode, 'plan' => $plan, 'user' => get_current_user_id()], self::TTL);
        HSY_LOG_SERVICE::add('csv_preview', ['summary' => $summary, 'mode' => $mode]);
        return [
            'token' => $token,
            'summary' => $summary,
            'mode' => $mode,
            'issues' => array_slice(array_values(array_filter($plan, function ($p) {
                return 'update' !== $p['action'];
            })), 0, 50),
        ];
    }

    /**
     * Aplica un lote de la importación preparada. Devuelve ['done', 'total', 'results'] o WP_Error.
     */
    public static function apply($token, $offset)
    {
        $data = get_transient(self::key($token));
        if (!$data || (int) $data['user'] !== get_current_user_id()) {
            return new WP_Error('hsy_csv_expired', 'La vista previa caducó: vuelve a subir el CSV.');
        }
        $config = (new HSY_USE_DATA_CONFIG())->get();
        $plan = $data['plan'];
        $slice = array_slice($plan, (int) $offset, self::APPLY_BATCH);
        $results = [];
        foreach ($slice as $item) {
            if ('update' !== $item['action']) {
                continue;
            }
            $result = self::write($item, $data['mode'], $config);
            $results[] = is_wp_error($result)
                ? ['product_id' => $item['product_id'], 'ok' => false, 'message' => 'Línea ' . $item['line'] . ': ' . $result->get_error_message()]
                : ['product_id' => $item['product_id'], 'ok' => true, 'fields' => $result];
        }
        $done = min(count($plan), (int) $offset + count($slice));
        if ($done >= count($plan)) {
            delete_transient(self::key($token));
        }
        $ids = array_filter(wp_list_pluck($results, 'product_id'));
        $texts = HSY_CONTENT::texts($ids);
        foreach ($results as $i => $r) {
            $results[$i]['row'] = $texts[$r['product_id']] ?? null;
        }
        return ['done' => $done, 'total' => count($plan), 'results' => $results];
    }

    // ------------------------------------------------------------------ internos

    /**
     * Qué se hará con una fila: update (con los campos) / skip (nada que escribir) / error.
     */
    private static function plan_row($row, $mode)
    {
        $id = absint($row['product_id'] ?? 0);
        $sku = trim((string) ($row['sku'] ?? ''));
        if (!$id && '' !== $sku) {
            $id = (int) wc_get_product_id_by_sku($sku);
        }
        $base = ['product_id' => $id, 'sku' => $sku, 'fields' => [], 'action' => 'error', 'message' => ''];
        $product = $id ? wc_get_product($id) : null;
        if (!$product || !HSY_STOCK::is_imported($product)) {
            return array_merge($base, ['message' => 'Producto no encontrado o no importado (product_id ' . ($row['product_id'] ?? '') . ', sku ' . $sku . ').']);
        }
        $has_description = '' !== trim((string) ($row['intro'] ?? '')) || '' !== trim((string) ($row['descripcion_html'] ?? ''));
        $has_short = '' !== trim((string) ($row['descripcion_corta'] ?? ''));
        $fields = [];
        if ($has_description && ('replace' === $mode || '' === trim(wp_strip_all_tags($product->get_description())))) {
            $fields[] = 'description';
        }
        if ($has_short && ('replace' === $mode || HSY_CONTENT::short_pending($product))) {
            $fields[] = 'short_description';
        }
        if (!$fields) {
            $why = !$has_description && !$has_short ? 'Sin textos en la fila.' : 'Ya tiene ese contenido (modo «solo faltantes»).';
            return array_merge($base, ['action' => 'skip', 'message' => $why]);
        }
        return array_merge($base, [
            'action' => 'update',
            'fields' => $fields,
            'texts' => [
                'intro' => (string) ($row['intro'] ?? ''),
                'beneficios' => (string) ($row['beneficios'] ?? ''),
                'caracteristicas' => (string) ($row['caracteristicas'] ?? ''),
                'descripcion_html' => (string) ($row['descripcion_html'] ?? ''),
                'descripcion_corta' => (string) ($row['descripcion_corta'] ?? ''),
            ],
        ]);
    }

    /**
     * Escribe los textos de una fila en el producto. Devuelve los campos escritos o WP_Error.
     */
    private static function write($item, $mode, $config)
    {
        $product = wc_get_product($item['product_id']);
        if (!$product) {
            return new WP_Error('hsy_csv_product', 'El producto ya no existe.');
        }
        $texts = $item['texts'];
        $written = [];
        if (in_array('description', $item['fields'], true)
            && ('replace' === $mode || '' === trim(wp_strip_all_tags($product->get_description())))) {
            if ('' !== trim($texts['intro'])) {
                $benefits = preg_split('/\s*\|\|\s*|\r\n|\r|\n/u', $texts['beneficios']);
                $parts = HSY_LAYOUT::parse(wp_json_encode([
                    'intro' => $texts['intro'],
                    'beneficios' => array_values(array_filter(array_map('trim', $benefits), 'strlen')),
                    'caracteristicas' => $texts['caracteristicas'],
                ]));
                $html = $parts ? HSY_LAYOUT::build($parts, $product, HSY_CONTENT::variables($product), $config) : '';
            } else {
                $html = HSY_CONTENT::clean_html($texts['descripcion_html']);
            }
            if ('' !== trim(wp_strip_all_tags($html))) {
                $product->set_description($html);
                $written[] = 'description';
            }
        }
        if (in_array('short_description', $item['fields'], true)
            && ('replace' === $mode || HSY_CONTENT::short_pending($product))) {
            $short = HSY_CONTENT::clean_html($texts['descripcion_corta']);
            if ('' !== trim(wp_strip_all_tags($short))) {
                $product->set_short_description($short);
                $product->update_meta_data(HSY_SHORT_AUTO_META, 'no');
                $written[] = 'short_description';
            }
        }
        if (!$written) {
            return new WP_Error('hsy_csv_empty', 'Los textos quedaron vacíos al limpiarlos.');
        }
        $product->update_meta_data('_hsy_content_ai', 'csv');
        $product->update_meta_data('_hsy_content_at', time());
        $product->save();
        HSY_LOG_SERVICE::add('csv_import', ['product_id' => $product->get_id(), 'fields' => $written, 'mode' => $mode]);
        return $written;
    }

    /**
     * Lee el CSV: detecta separador (, ; o tabulador), quita el BOM y devuelve filas asociativas.
     */
    private static function read($file)
    {
        $handle = fopen($file, 'r');
        if (!$handle) {
            return new WP_Error('hsy_csv_read', 'No se pudo leer el archivo.');
        }
        $first = (string) fgets($handle);
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
        $delimiter = ',';
        foreach ([';', "\t"] as $candidate) {
            if (substr_count($first, $candidate) > substr_count($first, $delimiter)) {
                $delimiter = $candidate;
            }
        }
        $header = array_map(function ($h) {
            return sanitize_key(str_replace(' ', '_', trim((string) $h)));
        }, str_getcsv(trim($first), $delimiter));
        if (!in_array('product_id', $header, true) && !in_array('sku', $header, true)) {
            fclose($handle);
            return new WP_Error('hsy_csv_header', 'El CSV debe tener la columna product_id o sku (usa el que exporta el plugin).');
        }
        $rows = [];
        while (false !== ($line = fgetcsv($handle, 0, $delimiter))) {
            if ([null] === $line || !array_filter($line, 'strlen')) {
                continue;
            }
            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = isset($line[$i]) ? trim((string) $line[$i]) : '';
            }
            $rows[] = $row;
        }
        fclose($handle);
        if (!$rows) {
            return new WP_Error('hsy_csv_empty', 'El CSV no tiene filas.');
        }
        return $rows;
    }

    /** Evita que Excel interprete una celda como fórmula (=, +, -, @ al inicio). */
    private static function cell($value)
    {
        $value = (string) $value;
        return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
    }

    private static function key($token)
    {
        return 'hsy_csv_' . md5($token . '|' . get_current_user_id());
    }
}
