<?php

add_action('admin_menu', function () {
    add_submenu_page(
        HSEY_KEY,
        'Configuracion',
        'Configuracion',
        'manage_options',
        HSEY_KEY . '_config',
        'HSEY_PAGE_CONFIG_VIEW'
    );
    remove_submenu_page(HSEY_KEY, HSEY_KEY);
});

function HSEY_PAGE_CONFIG_VIEW()
{
    require_once HSEY_DIR . 'src/page/pages/config/page.php';
}
