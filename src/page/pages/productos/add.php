<?php

add_action('admin_menu', function () {
    add_submenu_page(
        HSEY_KEY,
        'Productos',
        'Productos',
        'manage_options',
        HSEY_KEY . '_productos',
        'HSEY_PAGE_PRODUCTOS_VIEW'
    );
});

function HSEY_PAGE_PRODUCTOS_VIEW()
{
    require_once HSEY_DIR . 'src/page/pages/productos/page.php';
}
