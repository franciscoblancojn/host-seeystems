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
            <p class="description">Plantilla con <code>{nombre}</code> (obligatorio), <code>{tipo}</code>, <code>{genero}</code>, <code>{marca}</code> y <code>{ref}</code>. P. ej. <code>{tipo} {marca} {nombre} {genero} {ref}</code> → «Reloj Armani Exchange LEILA SET DAMA AX7186SET» y, en una joya, «Michael Kors Joyas Aretes DAMA MKJ8655040».</p>
            <p>
                <label><code>{tipo}</code> de los relojes <input type="text" name="hsy[type_watch]" value="<?= esc_attr($config['type_watch']) ?>" class="small-text" style="width:10em"></label>
                &nbsp; <label>de las joyas <input type="text" name="hsy[type_jewelry]" value="<?= esc_attr($config['type_jewelry']) ?>" class="small-text" style="width:10em" placeholder="(vacío)"></label>
            </p>
            <p class="description"><strong>Sin repetidos:</strong> el texto fijo de la plantilla y el <code>{tipo}</code> se quitan si esa palabra ya está en los datos del producto (sin mayúsculas, acentos ni plural). Así «Reloj» no se repite si el nombre ya dice «Reloj…», y en las joyas, cuyo nombre ya es el tipo («Aretes», «Brazalete»), el tipo va vacío. Si escribes «Reloj» como texto fijo, en las joyas se cambia por su <code>{tipo}</code> igual que si usaras la variable.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Qué se importa</th>
        <td>
            <ul class="hsy-help">
                <li><strong>Marca</strong> → marca del producto (<code>product_brand</code>): la marca equivalente de la tienda (ver «Marcas del catálogo»); si no hay, se crea en formato normal: «ARMANI EXCHANGE» → «Armani Exchange».</li>
                <li><strong>Nombre</strong> → nombre del producto según la plantilla (nombre, género, marca y referencia). <strong>Referencia</strong> → SKU.</li>
                <li><strong>Características</strong> (Género, Correa, Caja, Tamaño, Resistencia al agua, Referencia) → atributos visibles. También como descripción corta provisional, hasta que se genere la definitiva (ver <strong>Descripciones</strong>).</li>
                <li><strong>Precio</strong> → el mayor entre mayorista × 2 y PVP sugerido, con utilidad mínima (ver <strong>Precio y descuentos</strong>). El precio rebajado lo pone el plugin de descuentos.</li>
                <li><strong>Precio mayorista</strong> → meta <code><?= esc_html(HSY_WHOLESALE_META) ?></code>, se actualiza en cada importación y se ve en el editor del producto, junto a los precios.</li>
                <li>Todos los productos importados llevan la meta <code><?= esc_html(HSY_IMPORTED_META) ?> = yes</code>.</li>
            </ul>
        </td>
    </tr>
    <tr>
        <th scope="row">Marcas del catálogo</th>
        <td>
            <?php $hsy_brands = HSY_BRANDS::catalog_report(); ?>
            <?php if (!HSY_BRANDS::available()) : ?>
                <p class="fwue-message hsy-message error">La tienda no tiene la taxonomía de marcas (<code>product_brand</code>): los productos se importan sin marca.</p>
            <?php elseif (!$hsy_brands) : ?>
                <p class="description">Carga el catálogo (pestaña Productos) para ver sus marcas.</p>
            <?php else : ?>
                <table class="widefat striped hsy-brands">
                    <thead>
                        <tr><th>Marca en el catálogo</th><th>Productos</th><th>Marca en la tienda</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hsy_brands as $hsy_b) : ?>
                            <tr>
                                <td><?= esc_html($hsy_b['brand']) ?></td>
                                <td><?= (int) $hsy_b['products'] ?></td>
                                <td>
                                    <?php if (!$hsy_b['term']) : ?>
                                        <span class="hsy-badge hsy-badge--new">Se creará «<?= esc_html($hsy_b['brand']) ?>»</span>
                                    <?php else : ?>
                                        <a href="<?= esc_url(get_edit_term_link($hsy_b['term']->term_id, HSY_BRANDS::TAXONOMY, 'product')) ?>" target="_blank"><?= esc_html($hsy_b['term']->name) ?></a>
                                        <span class="hsy-muted">(#<?= (int) $hsy_b['term']->term_id ?>, <?= esc_html($hsy_b['term']->slug) ?>, <?= (int) $hsy_b['term']->count ?> productos)</span>
                                        <?php if (!$hsy_b['exact']) : ?>
                                            <span class="hsy-badge hsy-badge--similar" title="El nombre no es idéntico, pero es la misma marca sin contar mayúsculas, acentos, espacios, signos ni palabras como «reloj».">Equivalente</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="description">Cada marca del catálogo se asigna a la marca de la tienda equivalente: se ignoran mayúsculas, acentos, espacios, signos y palabras como «reloj», «relojes» o «relog»; otras palabras sí cuentan («Michael Kors Joyas» es distinta de «Michael Kors»). Primero se busca por nombre y después por slug; si hay varias, la que tiene más productos. Si no hay ninguna, se crea al importar.</p>
            <?php endif; ?>
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

<h2>Categorías</h2>
<?php
$hsy_tree = HSY_CATEGORIES::tree();
$hsy_map = HSY_CATEGORIES::map($config);
$hsy_default = empty(array_filter((array) $config['category_map']));
// Géneros: los del catálogo guardado + los habituales.
$hsy_genders = ['DAMA' => 0, 'HOMBRE' => 0, 'UNISEX' => 0];
foreach ((new HSY_USE_DATA_CATALOG())->items() as $hsy_it) {
    $hsy_g = HSY_CATEGORIES::gender_key($hsy_it['attributes']['Género'] ?? '');
    if ('' !== $hsy_g) {
        $hsy_genders[$hsy_g] = ($hsy_genders[$hsy_g] ?? 0) + 1;
    }
}
?>
<table class="form-table" role="presentation">
    <tr>
        <th scope="row">Por género</th>
        <td>
            <?php if (!$hsy_tree) : ?>
                <p class="fwue-message hsy-message error">La tienda no tiene categorías de producto.</p>
            <?php else : ?>
                <?php if ($hsy_default) : ?>
                    <p class="fwue-message hsy-message info">Sugerencia automática (por nombre de las categorías). Revísala y guarda para fijarla.</p>
                <?php endif; ?>
                <div class="hsy-catmap">
                    <?php foreach ($hsy_genders as $hsy_g => $hsy_n) : ?>
                        <fieldset class="hsy-catmap__gender">
                            <legend><strong><?= esc_html($hsy_g) ?></strong> <span class="hsy-muted">(<?= (int) $hsy_n ?> en el catálogo)</span></legend>
                            <input type="hidden" name="hsy[category_map][<?= esc_attr($hsy_g) ?>][]" value="0">
                            <div class="hsy-catmap__list">
                                <?php foreach ($hsy_tree as $hsy_node) : $hsy_t = $hsy_node['term']; if (HSY_CATEGORIES::is_offer($hsy_t)) continue; ?>
                                    <label style="padding-left: <?= (int) $hsy_node['depth'] * 16 ?>px">
                                        <input type="checkbox" name="hsy[category_map][<?= esc_attr($hsy_g) ?>][]" value="<?= (int) $hsy_t->term_id ?>" <?php checked(in_array((int) $hsy_t->term_id, array_map('intval', (array) ($hsy_map[$hsy_g] ?? [])), true)); ?>>
                                        <?= esc_html($hsy_t->name) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <ul class="hsy-help">
                <li>Marca las categorías de cada género del catálogo (atributo «Género»). Como en la tienda, conviene marcar la principal y la del género: <strong>Relojes + Mujer</strong>; para unisex, <strong>Relojes + Hombre + Mujer</strong>.</li>
                <li>El <strong>estilo</strong> (Análogo, Digital, Deportivo…, las subcategorías de lo marcado) lo elige la IA: ver <strong>Descripciones → Estilo</strong>.</li>
                <li>Se asignan <strong>solo si el producto no tiene categorías</strong> (al crearlo, o al reimportar uno que quedó sin categoría o en «Sin categorizar»). Si ya tiene, a mano o por el plugin, <strong>no se vuelven a tocar</strong>. Las categorías de ofertas no se muestran.</li>
            </ul>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="hsy-jewelry-words">Joyas</label></th>
        <td>
            <p><label>Marcas de joyas: si la marca contiene <input type="text" id="hsy-jewelry-words" name="hsy[jewelry_words]" value="<?= esc_attr($config['jewelry_words']) ?>" class="regular-text"></label></p>
            <p><label>van a la categoría <input type="text" name="hsy[jewelry_category]" value="<?= esc_attr($config['jewelry_category']) ?>" class="regular-text"></label> → Mujer / Hombre según el género.</p>
            <p class="description">Palabras separadas por coma, sin distinguir mayúsculas ni acentos (p. ej. «Michael Kors Joyas» contiene «joyas»). La categoría y sus subcategorías Mujer / Hombre se crean al importar la primera joya si no existen. Las joyas no llevan las categorías por género de arriba.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="hsy-category">Sin regla</label></th>
        <td>
            <input type="text" id="hsy-category" name="hsy[category]" value="<?= esc_attr($config['category']) ?>" class="regular-text">
            <p class="description">Opcional: categoría para los productos cuyo género no tiene ninguna marcada («Padre &gt; Hijo» para una subcategoría; se crea si no existe). Vacío = sin categoría y con aviso.</p>
        </td>
    </tr>
</table>

<h2>Precio y descuentos</h2>
<?php
$hsy_tiers = HSY_PRICING::tiers($config);
usort($hsy_tiers, function ($a, $b) {
    return (float) $a['min'] - (float) $b['min'];
});
$hsy_tiers[] = ['min' => '', 'slug' => '', 'percent' => '']; // fila vacía para añadir un tramo
$hsy_cat_tree = HSY_CATEGORIES::tree();
?>
<table class="form-table" role="presentation">
    <tr>
        <th scope="row">Cómo se calcula</th>
        <td>
            <ol class="hsy-help">
                <li>Con el <strong>precio mayorista</strong> y el <strong>PVP sugerido</strong> del catálogo: <strong>margen aprox.</strong> = (1 − mayorista ÷ PVP) × 100, redondeado (lo mismo que muestra el catálogo).</li>
                <li><strong>Precio del producto</strong> = el mayor entre <strong>mayorista × 2</strong> y el <strong>PVP sugerido</strong>.</li>
                <li><strong>Categoría de descuento</strong> según el margen (tabla de abajo). Otro plugin aplica el descuento de esa categoría; este solo la asigna y quita las de otros tramos. Es independiente de las categorías de género / estilo.</li>
                <li><strong>Utilidad mínima</strong>: si precio × (1 − descuento general − descuento de su categoría) es menor o igual a mayorista × (1 + utilidad), el precio sube lo justo para quedar por encima (redondeado hacia arriba).</li>
                <li>Se guardan en el producto: precio mayorista, PVP sugerido, margen aprox., mayorista × 2 (se ven en el editor, debajo del precio). El precio rebajado no se toca: lo pone el plugin de descuentos.</li>
            </ol>
            <p class="description">Se aplica al importar y con el botón «Actualizar precios y categorías de descuento» de la pestaña Productos (solo precios, sin IA).</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="hsy-profit-margin">Utilidad mínima</label></th>
        <td><input type="number" id="hsy-profit-margin" name="hsy[profit_margin]" value="<?= esc_attr($config['profit_margin']) ?>" min="0" step="0.5" class="small-text"> % sobre el precio mayorista</td>
    </tr>
    <tr>
        <th scope="row"><label for="hsy-global-discount">Descuento general</label></th>
        <td>
            <input type="number" id="hsy-global-discount" name="hsy[global_discount]" value="<?= esc_attr($config['global_discount']) ?>" min="0" max="95" step="0.5" class="small-text"> %
            <select name="hsy[discount_combine]">
                <option value="sum" <?php selected($config['discount_combine'], 'sum'); ?>>se suma al de la categoría (27 % + 5 % = 32 %)</option>
                <option value="chain" <?php selected($config['discount_combine'], 'chain'); ?>>se encadena con el de la categoría (1 − 0,73 × 0,95)</option>
            </select>
            <p class="description">El que otro plugin aplica a todos los productos. Solo se usa para comprobar la utilidad mínima.</p>
        </td>
    </tr>
    <tr>
        <th scope="row">Categorías de descuento por margen</th>
        <td>
            <table class="widefat striped hsy-tiers">
                <thead><tr><th>Margen desde (%)</th><th>Categoría</th><th>Descuento que aplica (%)</th></tr></thead>
                <tbody>
                    <?php foreach ($hsy_tiers as $hsy_i => $hsy_t) : ?>
                        <tr>
                            <td><input type="number" name="hsy[discount_tiers][<?= (int) $hsy_i ?>][min]" value="<?= esc_attr($hsy_t['min']) ?>" min="0" max="100" step="1" class="small-text"></td>
                            <td>
                                <select name="hsy[discount_tiers][<?= (int) $hsy_i ?>][slug]">
                                    <option value="">— Sin tramo —</option>
                                    <?php foreach ($hsy_cat_tree as $hsy_node) : $hsy_ct = $hsy_node['term']; ?>
                                        <option value="<?= esc_attr($hsy_ct->slug) ?>" <?php selected($hsy_t['slug'], $hsy_ct->slug); ?>><?= esc_html(str_repeat('— ', $hsy_node['depth']) . $hsy_ct->name . ' (' . $hsy_ct->slug . ')') ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ('' !== $hsy_t['slug'] && !get_term_by('slug', $hsy_t['slug'], 'product_cat')) : ?>
                                    <span class="hsy-error">No existe la categoría «<?= esc_html($hsy_t['slug']) ?>».</span>
                                <?php endif; ?>
                            </td>
                            <td><input type="number" name="hsy[discount_tiers][<?= (int) $hsy_i ?>][percent]" value="<?= esc_attr($hsy_t['percent']) ?>" min="0" max="95" step="0.5" class="small-text"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="description">Cada producto va al tramo de mayor «margen desde» que alcance; con menos margen que el primero, a ninguno. Por defecto: 50 % → Ofertas 5 %, 60 % → Ofertas 10 %, 70 % → Ofertas 15 % (49 % o menos, ninguna). Deja la categoría vacía para quitar un tramo; la última fila sirve para añadir uno.</p>
        </td>
    </tr>
    <?php if (HSY_PRICING::dpv_active()) : ?>
    <tr>
        <th scope="row">Descuentos automáticos</th>
        <td>
            <input type="hidden" name="hsy[reactivate_discounts_shown]" value="1">
            <label><input type="checkbox" name="hsy[reactivate_discounts]" value="1" <?php checked($config['reactivate_discounts'], 1); ?>> Aplicar descuentos automáticos de <strong>Descuentos por Volumen</strong> al importar o actualizar</label>
            <p class="description">Activado: en cada importación o actualización (también «Actualizar precios y categorías de descuento») el producto queda con el interruptor «Aplicar descuentos automáticos» encendido, y ese plugin calcula su precio rebajado (descuento general + el de la categoría de oferta). Si alguien lo había pasado a manual, se reactiva. Desactivado: no se toca ese interruptor.</p>
        </td>
    </tr>
    <?php endif; ?>
    <tr>
        <th scope="row"><label for="hsy-price-round">Redondeo</label></th>
        <td><input type="number" id="hsy-price-round" name="hsy[price_round]" value="<?= esc_attr($config['price_round']) ?>" min="1" step="1" class="small-text"> — cuando el precio se sube por utilidad mínima, se redondea hacia arriba a este múltiplo.</td>
    </tr>
</table>

<h2>Dimensiones y peso por defecto</h2>
<?php $hsy_dim = get_option('woocommerce_dimension_unit', 'cm'); $hsy_wu = get_option('woocommerce_weight_unit', 'kg'); ?>
<table class="form-table" role="presentation">
    <tr>
        <th scope="row">Envío</th>
        <td>
            <label>Largo <input type="number" name="hsy[default_length]" value="<?= esc_attr($config['default_length']) ?>" min="0" step="0.01" class="small-text"></label> ×
            <label>ancho <input type="number" name="hsy[default_width]" value="<?= esc_attr($config['default_width']) ?>" min="0" step="0.01" class="small-text"></label> ×
            <label>alto <input type="number" name="hsy[default_height]" value="<?= esc_attr($config['default_height']) ?>" min="0" step="0.01" class="small-text"></label> <?= esc_html($hsy_dim) ?>
            &nbsp;·&nbsp; <label>peso <input type="number" name="hsy[default_weight]" value="<?= esc_attr($config['default_weight']) ?>" min="0" step="0.01" class="small-text"></label> <?= esc_html($hsy_wu) ?>
            <ul class="hsy-help">
                <li>Cada vez que se guarda un producto importado (al importar, actualizar, recalcular precios, generar descripciones, importar el CSV o editarlo a mano) se rellenan <strong>solo los datos vacíos</strong>. Ejemplo: si tiene ancho pero no alto, solo se pone el alto.</li>
                <li>En las unidades de la tienda (WooCommerce → Ajustes → Productos: <?= esc_html($hsy_dim) ?> y <?= esc_html($hsy_wu) ?>). Deja un campo vacío para no rellenar ese dato.</li>
                <li>Para completarlo en los productos ya importados sin tocar nada más, usa «Actualizar precios y categorías de descuento» (también los guarda).</li>
            </ul>
        </td>
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
                <li>Se genera al importar si está marcada la casilla «Generar contenido al importar» de la pestaña Productos (activada por defecto), o después con «Generar descripciones y categorías faltantes», de a un producto.</li>
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
            <p>
                <button type="button" class="button" id="hsy-reset-prompts">Restablecer prompts por defecto</button>
                <span class="spinner" id="hsy-reset-spinner"></span>
                <span class="hsy-muted">Vuelve a los prompts de descripción, descripción corta y estilo, y al cierre de la descripción, que trae el plugin. Se guarda al momento.</span>
            </p>
            <div id="hsy-reset-message"></div>
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
            <textarea id="hsy-prompt-description" name="hsy[ai_prompt_description]" rows="14" class="large-text code hsy-prompt-input" autocomplete="off"
                data-default="<?= esc_attr(HSY_CONTENT::DEFAULT_PROMPT_DESCRIPTION) ?>"><?= esc_textarea($config['ai_prompt_description']) ?></textarea>
            <p><button type="button" class="button-link hsy-prompt-reset" data-target="#hsy-prompt-description">Restaurar el prompt por defecto</button></p>
            <p class="description"><strong>Estructura fija:</strong> la IA solo escribe los textos (JSON con <code>intro</code>, <code>beneficios</code> y <code>caracteristicas</code>) y el plugin arma siempre lo mismo: párrafo inicial, «Beneficios principales», «Características del producto», «Especificaciones técnicas» (una tabla con estilos, filas y emojis fijos, con los datos del producto) y el cierre de abajo. Si cambias el prompt, mantén ese JSON en la salida; si devuelve HTML, se usa tal cual (sin la estructura fija).</p>
            <p><label for="hsy-description-closing"><strong>Cierre de la descripción</strong></label></p>
            <input type="text" id="hsy-description-closing" name="hsy[description_closing]" class="large-text" autocomplete="off"
                value="<?= esc_attr('' !== $config['description_closing'] ? $config['description_closing'] : HSY_LAYOUT::DEFAULT_CLOSING) ?>">
            <p class="description">Último párrafo de todas las descripciones. Admite variables (<code>{tienda}</code>, <code>{marca}</code>…) y <code>&lt;strong&gt;</code>. Vacío = el de por defecto.</p>

            <p><label for="hsy-prompt-short"><strong>Prompt de la descripción corta</strong></label></p>
            <textarea id="hsy-prompt-short" name="hsy[ai_prompt_short]" rows="10" class="large-text code hsy-prompt-input" autocomplete="off"
                data-default="<?= esc_attr(HSY_CONTENT::DEFAULT_PROMPT_SHORT) ?>"><?= esc_textarea($config['ai_prompt_short']) ?></textarea>
            <p><button type="button" class="button-link hsy-prompt-reset" data-target="#hsy-prompt-short">Restaurar el prompt por defecto</button></p>

            <p><label><input type="checkbox" name="hsy[ai_style]" value="1" <?php checked($config['ai_style'], 1); ?>> <strong>Estilo:</strong> que la IA elija las subcategorías (Análogo, Digital, Deportivo…) entre las de la categoría del producto</label></p>
            <p><label for="hsy-prompt-style"><strong>Prompt del estilo</strong></label> <span class="hsy-muted">— usa <code>{opciones}</code> con las subcategorías posibles</span></p>
            <textarea id="hsy-prompt-style" name="hsy[ai_prompt_style]" rows="10" class="large-text code hsy-prompt-input" autocomplete="off"
                data-default="<?= esc_attr(HSY_CONTENT::DEFAULT_PROMPT_STYLE) ?>"><?= esc_textarea($config['ai_prompt_style']) ?></textarea>
            <p><button type="button" class="button-link hsy-prompt-reset" data-target="#hsy-prompt-style">Restaurar el prompt por defecto</button></p>

            <p class="description">La IA debe responder solo con el contenido del campo: texto plano (se envuelve en un párrafo) o HTML con &lt;p&gt;, &lt;h2&gt;–&lt;h4&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;i&gt;, &lt;small&gt;, &lt;ul&gt;/&lt;ol&gt;/&lt;li&gt;, &lt;br&gt; y tablas (&lt;table&gt;, &lt;tr&gt;, &lt;th&gt;, &lt;td&gt;, con estilos en línea); el resto se quita. Los prompts por defecto están adaptados de los del plugin de importación desde Drive/Excel. La corta se genera después de la descripción, así que <code>{descripcion}</code> trae la recién generada. Un prompt vacío vuelve al de por defecto.</p>
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
