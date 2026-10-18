<?php

use franciscoblancojn\wordpress_utils\FWUPage;

echo FWUPage::css();

wp_enqueue_script('hsey-import', HSEY_URL . 'src/js/hsey-import.js', ['jquery'], HSEY_get_version(), true);
wp_localize_script('hsey-import', 'HSEY_IMPORT', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('hsey_nonce'),
    'batch' => 5,
]);

$HSEY_USE_DATA_CONFIG = new HSEY_USE_DATA_CONFIG();

$TAGS = [
    [
        'key' => 'productos',
        'title' => 'Productos',
    ],
    [
        'key' => 'html',
        'title' => 'HTML',
    ],
];
$defaultTag =  $TAGS[0]['key'];

?>
<div id="page-<?= HSEY_KEY ?>" class="wrap">
    <h1>Host Seeystems</h1>
    <?php FWUPage::tabs($TAGS, $defaultTag); ?>
    <?php
    foreach ($TAGS  as $key => $value) {
    ?>
        <div class="tab-content <?= $value['key'] == $defaultTag ? "nav-tab-active" : "" ?>" id="<?= $value['key'] ?>">
            <?php
            require_once HSEY_DIR . 'src/page/sections/' . $value['key'] . ".php";
            ?>
        </div>
    <?php
    }
    ?>
</div>
<?php

echo FWUPage::js(HSEY_KEY);
