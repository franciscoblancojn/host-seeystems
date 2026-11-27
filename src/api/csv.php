<?php

/**
 * Contenido por CSV: exportar (descarga vía admin-post) e importar (vista previa + aplicar por AJAX).
 */
class HSY_API_CSV
{
    const EXPORT = 'hsy_csv_export';

    public static function init()
    {
        add_action('admin_post_' . self::EXPORT, [self::class, 'export']);
        add_action('wp_ajax_hsy_csv_preview', [self::class, 'preview']);
        add_action('wp_ajax_hsy_csv_apply', [self::class, 'apply']);
    }

    /** Descarga el CSV de los productos importados (solo con contenido faltante, o todos). */
    public static function export()
    {
        if (!current_user_can(HSY_CAPABILITY)) {
            wp_die('No tienes permisos para exportar.', '', ['response' => 403]);
        }
        check_admin_referer(self::EXPORT);
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
        $only_missing = !empty($_GET['missing']);
        $rows = HSY_CSV::export_rows($only_missing);
        HSY_LOG_SERVICE::add('csv_export', ['rows' => count($rows), 'only_missing' => $only_missing]);
        HSY_CSV::output($rows, 'productos-contenido-' . ($only_missing ? 'faltante-' : '') . wp_date('Y-m-d-His') . '.csv');
        exit;
    }

    private static function guard()
    {
        check_ajax_referer(HSY_API_IMPORT::NONCE, 'nonce');
        if (!current_user_can(HSY_CAPABILITY)) {
            wp_send_json_error('No tienes permisos.', 403);
        }
    }

    /** Lee el CSV subido y devuelve qué se haría, sin escribir nada. */
    public static function preview()
    {
        self::guard();
        $file = $_FILES['file'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        if (!$file || UPLOAD_ERR_OK !== (int) $file['error'] || !is_uploaded_file($file['tmp_name'])) {
            wp_send_json_error('No llegó el archivo (o es demasiado grande para el servidor).');
        }
        if (!preg_match('/\.(csv|txt)$/i', (string) $file['name'])) {
            wp_send_json_error('El archivo debe ser .csv.');
        }
        $result = HSY_CSV::preview($file['tmp_name'], sanitize_key(wp_unslash($_POST['mode'] ?? 'missing')));
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        wp_send_json_success($result);
    }

    /** Aplica un lote de la importación preparada en la vista previa. */
    public static function apply()
    {
        self::guard();
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }
        $result = HSY_CSV::apply(sanitize_text_field(wp_unslash($_POST['token'] ?? '')), absint($_POST['offset'] ?? 0));
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        wp_send_json_success($result);
    }
}

HSY_API_CSV::init();
