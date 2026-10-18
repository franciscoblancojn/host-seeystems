<?php

use franciscoblancojn\wordpress_utils\FWURespond;

$respond_productos = null;
if (
    isset($_POST['save']) && $_POST['save'] == "productos"
    && current_user_can('manage_options')
    && check_admin_referer('hsey_productos', 'hsey_nonce')
) {
    $respond_productos = HSEY_API_PRODUCTS::sync();
}

$PRODUCTS = (new HSEY_USE_DATA_PRODUCTS())->get();
$ITEMS = $PRODUCTS['items'] ?? [];
$COLUMNS = [];
foreach ($ITEMS as $item) {
    foreach (array_keys((array) $item) as $col) {
        $COLUMNS[$col] = true;
    }
}
$COLUMNS = array_keys($COLUMNS);

?>
<form method="post">
    <?php FWURespond::render($respond_productos) ?>
    <input type="hidden" name="save" value="productos">
    <?php wp_nonce_field('hsey_productos', 'hsey_nonce'); ?>
    <div class="content-title-btn">
        <p>
            <?php if (!empty($PRODUCTS['date'])) { ?>
                Última sincronización: <strong><?= esc_html($PRODUCTS['date']) ?></strong> —
                <strong><?= count($ITEMS) ?></strong> productos.
            <?php } else { ?>
                Aún no se han traído productos.
            <?php } ?>
        </p>
        <button type="submit" name="submit" class="button button-primary">
            Traer productos
        </button>
    </div>
</form>

<?php if (!empty($ITEMS)) { ?>
    <table class="widefat striped hsey-products">
        <thead>
            <tr>
                <?php foreach ($COLUMNS as $col) { ?>
                    <th><?= esc_html($col) ?></th>
                <?php } ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ITEMS as $item) { ?>
                <tr>
                    <?php foreach ($COLUMNS as $col) {
                        $value = $item[$col] ?? '';
                        if ($col === 'imgs') {
                            $value = $value[0] ?? '';
                        } elseif (is_array($value) || is_object($value)) {
                            $value = wp_json_encode($value);
                        }
                    ?>
                        <td>
                            <?php if ($col === 'imgs' && $value) { ?>
                                <img src="<?= esc_url($value) ?>" alt="" class="hsey-thumb" loading="lazy" />
                            <?php } else { ?>
                                <?= esc_html($value) ?>
                            <?php } ?>
                        </td>
                    <?php } ?>
                </tr>
            <?php } ?>
        </tbody>
    </table>
    <style>
        .hsey-products {
            margin-top: 1rem;
        }

        .hsey-thumb {
            width: 48px;
            height: 48px;
            object-fit: cover;
        }
    </style>
<?php } ?>
<?php
