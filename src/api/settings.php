<?php

/**
 * Guardar la configuración (formulario de la página del plugin, vía admin-post.php).
 *
 * Para acciones por AJAX: una clase HSY_API_* con const NONCE, un guard() con
 * check_ajax_referer() + current_user_can() y wp_send_json_success/error.
 */
class HSY_API_SETTINGS
{
    const ACTION = 'hsy_save_settings';

    public static function init()
    {
        add_action('admin_post_' . self::ACTION, [self::class, 'save']);
    }

    public static function save()
    {
        if (!current_user_can(HSY_CAPABILITY)) {
            wp_die('No tienes permisos para cambiar esta configuración.', '', ['response' => 403]);
        }
        check_admin_referer(self::ACTION);

        $config = HSY_USE_DATA_CONFIG::sanitize(wp_unslash($_POST['hsy'] ?? []));
        (new HSY_USE_DATA_CONFIG())->set($config);

        HSY_LOG_SERVICE::add('guardar_ajustes', $config);
        set_transient('hsy_respond', [
            'status' => 'ok',
            'message' => 'Configuración guardada.',
        ], 60);

        $tab = sanitize_key($_POST['hsy_tab'] ?? '');
        wp_safe_redirect(add_query_arg(['page' => HSY_KEY, 'tab' => $tab], admin_url('admin.php')));
        exit;
    }
}

HSY_API_SETTINGS::init();
