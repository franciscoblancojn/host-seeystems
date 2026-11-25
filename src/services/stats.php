<?php

/**
 * Contadores de la pestaña Productos y publicación de los borradores listos.
 *
 * «Con descripción» usa la misma regla que el badge de la tabla (HSY_CONTENT): un producto
 * importado tiene descripción si no está pendiente (descripción y descripción corta definitivas).
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
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_status, TRIM(p.post_content) = '' AS no_description, p.post_excerpt, sa.meta_value AS short_auto
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} imp ON imp.post_id = p.ID AND imp.meta_key = %s AND imp.meta_value = 'yes'
             LEFT JOIN {$wpdb->postmeta} sa ON sa.post_id = p.ID AND sa.meta_key = %s
             WHERE p.post_type = 'product' AND p.post_status NOT IN ('trash', 'auto-draft')",
            HSY_IMPORTED_META,
            HSY_SHORT_AUTO_META
        ));
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->ID] = [
                'status' => $row->post_status,
                'pending' => (bool) $row->no_description || HSY_CONTENT::short_pending_raw($row->post_excerpt, $row->short_auto),
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
            'with_description' => $with,
            'without_description' => count($imported) - $with,
            'draft' => $draft,
            'publishable' => count(self::publishable_ids($imported)),
        ];
    }

    /**
     * IDs de los productos importados en borrador que ya tienen descripción.
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
     * Publica un producto si sigue cumpliendo las condiciones (importado, borrador, con descripción).
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
        $product->set_status('publish');
        $product->save();
        HSY_LOG_SERVICE::add('publish', ['product_id' => $product->get_id(), 'sku' => $product->get_sku()]);
        return true;
    }
}
