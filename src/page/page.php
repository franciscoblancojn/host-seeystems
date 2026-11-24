<?php

use franciscoblancojn\wordpress_utils\FWUPage;
use franciscoblancojn\wordpress_utils\FWURespond;

echo FWUPage::css();

// Una pestaña por archivo en src/page/sections/<key>.php. 'form' => true la mete en el formulario de ajustes.
$TAGS = [
    [
        'key' => 'productos',
        'title' => 'Productos',
        'form' => false,
    ],
    [
        'key' => 'conexion',
        'title' => 'Conexión',
        'form' => true,
    ],
    [
        'key' => 'importacion',
        'title' => 'Importación',
        'form' => true,
    ],
];
$keys = wp_list_pluck($TAGS, 'key');
$defaultTag = in_array($_GET['tab'] ?? '', $keys, true) ? $_GET['tab'] : $TAGS[0]['key']; // phpcs:ignore WordPress.Security.NonceVerification
$config = (new HSY_USE_DATA_CONFIG())->get();
$respond = get_transient('hsy_respond');

?>
<div id="page-<?= esc_attr(HSY_KEY) ?>" class="wrap hsy-page">
    <h1>Host Seeystems <span class="hsy-version">v<?= esc_html(HSY_get_version()) ?></span></h1>
    <?php
    if ($respond) {
        delete_transient('hsy_respond');
        FWURespond::render($respond);
    }
    ?>
    <?php FWUPage::tabs($TAGS, $defaultTag); ?>
    <?php foreach ($TAGS as $value) : if ($value['form']) continue; ?>
        <div class="tab-content <?= $value['key'] === $defaultTag ? 'nav-tab-active' : '' ?>" id="<?= esc_attr($value['key']) ?>">
            <?php require HSY_DIR . 'src/page/sections/' . $value['key'] . '.php'; ?>
        </div>
    <?php endforeach; ?>
    <form method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>" class="hsy-form">
        <input type="hidden" name="action" value="<?= esc_attr(HSY_API_SETTINGS::ACTION) ?>">
        <input type="hidden" name="hsy_tab" value="<?= esc_attr($defaultTag) ?>" id="hsy-tab">
        <?php wp_nonce_field(HSY_API_SETTINGS::ACTION); ?>
        <?php foreach ($TAGS as $value) : if (!$value['form']) continue; ?>
            <div class="tab-content <?= $value['key'] === $defaultTag ? 'nav-tab-active' : '' ?>" id="<?= esc_attr($value['key']) ?>">
                <?php require HSY_DIR . 'src/page/sections/' . $value['key'] . '.php'; ?>
                <?php submit_button('Guardar cambios'); ?>
            </div>
        <?php endforeach; ?>
    </form>
</div>
<?php

echo FWUPage::js(HSY_KEY);
