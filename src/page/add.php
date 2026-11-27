<?php

add_action('admin_menu', function () {
    add_menu_page(
        'Host Seeystems',            // Título de la página
        'Importar productos',        // Nombre en el menú
        HSY_CAPABILITY,              // Permisos
        HSY_KEY,                     // Slug
        'HSY_PAGE_VIEW',
        'dashicons-download',
        58
    );
});

function HSY_PAGE_VIEW()
{
    require_once HSY_DIR . 'src/page/page.php';
}

// CSS y JS del admin solo en la página del plugin.
add_action('admin_enqueue_scripts', function ($hook) {
    if ('toplevel_page_' . HSY_KEY !== $hook) {
        return;
    }
    $css = HSY_DIR . 'src/css/admin.css';
    $js = HSY_DIR . 'src/js/admin.js';
    wp_enqueue_style('hsy-admin', HSY_URL . 'src/css/admin.css', [], filemtime($css));
    wp_enqueue_script('hsy-admin', HSY_URL . 'src/js/admin.js', ['jquery'], filemtime($js), true);
    wp_localize_script('hsy-admin', 'HSY_ADMIN', [
        'ajax' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce(HSY_API_IMPORT::NONCE),
        'batch' => HSY_BATCH_SIZE,
        'page' => HSY_KEY,
        'min_stock' => HSY_STOCK::min(),
        'content_batch' => HSY_CONTENT_BATCH,
        'content_enabled' => HSY_CONTENT::enabled(),
        'currency' => html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8'),
        'decimals' => wc_get_price_decimals(),
        'thousand' => wc_get_price_thousand_separator(),
        'decimal' => wc_get_price_decimal_separator(),
    ]);
});
