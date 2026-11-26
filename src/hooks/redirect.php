<?php

/**
 * Redirección 301 de la URL vieja de un producto importado al que se le cambió el nombre (y el slug).
 *
 * WordPress guarda el slug anterior en _wp_old_slug y lo redirige solo (wp_old_slug_redirect), pero
 * eso falla con estructuras de enlaces de producto personalizadas (p. ej. sin «/producto/»). Si la
 * petición da 404 y su último segmento es el slug viejo de un producto importado publicado, se redirige.
 */
class HSY_HOOK_REDIRECT
{
    public static function init()
    {
        // Después de wp_old_slug_redirect (prioridad 10): solo actúa si WordPress no redirigió.
        add_action('template_redirect', [self::class, 'redirect'], 20);
    }

    public static function redirect()
    {
        if (!is_404()) {
            return;
        }
        $path = (string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $slug = sanitize_title(basename(untrailingslashit($path)));
        if ('' === $slug) {
            return;
        }
        global $wpdb;
        $product_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} old ON old.post_id = p.ID AND old.meta_key = '_wp_old_slug' AND old.meta_value = %s
             INNER JOIN {$wpdb->postmeta} imp ON imp.post_id = p.ID AND imp.meta_key = %s AND imp.meta_value = 'yes'
             WHERE p.post_type = 'product' AND p.post_status = 'publish'
             ORDER BY p.ID DESC LIMIT 1",
            $slug,
            HSY_IMPORTED_META
        ));
        if ($product_id) {
            wp_safe_redirect(get_permalink($product_id), 301, 'Host Seeystems');
            exit;
        }
    }
}

HSY_HOOK_REDIRECT::init();
