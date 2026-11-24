<?php

// Variables disponibles: $config (HSY_USE_DATA_CONFIG::get()).

?>
<table class="form-table" role="presentation">
    <tr>
        <th scope="row"><label for="hsy-status">Estado de los productos nuevos</label></th>
        <td>
            <select id="hsy-status" name="hsy[status]">
                <?php foreach (HSY_USE_DATA_CONFIG::STATUSES as $key => $label) : ?>
                    <option value="<?= esc_attr($key) ?>" <?php selected($config['status'], $key); ?>><?= esc_html($label) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    <tr>
        <th scope="row">Productos ya importados</th>
        <td>
            <label><input type="checkbox" name="hsy[update_existing]" value="1" <?php checked($config['update_existing'], 1); ?>> Actualizarlos al volver a importar</label>
            <fieldset class="hsy-fields">
                <?php foreach (HSY_USE_DATA_CONFIG::UPDATE_FIELDS as $key => $label) : ?>
                    <label><input type="checkbox" name="hsy[update_fields][]" value="<?= esc_attr($key) ?>" <?php checked(in_array($key, (array) $config['update_fields'], true)); ?>> <?= esc_html($label) ?></label>
                <?php endforeach; ?>
            </fieldset>
            <p class="description">Qué se actualiza en un producto existente (se reconoce por su id de origen o por el SKU). Los nuevos se crean con todo.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Contenido</th>
        <td>
            <label><input type="checkbox" name="hsy[import_images]" value="1" <?php checked($config['import_images'], 1); ?>> Descargar imágenes</label><br>
            <label><input type="checkbox" name="hsy[import_categories]" value="1" <?php checked($config['import_categories'], 1); ?>> Crear y asignar categorías (por marca)</label>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="hsy-name-template">Nombre del producto</label></th>
        <td>
            <input type="text" id="hsy-name-template" name="hsy[name_template]" value="<?= esc_attr($config['name_template']) ?>" class="regular-text code">
            <p class="description">Plantilla con <code>{nombre}</code> (obligatorio), <code>{marca}</code> y <code>{ref}</code>. P. ej. <code>{marca} {nombre} {ref}</code>.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="hsy-category-parent">Categoría padre</label></th>
        <td>
            <input type="text" id="hsy-category-parent" name="hsy[category_parent]" value="<?= esc_attr($config['category_parent']) ?>" class="regular-text">
            <p class="description">Cada producto va a la categoría de su marca. Si pones una categoría padre (p. ej. «Relojes»), las marcas se crean dentro de ella.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="hsy-price-source">Precio de venta</label></th>
        <td>
            <select id="hsy-price-source" name="hsy[price_source]">
                <?php foreach (HSY_USE_DATA_CONFIG::PRICE_SOURCES as $key => $label) : ?>
                    <option value="<?= esc_attr($key) ?>" <?php selected($config['price_source'], $key); ?>><?= esc_html($label) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description">Base del precio normal en la tienda (sin precio rebajado). El precio del cliente se guarda siempre en la meta <code>_hsy_cost</code>.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="hsy-factor">Multiplicador de precio</label></th>
        <td>
            <input type="number" id="hsy-factor" name="hsy[price_factor]" value="<?= esc_attr($config['price_factor']) ?>" step="0.0001" min="0.0001" class="small-text">
            <p class="description">Se aplica al precio de venta elegido. 1 = igual; 1.3 = +30 % (útil con el precio del cliente).</p>
        </td>
    </tr>
</table>
