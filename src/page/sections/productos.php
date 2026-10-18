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
// La imagen va justo después del id
$COLUMNS = array_values(array_diff(array_keys($COLUMNS), ['imgs']));
array_splice($COLUMNS, array_search('id', $COLUMNS, true) + 1, 0, ['imgs']);

$IMPORT = (new HSEY_USE_DATA_CONFIG())->getImport();
$IMPORT_FIELDS = [];
foreach ($IMPORT['update_fields'] as $key => $active) {
    if ($active) {
        $IMPORT_FIELDS[] = HSEY_API_IMPORT::FIELDS[$key][0];
    }
}

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
    <div class="hsey-import" id="hsey-import" data-total="<?= count($ITEMS) ?>">
        <h2>Cargar en WooCommerce</h2>
        <?php if (!class_exists('WooCommerce')) { ?>
            <?php FWURespond::render(['status' => 'error', 'message' => 'WooCommerce no está activo.']) ?>
        <?php } else { ?>
            <p>
                Nuevos: se crean con todos los datos como <strong><?= esc_html(HSEY_API_IMPORT::STATUSES[$IMPORT['status']]) ?></strong>.
                Existentes: se actualiza <strong><?= esc_html($IMPORT_FIELDS ? implode(', ', $IMPORT_FIELDS) : 'nada') ?></strong>.
                <a href="<?= esc_url(admin_url('admin.php?page=' . HSEY_KEY . '_config#tag-carga')) ?>">Cambiar configuración</a>
            </p>
            <div class="content-btn">
                <button type="button" class="button button-primary hsey-import-start">
                    Cargar <?= count($ITEMS) ?> productos
                </button>
                <button type="button" class="button hsey-import-stop" hidden>
                    Detener
                </button>
            </div>
            <progress class="hsey-import-progress" max="<?= count($ITEMS) ?>" value="0" hidden></progress>
            <p class="hsey-import-status"></p>
            <ul class="hsey-import-errors"></ul>
        <?php } ?>
    </div>
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

        .hsey-import {
            margin-top: 1rem;
            padding: 0 1rem 1rem;
            background: #fff;
            border: 1px solid #dcdcde;
            border-radius: 8px;
        }

        .hsey-import-progress {
            width: 100%;
            margin-top: 1rem;
        }

        .hsey-import-errors {
            color: #d63638;
            max-height: 200px;
            overflow: auto;
        }

        .hsey-thumb {
            width: 48px;
            height: 48px;
            object-fit: cover;
        }
    </style>
<?php } ?>
<?php
