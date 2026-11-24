<?php

// Variables disponibles: $config (HSY_USE_DATA_CONFIG::get()). Las claves secretas nunca se imprimen.

?>
<input type="hidden" name="hsy[import_tab]" value="1">

<h2>Productos</h2>
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
        <th scope="row"><label for="hsy-name-template">Nombre del producto</label></th>
        <td>
            <input type="text" id="hsy-name-template" name="hsy[name_template]" value="<?= esc_attr($config['name_template']) ?>" class="regular-text code">
            <p class="description">Plantilla con <code>{nombre}</code> (obligatorio), <code>{genero}</code>, <code>{marca}</code> y <code>{ref}</code>. Por defecto <code>{nombre} {genero} {marca} {ref}</code> → «LEILA SET DAMA Armani Exchange AX7186SET».</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="hsy-category">Categoría de los nuevos</label></th>
        <td>
            <input type="text" id="hsy-category" name="hsy[category]" value="<?= esc_attr($config['category']) ?>" class="regular-text">
            <p class="description">Opcional (p. ej. «Relojes»; «Padre &gt; Hijo» para una subcategoría). Se crea si no existe. Vacío = sin categoría.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Qué se importa</th>
        <td>
            <ul class="hsy-help">
                <li><strong>Marca</strong> → marca del producto (<code>product_brand</code>). Si no existe se crea en formato normal: «ARMANI EXCHANGE» → «Armani Exchange».</li>
                <li><strong>Nombre</strong> → nombre del producto según la plantilla (nombre, género, marca y referencia). <strong>Referencia</strong> → SKU.</li>
                <li><strong>Características</strong> (Género, Correa, Caja, Tamaño, Resistencia al agua, Referencia) → atributos visibles y descripción corta.</li>
                <li><strong>PVP sugerido</strong> → precio normal. El precio rebajado no se toca.</li>
                <li><strong>Precio mayorista</strong> → meta <code><?= esc_html(HSY_WHOLESALE_META) ?></code>, se actualiza en cada importación y se ve en el editor del producto, junto a los precios.</li>
                <li>Todos los productos importados llevan la meta <code><?= esc_html(HSY_IMPORTED_META) ?> = yes</code>.</li>
            </ul>
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
            <p class="description">Qué se actualiza en un producto existente (se reconoce por su id de origen o por el SKU). Los nuevos se crean con todo. El precio mayorista se actualiza siempre.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Imágenes</th>
        <td><label><input type="checkbox" name="hsy[import_images]" value="1" <?php checked($config['import_images'], 1); ?>> Descargar imágenes (la primera es la destacada; las ya descargadas se reutilizan)</label></td>
    </tr>
</table>

<h2>Stock mínimo</h2>
<table class="form-table" role="presentation">
    <tr>
        <th scope="row"><label for="hsy-min-stock">Stock mínimo</label></th>
        <td>
            <input type="number" id="hsy-min-stock" name="hsy[min_stock]" value="<?= esc_attr($config['min_stock']) ?>" min="0" step="1" class="small-text"> unidades
            <ul class="hsy-help">
                <li><strong>Al importar:</strong> el stock del catálogo (menos lo reservado) se compara con este mínimo. Si el producto tiene <strong><?= (int) $config['min_stock'] ?> unidades o menos</strong>, se importa con stock <strong>0</strong> (agotado); si tiene más, con su cantidad. Ejemplo con mínimo 3: 3 → 0, 4 → 4.</li>
                <li><strong>Después:</strong> si el stock de un producto importado baja hasta el mínimo o por debajo (por una venta, una edición manual o la API), se pone automáticamente en <strong>0</strong>. Queda anotado en el log.</li>
                <li>Solo afecta a los productos importados (meta <code><?= esc_html(HSY_IMPORTED_META) ?></code>). Los demás productos de la tienda no cambian.</li>
                <li>Los productos marcados «SIN STOCK» en el catálogo se importan siempre con stock 0.</li>
                <li>0 = sin mínimo: se importa la cantidad tal cual.</li>
            </ul>
        </td>
    </tr>
</table>

<h2>Descripción desde Google</h2>
<table class="form-table" role="presentation">
    <tr>
        <th scope="row">Buscar descripción</th>
        <td>
            <label><input type="checkbox" name="hsy[describe]" value="1" <?php checked($config['describe'], 1); ?>> Si el producto no tiene descripción, buscarla en Google al importar</label>
            <ul class="hsy-help">
                <li>Busca «marca nombre referencia», abre los primeros resultados y solo usa una página que contenga <strong>la referencia exacta</strong> (para no traer la de otro modelo).</li>
                <li>Toma la descripción del producto de esa página (datos estructurados o meta descripción) y guarda la URL de origen en <code>_hsy_description_source</code>.</li>
                <li>Nunca reemplaza una descripción que ya exista. Si no encuentra nada, el producto se importa igual y se avisa.</li>
            </ul>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="hsy-serper-key">API key de Serper.dev</label></th>
        <td>
            <input type="password" id="hsy-serper-key" name="hsy[serper_key]" value="" class="regular-text" autocomplete="new-password"
                placeholder="<?= $config['serper_key'] ? esc_attr('•••••••• (guardada)') : '' ?>">
            <?php if ($config['serper_key']) : ?>
                <label><input type="checkbox" name="hsy[serper_key_clear]" value="1"> Borrar</label>
            <?php endif; ?>
            <p class="description">Resultados de Google por API (<a href="https://serper.dev" target="_blank" rel="noopener">serper.dev</a>). La API de búsqueda de Google ya no admite clientes nuevos y se apaga el 1 de enero de 2027. Vacío = conservar la guardada.</p>
        </td>
    </tr>
</table>
