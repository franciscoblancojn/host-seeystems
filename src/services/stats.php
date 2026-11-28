<?php

/**
 * Contadores de la pestaña Productos y publicación de los borradores listos.
 *
 * «Con contenido» = producto importado con descripción, descripción corta (definitivas, misma regla
 * que el badge de la tabla) y categorías. Solo esos se publican.
 */
class HSY_STATS
{
    /** Productos por petición al publicar. */
    const PUBLISH_BATCH = 20;

    /**
     * Estado de los productos importados en una consulta:
     * [product_id => ['status' => post_status, 'pending' => bool]].
     */
    private static function imported()
    {
        global $wpdb;
        $ignored = HSY_CATEGORIES::ignored_sql(); // enteros: categoría por defecto y de ofertas
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_status, TRIM(p.post_content) = '' AS no_description, p.post_excerpt, sa.meta_value AS short_auto,
                    (SELECT COUNT(*) FROM {$wpdb->term_relationships} tr
                     INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                     WHERE tr.object_id = p.ID AND tt.taxonomy = 'product_cat' AND tt.term_id NOT IN ({$ignored})) AS categories
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} imp ON imp.post_id = p.ID AND imp.meta_key = %s AND imp.meta_value = 'yes'
             LEFT JOIN {$wpdb->postmeta} sa ON sa.post_id = p.ID AND sa.meta_key = %s
             WHERE p.post_type = 'product' AND p.post_status NOT IN ('trash', 'auto-draft')",
            HSY_IMPORTED_META,
            HSY_SHORT_AUTO_META
        ));
        $out = [];
        foreach ($rows as $row) {
            // Contenido completo = descripción + descripción corta + categorías (el estilo no cuenta).
            $out[(int) $row->ID] = [
                'status' => $row->post_status,
                'pending' => (bool) $row->no_description
                    || HSY_CONTENT::short_pending_raw($row->post_excerpt, $row->short_auto)
                    || 0 === (int) $row->categories,
            ];
        }
        return $out;
    }

    /**
     * Contadores para la pestaña Productos.
     */
    public static function counts()
    {
        $imported = self::imported();
        $catalog = (new HSY_USE_DATA_CATALOG())->items();
        $map = HSY_IMPORTER::imported_map();
        $not_imported = 0;
        foreach (array_keys($catalog) as $source_id) {
            if (empty($map[(string) $source_id])) {
                $not_imported++;
            }
        }
        $with = 0;
        $draft = 0;
        foreach ($imported as $p) {
            if (!$p['pending']) {
                $with++;
            }
            if ('draft' === $p['status']) {
                $draft++;
            }
        }
        return [
            'total' => count($catalog),
            'imported' => count($imported),
            'not_imported' => $not_imported,
            'with_content' => $with,
            'without_content' => count($imported) - $with,
            'draft' => $draft,
            'publishable' => count(self::publishable_ids($imported)),
        ];
    }

    /**
     * IDs de los productos importados en borrador con contenido completo.
     */
    public static function publishable_ids($imported = null)
    {
        $ids = [];
        foreach (null === $imported ? self::imported() : $imported as $id => $p) {
            if ('draft' === $p['status'] && !$p['pending']) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /**
     * Publica un producto si sigue cumpliendo las condiciones (importado, borrador, con contenido completo).
     * Devuelve true o WP_Error.
     */
    public static function publish($product_id)
    {
        $product = wc_get_product($product_id);
        if (!$product || !HSY_STOCK::is_imported($product)) {
            return new WP_Error('hsy_publish_product', 'El producto #' . (int) $product_id . ' no es un producto importado.');
        }
        if ('draft' !== $product->get_status()) {
            return new WP_Error('hsy_publish_status', 'El producto #' . (int) $product_id . ' ya no está en borrador.');
        }
        if (HSY_CONTENT::is_pending($product)) {
            return new WP_Error('hsy_publish_pending', 'El producto #' . (int) $product_id . ' no tiene descripción.');
        }
        if (!HSY_CATEGORIES::has_categories($product)) {
            return new WP_Error('hsy_publish_categories', 'El producto #' . (int) $product_id . ' no tiene categorías.');
        }
        $product->set_status('publish');
        $product->save();
        HSY_LOG_SERVICE::add('publish', ['product_id' => $product->get_id(), 'sku' => $product->get_sku()]);
        return true;
    }
}
