<?php

/**
 * AJAX de la pestaña Productos: probar conexión, leer el catálogo, importar por lotes y
 * generar las descripciones pendientes (de a un producto).
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
        add_action('wp_ajax_hsy_pending', [self::class, 'pending']);
        add_action('wp_ajax_hsy_content', [self::class, 'content']);
        add_action('wp_ajax_hsy_preview', [self::class, 'preview']);
        add_action('wp_ajax_hsy_ai_resume', [self::class, 'ai_resume']);
        add_action('wp_ajax_hsy_reset_prompts', [self::class, 'reset_prompts']);
        add_action('wp_ajax_hsy_publishable', [self::class, 'publishable']);
        add_action('wp_ajax_hsy_publish', [self::class, 'publish']);
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
            'pending' => count(HSY_CONTENT::pending_ids()),
            'stats' => HSY_STATS::counts(),
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
            'pending' => count(HSY_CONTENT::pending_ids()),
            'stats' => HSY_STATS::counts(),
        ]);
    }

    /** IDs de los productos importados a los que les falta algo (descripción, corta, estilo o categorías). */
    public static function pending()
    {
        self::guard();
        wp_send_json_success(HSY_CONTENT::pending_ids());
    }

    /** IDs de los productos importados en borrador que ya tienen descripción. */
    public static function publishable()
    {
        self::guard();
        wp_send_json_success(HSY_STATS::publishable_ids());
    }

    /** Publica un lote de productos (máx. HSY_STATS::PUBLISH_BATCH); cada uno se vuelve a comprobar. */
    public static function publish()
    {
        self::guard();
        $ids = array_slice(array_filter(array_map('absint', (array) wp_unslash($_POST['ids'] ?? []))), 0, HSY_STATS::PUBLISH_BATCH);
        if (!$ids) {
            wp_send_json_error('No hay productos.');
        }
        $results = [];
        foreach ($ids as $id) {
            $result = HSY_STATS::publish($id);
            $results[] = is_wp_error($result)
                ? ['product_id' => $id, 'ok' => false, 'message' => $result->get_error_message()]
                : ['product_id' => $id, 'ok' => true];
        }
        wp_send_json_success($results);
    }

    /** Restablece los prompts (descripción, corta, estilo) y el cierre de la descripción a los de por defecto. */
    public static function reset_prompts()
    {
        self::guard();
        $data = new HSY_USE_DATA_CONFIG();
        $config = $data->get_saved();
        foreach (['ai_prompt_description', 'ai_prompt_short', 'ai_prompt_style', 'description_closing'] as $key) {
            $config[$key] = ''; // vacío = el de por defecto (y sigue sus mejoras en próximas versiones)
        }
        $data->set($config);
        HSY_LOG_SERVICE::add('prompts_restablecidos', []);
        wp_send_json_success('Prompts restablecidos a los de por defecto.');
    }

    /** Quita la pausa por límite de uso de una conexión de IA. */
    public static function ai_resume()
    {
        self::guard();
        $key = sanitize_text_field(wp_unslash($_POST['key'] ?? ''));
        HSY_CONTENT::resume_connection($key);
        HSY_LOG_SERVICE::add('ai_resume', ['connection' => $key]);
        wp_send_json_success('Conexión reanudada.');
    }

    /** Prueba los prompts del formulario (sin guardar) con un producto. No escribe nada. */
    public static function preview()
    {
        self::guard();
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }
        $result = HSY_CONTENT::preview(absint($_POST['product_id'] ?? 0), [
            'ai_prompt_description' => HSY_USE_DATA_CONFIG::prompt(wp_unslash($_POST['prompt_description'] ?? ''), ''),
            'ai_prompt_short' => HSY_USE_DATA_CONFIG::prompt(wp_unslash($_POST['prompt_short'] ?? ''), ''),
            'ai_prompt_style' => HSY_USE_DATA_CONFIG::prompt(wp_unslash($_POST['prompt_style'] ?? ''), ''),
        ]);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        wp_send_json_success($result);
    }

    /** Genera la descripción / descripción corta pendiente de un producto (máx. HSY_CONTENT_BATCH). */
    public static function content()
    {
        self::guard();
        $ids = array_slice(array_filter(array_map('absint', (array) wp_unslash($_POST['ids'] ?? []))), 0, HSY_CONTENT_BATCH);
        if (!$ids) {
            wp_send_json_error('No hay productos.');
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }
        $force = !empty($_POST['force']); // Regenerar: reescribe aunque ya tenga descripción
        $config = (new HSY_USE_DATA_CONFIG())->get();
        $results = [];
        foreach ($ids as $id) {
            $result = HSY_CONTENT::fill($id, $config, $force);
            if (is_wp_error($result)) {
                $data = (array) $result->get_error_data();
                $results[] = [
                    'product_id' => $id,
                    'ok' => false,
                    'message' => $result->get_error_message(),
                    'partial' => array_values((array) ($data['fields'] ?? [])), // lo que sí se hizo (p. ej. categorías)
                    'stop' => HSY_CONTENT::is_stop_error($result),            // ninguna IA disponible: detener la cola
                ];
                if (HSY_CONTENT::is_stop_error($result)) {
                    break;
                }
                continue;
            }
            $results[] = ['product_id' => $id, 'ok' => true, 'fields' => $result['fields'], 'source' => $result['source'], 'message' => implode(' · ', $result['warnings'])];
        }
        // Textos ya guardados de cada producto, para repintar su fila en la tabla sin recargarla.
        $texts = HSY_CONTENT::texts($ids);
        foreach ($results as $i => $result) {
            $results[$i]['row'] = $texts[$result['product_id']] ?? null;
        }
        wp_send_json_success($results);
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
        // Textos y categorías de los importados, para repintar sus filas (badges de pendiente) sin recargar.
        $texts = HSY_CONTENT::texts(array_filter(wp_list_pluck($results, 'product_id')));
        foreach ($results as $k => $r) {
            if (!empty($r['product_id'])) {
                $results[$k]['row'] = $texts[$r['product_id']] ?? null;
            }
        }
        wp_send_json_success($results);
    }
}

HSY_API_IMPORT::init();
