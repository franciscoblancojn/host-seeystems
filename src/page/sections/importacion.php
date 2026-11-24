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
                <li><strong>Características</strong> (Género, Correa, Caja, Tamaño, Resistencia al agua, Referencia) → atributos visibles. También como descripción corta provisional, hasta que se genere la definitiva (ver <strong>Descripciones</strong>).</li>
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

<h2>Descripciones</h2>
<?php
$hsy_connections = HSY_CONTENT::ai_connections();
$hsy_preview_products = get_posts([
    'post_type' => 'product',
    'post_status' => 'any',
    'meta_key' => HSY_IMPORTED_META,
    'meta_value' => 'yes',
    'posts_per_page' => 200,
    'orderby' => 'title',
    'order' => 'ASC',
    'no_found_rows' => true,
]);
?>
<table class="form-table" role="presentation">
    <tr>
        <th scope="row">Qué se completa</th>
        <td>
            <ul class="hsy-help">
                <li>Productos importados <strong>sin descripción</strong>, o cuya <strong>descripción corta</strong> sigue siendo la lista automática de características.</li>
                <li>Lo escrito a mano o generado antes <strong>nunca se reemplaza</strong>.</li>
                <li>Se genera justo después de importar (si está marcado abajo) o con el botón «Generar descripciones pendientes» de la pestaña Productos, de a un producto.</li>
            </ul>
        </td>
    </tr>
    <tr>
        <th scope="row">Generar con IA</th>
        <td>
            <?php if (!HSY_CONTENT::ai_available()) : ?>
                <p class="fwue-message hsy-message error">Necesita el plugin <strong>IA Conector</strong> activo, con al menos una conexión (Gemini, Groq…).</p>
            <?php elseif (!$hsy_connections) : ?>
                <p class="fwue-message hsy-message error">IA Conector está activo pero no tiene conexiones activas: crea una en su página de configuración.</p>
            <?php endif; ?>
            <label><input type="checkbox" name="hsy[ai_enabled]" value="1" <?php checked($config['ai_enabled'], 1); ?> <?= HSY_CONTENT::ai_available() ? '' : 'disabled' ?>> Generar la descripción y la descripción corta con IA</label>
            <?php
            // Orden: primero las elegidas (en su prioridad), después el resto de conexiones de IA Conector.
            $hsy_order = $config['ai_connections'];
            foreach (array_keys($hsy_connections) as $key) {
                if (!in_array($key, $hsy_order, true)) {
                    $hsy_order[] = $key;
                }
            }
            $hsy_paused = HSY_CONTENT::paused_connections($hsy_order);
            ?>
            <p><strong>Conexiones, en orden de prioridad</strong></p>
            <ol class="hsy-connections" id="hsy-connections">
                <?php foreach ($hsy_order as $key) : ?>
                    <li class="hsy-connection" data-key="<?= esc_attr($key) ?>">
                        <label>
                            <input type="checkbox" name="hsy[ai_connections][]" value="<?= esc_attr($key) ?>" <?php checked(in_array($key, $config['ai_connections'], true)); ?>>
                            <?= esc_html($hsy_connections[$key] ?? $key . ' (no disponible en IA Conector)') ?>
                        </label>
                        <?php if (isset($hsy_paused[$key])) : ?>
                            <span class="hsy-badge hsy-badge--pending">En pausa por límite de uso (~<?= (int) ceil($hsy_paused[$key] / 60) ?> min)</span>
                            <button type="button" class="button-link hsy-ai-resume" data-key="<?= esc_attr($key) ?>">Reanudar</button>
                        <?php endif; ?>
                        <span class="hsy-connection__move">
                            <button type="button" class="button button-small hsy-move" data-dir="up" aria-label="Subir">↑</button>
                            <button type="button" class="button button-small hsy-move" data-dir="down" aria-label="Bajar">↓</button>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ol>
            <ul class="hsy-help">
                <li>Marca las conexiones que se pueden usar y ordénalas con ↑ ↓. Se usa la primera; si falla (tokens o límite de uso agotados, modelo inexistente, sin conexión…) se prueba la siguiente, y así hasta que una responda.</li>
                <li>Si el error es de <strong>cuota o límite de uso</strong>, esa conexión queda <strong>en pausa <?= (int) (HSY_AI_COOLDOWN / 60) ?> minutos</strong> para no gastar una llamada fallida en cada producto; después se vuelve a probar sola. «Reanudar» la quita de la pausa ya.</li>
                <li>En el producto queda qué conexión respondió (meta <code>_hsy_content_ai</code>) y en el log, los saltos.</li>
            </ul>
            <p class="description">Las dos descripciones se generan con una llamada cada una, con los prompts de abajo.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Prompts</th>
        <td>
            <details class="hsy-vars">
                <summary>Variables disponibles</summary>
                <table class="widefat striped">
                    <tbody>
                        <?php foreach (HSY_CONTENT::VARIABLES as $key => $label) : ?>
                            <tr>
                                <td><code class="hsy-var" title="Clic para insertar">{<?= esc_html($key) ?>}</code></td>
                                <td><?= esc_html($label) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="description">Clic en una variable para insertarla en el último prompt donde estabas escribiendo. Las variables vacías quedan como texto vacío.</p>
            </details>

            <p><label for="hsy-prompt-description"><strong>Prompt de la descripción</strong></label></p>
            <textarea id="hsy-prompt-description" name="hsy[ai_prompt_description]" rows="14" class="large-text code hsy-prompt-input"
                data-default="<?= esc_attr(HSY_CONTENT::DEFAULT_PROMPT_DESCRIPTION) ?>"><?= esc_textarea($config['ai_prompt_description']) ?></textarea>
            <p><button type="button" class="button-link hsy-prompt-reset" data-target="#hsy-prompt-description">Restaurar el prompt por defecto</button></p>

            <p><label for="hsy-prompt-short"><strong>Prompt de la descripción corta</strong></label></p>
            <textarea id="hsy-prompt-short" name="hsy[ai_prompt_short]" rows="10" class="large-text code hsy-prompt-input"
                data-default="<?= esc_attr(HSY_CONTENT::DEFAULT_PROMPT_SHORT) ?>"><?= esc_textarea($config['ai_prompt_short']) ?></textarea>
            <p><button type="button" class="button-link hsy-prompt-reset" data-target="#hsy-prompt-short">Restaurar el prompt por defecto</button></p>

            <p class="description">La IA debe responder solo con el HTML del campo (se permiten &lt;p&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;ul&gt;/&lt;ol&gt;/&lt;li&gt;, &lt;h3&gt;/&lt;h4&gt;, &lt;br&gt;; el resto se quita). La corta se genera después de la descripción, así que <code>{descripcion}</code> trae la recién generada. Un prompt vacío vuelve al de por defecto.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="hsy-preview-product">Probar los prompts</label></th>
        <td>
            <select id="hsy-preview-product">
                <option value="">— Producto importado —</option>
                <?php foreach ($hsy_preview_products as $hsy_p) : ?>
                    <option value="<?= (int) $hsy_p->ID ?>"><?= esc_html($hsy_p->post_title . ' (#' . $hsy_p->ID . ')') ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="button" id="hsy-preview">Probar</button>
            <span class="spinner" id="hsy-preview-spinner"></span>
            <p class="description">Genera con los prompts tal como están en el formulario (sin guardar) y con la conexión guardada. <strong>No cambia el producto.</strong></p>
            <div id="hsy-preview-message"></div>
            <div class="hsy-preview" id="hsy-preview-result" hidden></div>
        </td>
    </tr>
    <tr>
        <th scope="row">Después de importar</th>
        <td><label><input type="checkbox" name="hsy[content_after_import]" value="1" <?php checked($config['content_after_import'], 1); ?>> Generar las descripciones pendientes de los productos recién importados</label></td>
    </tr>
    <tr>
        <th scope="row">Buscar en Google</th>
        <td>
            <label><input type="checkbox" name="hsy[describe]" value="1" <?php checked($config['describe'], 1); ?>> Buscar el producto en Google</label>
            <ul class="hsy-help">
                <li>Busca «marca nombre referencia» y solo usa una página o resultado que contenga <strong>la referencia exacta</strong> (para no traer la de otro modelo).</li>
                <li><strong>Con IA:</strong> lo encontrado se le pasa como contexto, para que la descripción tenga datos reales del modelo.</li>
                <li><strong>Sin IA:</strong> lo encontrado se usa tal cual como descripción (la descripción corta queda con la lista de características).</li>
                <li>La URL de origen queda en <code>_hsy_description_source</code>.</li>
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
