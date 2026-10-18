<?php

use franciscoblancojn\wordpress_utils\FWUSystemLog;
use franciscoblancojn\wordpress_utils\FWURespond;
use franciscoblancojn\wordpress_utils\FWUTooltip;

$respond_carga = null;
if (
    isset($_POST['save']) && $_POST['save'] == "carga"
    && current_user_can('manage_options')
    && check_admin_referer('hsey_carga', 'hsey_nonce')
) {
    $update_fields = [];
    foreach (HSEY_API_IMPORT::FIELDS as $key => $field) {
        $update_fields[$key] = isset($_POST['update_fields'][$key]);
    }
    $HSEY_USE_DATA_CONFIG->setField('import', [
        'update_fields' => $update_fields,
        'price_source' => sanitize_key(wp_unslash($_POST['price_source'] ?? '')),
        'status' => sanitize_key(wp_unslash($_POST['status'] ?? '')),
    ]);
    FWUSystemLog::add(HSEY_KEY, [
        'type' => "save_import_config",
        'data' => $HSEY_USE_DATA_CONFIG->getImport(),
    ]);
    $respond_carga = ['status' => 'ok', 'message' => 'Configuración de carga guardada.'];
}

$IMPORT = $HSEY_USE_DATA_CONFIG->getImport();

?>
<form method="post">
    <?php FWURespond::render($respond_carga) ?>
    <input type="hidden" name="save" value="carga">
    <?php wp_nonce_field('hsey_carga', 'hsey_nonce'); ?>
    <p>
        Si el producto <strong>no existe</strong> en WooCommerce (se busca por SKU = referencia) se crea con todos los datos.
        Si <strong>ya existe</strong>, solo se actualizan los campos marcados.
    </p>
    <table class="form-table">
        <tr>
            <th scope="row">
                <?php FWUTooltip::render("Campos a actualizar", "Campos que se sobrescriben cuando el producto ya existe. Los demás se dejan como estén en WooCommerce.") ?>
            </th>
            <td>
                <fieldset>
                    <?php foreach (HSEY_API_IMPORT::FIELDS as $key => $field) { ?>
                        <label class="hsey-check">
                            <input
                                type="checkbox"
                                name="update_fields[<?= esc_attr($key) ?>]"
                                value="1"
                                <?php checked($IMPORT['update_fields'][$key]); ?> />
                            <?= esc_html($field[0]) ?>
                        </label>
                    <?php } ?>
                </fieldset>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <?php FWUTooltip::render("Precio regular", "Precio de la plataforma que se usa como precio regular en WooCommerce.") ?>
            </th>
            <td>
                <select name="price_source">
                    <?php foreach (HSEY_API_IMPORT::PRICE_SOURCES as $key => $label) { ?>
                        <option value="<?= esc_attr($key) ?>" <?php selected($IMPORT['price_source'], $key); ?>><?= esc_html($label) ?></option>
                    <?php } ?>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <?php FWUTooltip::render("Estado al crear", "Estado de los productos nuevos. Los existentes conservan su estado.") ?>
            </th>
            <td>
                <select name="status">
                    <?php foreach (HSEY_API_IMPORT::STATUSES as $key => $label) { ?>
                        <option value="<?= esc_attr($key) ?>" <?php selected($IMPORT['status'], $key); ?>><?= esc_html($label) ?></option>
                    <?php } ?>
                </select>
            </td>
        </tr>
    </table>

    <div class="content-btn">
        <button
            type="submit"
            name="submit"
            value="Guardar"
            class="button button-primary">
            Guardar
        </button>
    </div>
    <style>
        .hsey-check {
            display: block;
            margin-bottom: .5rem;
        }
    </style>
</form>
<?php
