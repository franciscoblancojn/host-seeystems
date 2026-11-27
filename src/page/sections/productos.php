<?php

// Variables disponibles: $config. La tabla la pinta src/js/admin.js con los datos de AJAX.
$catalog = new HSY_USE_DATA_CATALOG();

?>
<?php if (!HSY_USE_DATA_CONFIG::is_connected_config($config)) : ?>
    <p class="fwue-message hsy-message error">Completa la URL, el correo y la contraseña en la pestaña <strong>Conexión</strong>.</p>
<?php else : ?>
    <div class="hsy-stats" id="hsy-stats">
        <?php foreach ([
            'total' => 'Total productos',
            'imported' => 'Importados',
            'not_imported' => 'Pendientes de importar',
            'with_content' => 'Con contenido',
            'without_content' => 'Sin contenido',
            'draft' => 'Borrador',
        ] as $hsy_key => $hsy_label) : ?>
            <div class="hsy-stat hsy-stat--<?= esc_attr($hsy_key) ?>">
                <span class="hsy-stat__value" data-stat="<?= esc_attr($hsy_key) ?>">…</span>
                <span class="hsy-stat__label"><?= esc_html($hsy_label) ?></span>
            </div>
        <?php endforeach; ?>
        <div class="hsy-stat hsy-stat--action">
            <button type="button" class="button button-primary" id="hsy-publish" disabled
                title="Publica los productos importados en borrador con contenido completo: descripción, descripción corta y categorías">Publicar productos con contenido (<span data-stat="publishable">0</span>)</button>
        </div>
    </div>
    <details class="hsy-csv">
        <summary><strong>Contenido por CSV</strong> <span class="hsy-muted">— exportar los productos, redactar los textos fuera del sitio e importarlos</span></summary>
        <div class="hsy-csv__body">
            <div class="hsy-csv__step">
                <h4>1. Exportar</h4>
                <p>
                    <label><input type="checkbox" id="hsy-csv-missing" checked> Solo productos con contenido faltante</label>
                </p>
                <p>
                    <a class="button" id="hsy-csv-export"
                        data-base="<?= esc_url(wp_nonce_url(admin_url('admin-post.php?action=' . HSY_API_CSV::EXPORT), HSY_API_CSV::EXPORT)) ?>"
                        href="<?= esc_url(wp_nonce_url(admin_url('admin-post.php?action=' . HSY_API_CSV::EXPORT . '&missing=1'), HSY_API_CSV::EXPORT)) ?>">Exportar CSV</a>
                </p>
                <p class="description">Una fila por producto importado con sus datos (<code>contexto</code>, <code>tipo_producto</code>, <code>falta</code>…) y columnas vacías para rellenar:
                    <code>intro</code>, <code>beneficios</code> (uno por línea o separados por <code>||</code>), <code>caracteristicas</code> y <code>descripcion_corta</code>.</p>
            </div>
            <div class="hsy-csv__step">
                <h4>2. Importar</h4>
                <p>
                    <input type="file" id="hsy-csv-file" accept=".csv,text/csv">
                    <select id="hsy-csv-mode">
                        <option value="missing">Solo rellenar lo que falta</option>
                        <option value="replace">Reemplazar aunque ya tenga</option>
                    </select>
                    <button type="button" class="button" id="hsy-csv-preview">Vista previa</button>
                    <span class="spinner" id="hsy-csv-spinner"></span>
                </p>
                <div id="hsy-csv-message"></div>
                <div id="hsy-csv-summary" hidden></div>
                <p><button type="button" class="button button-primary" id="hsy-csv-apply" hidden>Aplicar</button></p>
                <p class="description">La descripción se arma con la misma estructura fija que la IA (párrafo, beneficios, características, tabla de especificaciones y cierre). Nada se escribe hasta pulsar «Aplicar».</p>
            </div>
        </div>
    </details>

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
            <option value="incomplete">Con contenido faltante</option>
        </select>
        <label class="hsy-auto-content" title="Activada: al importar, la IA genera descripciones, estilo y lo que falte en paralelo. Desactivada: solo importa y los productos quedan «Pendiente de generar con IA».">
            <input type="checkbox" id="hsy-auto-content" checked> Generar contenido al importar
        </label>
        <button type="button" class="button" id="hsy-import-selected" disabled>Importar seleccionados (<span id="hsy-selected-count">0</span>)</button>
        <button type="button" class="button" id="hsy-regenerate-selected" disabled title="Vuelve a generar con IA la descripción y la descripción corta de los productos ya importados seleccionados">Regenerar descripciones (<span id="hsy-regenerate-count">0</span>)</button>
        <button type="button" class="button-link" id="hsy-clear-selection" hidden>Quitar selección</button>
        <button type="button" class="button" id="hsy-import-all" title="Importa los productos de la página actual (100 por página)">Importar esta página (<span id="hsy-page-count">0</span>)</button>
        <span class="hsy-muted"><span id="hsy-total">0</span> productos</span>
    </div>
    <div class="hsy-content-bar" hidden>
        <button type="button" class="button" id="hsy-content-pending" title="Completa solo lo que le falta a cada producto importado: categorías (por género), estilo, descripción y/o descripción corta">Generar descripciones y categorías faltantes (<span id="hsy-pending-count">0</span>)</button>
        <span class="hsy-muted">Productos importados sin descripción o con la descripción corta automática. Se configura en <strong>Importación → Descripciones</strong>.</span>
    </div>
    <div class="hsy-progress" id="hsy-progress" hidden>
        <div class="hsy-progress__bar"><span></span></div>
        <span class="hsy-progress__text"></span>
    </div>
    <div class="hsy-progress hsy-progress--ai" id="hsy-progress-ai" hidden>
        <div class="hsy-progress__bar"><span></span></div>
        <span class="hsy-progress__text"></span>
    </div>

    <div class="hsy-pager" hidden></div>
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
                <th class="hsy-col-text">Categorías</th>
                <th>Estado</th>
                <th class="hsy-col-action"></th>
            </tr>
        </thead>
        <tbody id="hsy-rows"></tbody>
    </table>
    <div class="hsy-pager" hidden></div>
<?php endif; ?>
