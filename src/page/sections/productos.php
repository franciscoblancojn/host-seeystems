<?php

// Variables disponibles: $config. La tabla la pinta src/js/admin.js con los datos de AJAX.
$catalog = new HSY_USE_DATA_CATALOG();

?>
<?php if (!HSY_USE_DATA_CONFIG::is_connected_config($config)) : ?>
    <p class="fwue-message hsy-message error">Completa la URL, el correo y la contraseña en la pestaña <strong>Conexión</strong>.</p>
<?php else : ?>
    <div class="hsy-toolbar">
        <button type="button" class="button button-primary" id="hsy-load">Cargar productos del catálogo</button>
        <span class="hsy-muted" id="hsy-fetched"><?= $catalog->fetched_at() ? esc_html('Última lectura: ' . wp_date('Y-m-d H:i', $catalog->fetched_at())) : '' ?></span>
        <span class="spinner" id="hsy-spinner"></span>
    </div>
    <div id="hsy-message"></div>

    <div class="hsy-actions" hidden>
        <input type="search" id="hsy-search" placeholder="Buscar por nombre, referencia, marca o disponibilidad" class="regular-text">
        <select id="hsy-filter">
            <option value="">Todos</option>
            <option value="new">Sin importar</option>
            <option value="imported">Ya importados</option>
        </select>
        <button type="button" class="button" id="hsy-import-selected" disabled>Importar seleccionados (<span id="hsy-selected-count">0</span>)</button>
        <button type="button" class="button" id="hsy-import-all">Importar todos los visibles</button>
        <span class="hsy-muted"><span id="hsy-total">0</span> productos</span>
    </div>
    <div class="hsy-content-bar" hidden>
        <button type="button" class="button" id="hsy-content-pending">Generar descripciones pendientes (<span id="hsy-pending-count">0</span>)</button>
        <span class="hsy-muted">Productos importados sin descripción o con la descripción corta automática. Se configura en <strong>Importación → Descripciones</strong>.</span>
    </div>
    <div class="hsy-progress" id="hsy-progress" hidden>
        <div class="hsy-progress__bar"><span></span></div>
        <span class="hsy-progress__text"></span>
    </div>

    <table class="wp-list-table widefat striped hsy-table" hidden>
        <thead>
            <tr>
                <td class="manage-column check-column"><input type="checkbox" class="hsy-check-all"></td>
                <th class="hsy-col-product">Producto</th>
                <th>Referencia</th>
                <th>PVP sugerido</th>
                <th>Precio mayorista</th>
                <th>Inventario</th>
                <th class="hsy-col-text">Descripción</th>
                <th class="hsy-col-text">Descripción corta</th>
                <th>Estado</th>
                <th class="hsy-col-action"></th>
            </tr>
        </thead>
        <tbody id="hsy-rows"></tbody>
    </table>
<?php endif; ?>
