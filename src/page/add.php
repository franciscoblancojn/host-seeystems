<?php

add_action('admin_menu', function () {
    add_menu_page(
        'Host Seeystems',
        'Host Seeystems',
        'manage_options',
        HSEY_KEY,
        'HSEY_REDIRECT_FIRST_SUBMENU',
        'dashicons-cloud'
    );
});
function HSEY_REDIRECT_FIRST_SUBMENU()
{
    wp_redirect(admin_url('admin.php?page=' . HSEY_KEY . '_config'));
    exit;
}
