<?php

/**
 * Migraciones de datos de una sola vez, en admin_init (también en las peticiones AJAX de wp-admin).
 * La versión aplicada se guarda en la opción HSY_MIGRATION.
 *
 * 1. HSY_CREATED_META en los productos importados antes de que existiera: 'no' si el producto ya existía
 *    cuando se enlazó (su fecha de creación es más de 7 días anterior a su última importación), 'yes' si no.
 *    Un producto que crea el importador tiene la misma fecha que su importación; los enlazados por SKU,
 *    meses antes.
 */
class HSY_HOOK_MIGRATE
{
    const OPTION = 'HSY_MIGRATION';
    const VERSION = 1;

    /** Diferencia mínima entre la fecha del producto y su importación para considerarlo ya existente. */
    const EXISTING_GAP = 7 * DAY_IN_SECONDS;

    public static function init()
    {
        add_action('admin_init', [self::class, 'run']);
    }

    public static function run()
    {
        if ((int) get_option(self::OPTION, 0) >= self::VERSION) {
            return;
        }
        if (!self::created_meta()) {
            return; // se reintenta en la próxima carga de wp-admin
        }
        update_option(self::OPTION, self::VERSION, false);
    }

    private static function created_meta()
    {
        global $wpdb;
        // Devuelve false si la consulta falla.
        // post_date (hora local) y no post_date_gmt: en los borradores post_date_gmt puede ser 0000-00-00.
        // La diferencia de zona horaria (horas) no afecta a un margen de 7 días.
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
             SELECT p.ID, %s,
                    IF(CAST(ia.meta_value AS SIGNED) - CAST(UNIX_TIMESTAMP(p.post_date) AS SIGNED) > %d, 'no', 'yes')
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} imp ON imp.post_id = p.ID AND imp.meta_key = %s AND imp.meta_value = 'yes'
             INNER JOIN {$wpdb->postmeta} ia ON ia.post_id = p.ID AND ia.meta_key = '_hsy_imported_at'
             LEFT JOIN {$wpdb->postmeta} cr ON cr.post_id = p.ID AND cr.meta_key = %s
             WHERE p.post_type = 'product' AND cr.meta_id IS NULL",
            HSY_CREATED_META,
            self::EXISTING_GAP,
            HSY_IMPORTED_META,
            HSY_CREATED_META
        ));
        if (false === $inserted) {
            HSY_LOG_SERVICE::add('migracion_error', ['version' => 1, 'message' => $wpdb->last_error]);
            return false;
        }
        $existing = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = 'no'",
            HSY_CREATED_META
        ));
        wp_cache_flush();
        HSY_LOG_SERVICE::add('migracion', ['version' => 1, 'productos_ya_existentes' => $existing]);
        return true;
    }
}

HSY_HOOK_MIGRATE::init();
