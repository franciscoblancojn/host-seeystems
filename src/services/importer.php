<?php

/**
 * Crea o actualiza productos de WooCommerce a partir de un producto normalizado del catálogo.
 *
 * Producto normalizado (lo produce HSY_PARSER):
 *   id (string, único en el origen), sku (referencia), name, brand, description, short_description,
 *   pvp (float|null, PVP sugerido = precio normal), wholesale (float|null, precio mayorista),
 *   price_type, availability (etiqueta del origen), stock (int|null, ya sin reservas),
 *   incoming (unidades en tránsito), attributes (nombre => valor), images (URLs absolutas), url.
 *
 * Un producto ya importado se reconoce por la meta HSY_SOURCE_META y, si no la tiene,
 * por el SKU. Es la única clase que escribe productos. Siempre deja HSY_IMPORTED_META = 'yes'
 * y actualiza el precio mayorista (HSY_WHOLESALE_META), aunque no se actualice nada más.
 * La descripción y la descripción corta definitivas las escribe HSY_CONTENT (IA / Google), después.
 */
class HSY_IMPORTER
{
    /**
     * Mapa source_id → product_id de los productos ya importados (una sola consulta).
     */
    public static function imported_map()
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT pm.meta_value AS source_id, pm.post_id
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = %s AND p.post_type = 'product' AND p.post_status <> 'trash'",
            HSY_SOURCE_META
        ));
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->source_id] = (int) $row->post_id;
        }
        return $map;
    }

    /**
     * Importa un producto. Devuelve ['id', 'action' => created|updated, 'warnings'] o WP_Error.
     * Con «actualizar existentes» desactivado, un existente solo actualiza precio mayorista y metas.
     */
    public static function import($item, $config = null)
    {
        $config = $config ?: (new HSY_USE_DATA_CONFIG())->get();
        $item = self::normalize($item);
        if ('' === $item['id'] || '' === $item['name']) {
            return new WP_Error('hsy_item', 'Producto sin id o sin nombre en el catálogo.');
        }

        $product_id = self::find($item);
        $is_new = !$product_id;
        $product = $is_new ? new WC_Product_Simple() : wc_get_product($product_id);
        if (!$product) {
            return new WP_Error('hsy_product', 'No se pudo abrir el producto #' . $product_id . '.');
        }
        if ($is_new) {
            $fields = array_keys(HSY_USE_DATA_CONFIG::UPDATE_FIELDS);
        } else {
            $fields = $config['update_existing'] ? (array) $config['update_fields'] : [];
        }
        $has = function ($field) use ($fields) {
            return in_array($field, $fields, true);
        };
        $warnings = [];

        if ($is_new) {
            $product->set_status($config['status']);
            if ('' !== $item['sku']) {
                // Un SKU repetido lanza excepción: se importa sin SKU y se avisa.
                try {
                    $product->set_sku($item['sku']);
                } catch (WC_Data_Exception $e) {
                    $warnings[] = 'SKU ' . $item['sku'] . ' no asignado: ' . $e->getMessage();
                }
            }
        }
        if ($has('categories')) {
            $category_ids = HSY_CATEGORIES::ids_for($item, $config);
            if ($category_ids) {
                $product->set_category_ids($is_new ? $category_ids : HSY_CATEGORIES::merge_keep_styles($product->get_category_ids(), $category_ids));
            } elseif ($is_new) {
                $warnings[] = 'Sin categoría: el género «' . ($item['attributes']['Género'] ?? '') . '» no tiene regla en Importación → Categorías.';
            }
        }
        if ($has('name')) {
            $product->set_name(self::name($item, $config['name_template']));
        }
        if ($has('price') && null !== $item['pvp']) {
            // PVP sugerido = precio normal. El precio rebajado lo define la tienda: no se toca.
            $product->set_regular_price((string) round($item['pvp'], wc_get_price_decimals()));
        }
        if ($has('stock') && null !== $item['stock']) {
            $product->set_manage_stock(true);
            $product->set_stock_quantity(HSY_STOCK::apply($item['stock'], $config['min_stock']));
        }
        if ($has('attributes')) {
            $product->set_attributes(self::attributes($item['attributes']));
            // La lista de características es la descripción corta provisional: solo se escribe si está
            // vacía o si sigue siendo la automática (lo escrito a mano o por la IA no se toca).
            if (HSY_CONTENT::short_pending($product)) {
                $product->set_short_description($item['short_description']);
                $product->update_meta_data(HSY_SHORT_AUTO_META, 'yes');
            }
        }
        if ($is_new && '' !== trim(wp_strip_all_tags($item['description']))) {
            $product->set_description(wpautop(esc_html(wp_strip_all_tags($item['description']))));
        }

        // Siempre: marca de importado, precio mayorista y datos del origen.
        $product->update_meta_data(HSY_IMPORTED_META, 'yes');
        $product->update_meta_data(HSY_SOURCE_META, $item['id']);
        $product->update_meta_data('_hsy_source_name', $item['name']);
        $product->update_meta_data(HSY_WHOLESALE_META, null !== $item['wholesale'] ? (string) $item['wholesale'] : '');
        $product->update_meta_data('_hsy_pvp', null !== $item['pvp'] ? (string) $item['pvp'] : '');
        $product->update_meta_data('_hsy_availability', $item['availability']);
        $product->update_meta_data('_hsy_stock_source', null !== $item['stock'] ? $item['stock'] : '');
        // Estilo (subcategorías): queda pendiente para la IA si hay opciones y no se clasificó antes.
        if ($has('categories') && 'done' !== $product->get_meta(HSY_STYLE_META)) {
            $product->update_meta_data(HSY_STYLE_META, HSY_CATEGORIES::style_options($product) ? 'pending' : 'none');
        }
        $product->update_meta_data('_hsy_imported_at', time());
        $product_id = $product->save();

        if ($has('attributes') && '' !== $item['brand']) {
            $brand = HSY_BRANDS::assign($product_id, $item['brand']);
            if (is_wp_error($brand)) {
                $warnings[] = $brand->get_error_message();
            }
        }
        if ($config['import_images'] && $has('images') && $item['images']) {
            $warnings = array_merge($warnings, self::set_images($product, $item['images']));
        }

        $action = $is_new ? 'created' : 'updated';
        HSY_LOG_SERVICE::add('import_' . $action, [
            'source_id' => $item['id'],
            'product_id' => $product_id,
            'sku' => $item['sku'],
            'fields' => $fields,
            'warnings' => $warnings,
        ]);
        return ['id' => $product_id, 'action' => $action, 'warnings' => $warnings];
    }

    // ------------------------------------------------------------------ internos

    private static function normalize($item)
    {
        $item = (array) $item;
        $num = function ($v) {
            return null === $v || '' === $v ? null : (float) $v;
        };
        return [
            'id' => (string) ($item['id'] ?? ''),
            'sku' => wc_clean((string) ($item['sku'] ?? '')),
            'name' => wc_clean((string) ($item['name'] ?? '')),
            'brand' => wc_clean((string) ($item['brand'] ?? '')),
            'description' => wp_kses_post((string) ($item['description'] ?? '')),
            'short_description' => wp_kses_post((string) ($item['short_description'] ?? '')),
            'pvp' => $num($item['pvp'] ?? null),
            'wholesale' => $num($item['wholesale'] ?? null),
            'availability' => wc_clean((string) ($item['availability'] ?? '')),
            'stock' => isset($item['stock']) && '' !== $item['stock'] && null !== $item['stock'] ? (int) $item['stock'] : null,
            'attributes' => array_filter(array_map('wc_clean', (array) ($item['attributes'] ?? [])), 'strlen'),
            'images' => array_values(array_filter(array_map('esc_url_raw', (array) ($item['images'] ?? [])))),
            'url' => esc_url_raw((string) ($item['url'] ?? '')),
        ];
    }

    private static function find($item)
    {
        $ids = get_posts([
            'post_type' => 'product',
            'post_status' => 'any',
            'meta_key' => HSY_SOURCE_META,
            'meta_value' => $item['id'],
            'fields' => 'ids',
            'posts_per_page' => 1,
            'no_found_rows' => true,
        ]);
        if ($ids) {
            return (int) $ids[0];
        }
        return '' !== $item['sku'] ? (int) wc_get_product_id_by_sku($item['sku']) : 0;
    }

    /**
     * Nombre según la plantilla ({nombre}, {genero}, {marca}, {ref}). Una variable vacía no deja espacios dobles.
     */
    private static function name($item, $template)
    {
        $name = strtr((string) $template, [
            '{nombre}' => $item['name'],
            '{genero}' => (string) ($item['attributes']['Género'] ?? ''),
            '{marca}' => $item['brand'],
            '{ref}' => $item['sku'],
        ]);
        $name = trim(preg_replace('/\s+/', ' ', $name));
        return '' !== $name ? $name : $item['name'];
    }

    /**
     * Atributos locales (visibles en la ficha, sin variaciones).
     */
    private static function attributes($values)
    {
        $attributes = [];
        $position = 0;
        foreach ($values as $name => $value) {
            $attribute = new WC_Product_Attribute();
            $attribute->set_name($name);
            $attribute->set_options([$value]);
            $attribute->set_position($position++);
            $attribute->set_visible(true);
            $attribute->set_variation(false);
            $attributes[sanitize_title($name)] = $attribute;
        }
        return $attributes;
    }

    /**
     * IDs de product_cat a partir de nombres ("Padre > Hijo" crea la jerarquía). Crea las que falten.
     */
    public static function category_ids($names)
    {
        $ids = [];
        foreach ($names as $path) {
            $parent = 0;
            foreach (array_filter(array_map('trim', explode('>', $path))) as $name) {
                $term = term_exists($name, 'product_cat', $parent);
                if (!$term) {
                    $term = wp_insert_term($name, 'product_cat', ['parent' => $parent]);
                }
                if (is_wp_error($term)) {
                    break;
                }
                $parent = (int) $term['term_id'];
            }
            if ($parent) {
                $ids[] = $parent;
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * Descarga las imágenes (con la sesión del catálogo) y las asigna: la primera es la destacada.
     * Una imagen ya descargada (misma URL de origen) se reutiliza.
     */
    private static function set_images($product, $urls)
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $warnings = [];
        $ids = [];
        foreach ($urls as $url) {
            $existing = get_posts([
                'post_type' => 'attachment',
                'post_status' => 'inherit',
                'meta_key' => '_hsy_source_url',
                'meta_value' => $url,
                'fields' => 'ids',
                'posts_per_page' => 1,
                'no_found_rows' => true,
            ]);
            if ($existing) {
                $ids[] = (int) $existing[0];
                continue;
            }

            $tmp = HSY_CLIENT::download($url);
            if (is_wp_error($tmp)) {
                $warnings[] = $tmp->get_error_message();
                continue;
            }
            $name = sanitize_file_name(wp_basename(wp_parse_url($url, PHP_URL_PATH) ?: 'imagen.jpg'));
            if (!preg_match('/\.(jpe?g|png|gif|webp)$/i', $name)) {
                $name .= '.jpg';
            }
            $attachment_id = media_handle_sideload(['name' => $name, 'tmp_name' => $tmp], $product->get_id(), $product->get_name());
            if (is_wp_error($attachment_id)) {
                @unlink($tmp);
                $warnings[] = 'Imagen ' . $url . ': ' . $attachment_id->get_error_message();
                continue;
            }
            update_post_meta($attachment_id, '_hsy_source_url', $url);
            $ids[] = (int) $attachment_id;
        }

        if ($ids) {
            $product->set_image_id(array_shift($ids));
            $product->set_gallery_image_ids($ids);
            $product->save();
        }
        return $warnings;
    }
}
