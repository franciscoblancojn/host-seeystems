<?php

/**
 * AJAX de la pestaña Productos: probar conexión, leer el catálogo e importar por lotes.
 */
class HSY_API_IMPORT
{
    const NONCE = 'hsy_import';

    public static function init()
    {
        add_action('wp_ajax_hsy_test', [self::class, 'test']);
        add_action('wp_ajax_hsy_load', [self::class, 'load']);
        add_action('wp_ajax_hsy_rows', [self::class, 'rows']);
        add_action('wp_ajax_hsy_import', [self::class, 'import']);
    }

    private static function guard()
    {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can(HSY_CAPABILITY)) {
            wp_send_json_error('No tienes permisos para importar productos.', 403);
        }
    }

    /** Inicia sesión de nuevo con los datos guardados. */
    public static function test()
    {
        self::guard();
        $login = HSY_CLIENT::login();
        if (is_wp_error($login)) {
            wp_send_json_error($login->get_error_message());
        }
        wp_send_json_success('Conexión correcta: sesión iniciada en el catálogo.');
    }

    /** Lee el catálogo del origen y devuelve las filas. */
    public static function load()
    {
        self::guard();
        $count = HSY_CATALOG_SERVICE::refresh();
        if (is_wp_error($count)) {
            wp_send_json_error($count->get_error_message());
        }
        wp_send_json_success([
            'rows' => HSY_CATALOG_SERVICE::rows(),
            'fetched_at' => wp_date('Y-m-d H:i'),
        ]);
    }

    /** Filas del último catálogo guardado (sin pedirlo al origen). */
    public static function rows()
    {
        self::guard();
        $catalog = new HSY_USE_DATA_CATALOG();
        wp_send_json_success([
            'rows' => HSY_CATALOG_SERVICE::rows(),
            'fetched_at' => $catalog->fetched_at() ? wp_date('Y-m-d H:i', $catalog->fetched_at()) : '',
        ]);
    }

    /** Importa un lote de ids del catálogo (máx. HSY_BATCH_SIZE). */
    public static function import()
    {
        self::guard();
        $ids = array_slice(array_map('strval', array_map('sanitize_text_field', (array) wp_unslash($_POST['ids'] ?? []))), 0, HSY_BATCH_SIZE);
        if (!$ids) {
            wp_send_json_error('No hay productos seleccionados.');
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $catalog = new HSY_USE_DATA_CATALOG();
        $config = (new HSY_USE_DATA_CONFIG())->get();
        $results = [];
        foreach ($ids as $id) {
            $item = $catalog->item($id);
            if (!$item) {
                $results[] = ['source_id' => $id, 'ok' => false, 'message' => 'No está en el catálogo cargado.'];
                continue;
            }
            $result = HSY_IMPORTER::import($item, $config);
            if (is_wp_error($result)) {
                HSY_LOG_SERVICE::add('import_error', ['source_id' => $id, 'message' => $result->get_error_message()]);
                $results[] = ['source_id' => $id, 'ok' => false, 'message' => $result->get_error_message()];
                continue;
            }
            $results[] = [
                'source_id' => $id,
                'ok' => true,
                'action' => $result['action'],
                'product_id' => $result['id'],
                'edit' => get_edit_post_link($result['id'], 'raw'),
                'message' => implode(' · ', $result['warnings']),
            ];
        }
        wp_send_json_success($results);
    }
}

HSY_API_IMPORT::init();
