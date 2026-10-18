<?php

use franciscoblancojn\wordpress_utils\FWUPage;

echo FWUPage::css();

$HSEY_USE_DATA_CONFIG = new HSEY_USE_DATA_CONFIG();

$TAGS = [
    [
        'key' => 'config',
        'title' => 'Conexión',
    ],
    [
        'key' => 'carga',
        'title' => 'Carga WooCommerce',
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
