<?php

/**
 * Lee el catálogo completo del origen (todas sus páginas) y lo guarda en HSY_USE_DATA_CATALOG.
 */
class HSY_CATALOG_SERVICE
{
    /**
     * Devuelve el número de productos leídos o WP_Error.
     */
    public static function refresh()
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
        $url = HSY_CLIENT::url(HSY_CLIENT::config()['catalog_path']);
        $items = [];
        $seen = [];

        for ($page = 0; $url && $page < HSY_MAX_PAGES; $page++) {
            if (isset($seen[$url])) {
                break;
            }
            $seen[$url] = true;
            $response = HSY_CLIENT::get($url);
            if (is_wp_error($response)) {
                return $response;
            }
            foreach (HSY_PARSER::products($response['body'], $response['url']) as $item) {
                if ('' !== (string) ($item['id'] ?? '')) {
                    $items[(string) $item['id']] = $item;
                }
            }
            $url = HSY_PARSER::next_page($response['body'], $response['url']);
            if ($url && !HSY_CLIENT::is_same_host($url)) {
                $url = '';
            }
        }

        (new HSY_USE_DATA_CATALOG())->set_items($items);
        HSY_LOG_SERVICE::add('catalog_refresh', ['pages' => count($seen), 'products' => count($items)]);
        return count($items);
    }

    /**
     * Filas para la tabla: producto del catálogo + id del producto importado (si existe) y sus descripciones.
     */
    public static function rows()
    {
        $map = HSY_IMPORTER::imported_map();
        $texts = HSY_CONTENT::texts(array_values($map));
        $rows = [];
        foreach ((new HSY_USE_DATA_CATALOG())->items() as $id => $item) {
            $product_id = $map[(string) $id] ?? 0;
            $rows[] = [
                'id' => (string) $id,
                'sku' => (string) ($item['sku'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'brand' => (string) ($item['brand'] ?? ''),
                'pvp' => $item['pvp'] ?? null,
                'wholesale' => $item['wholesale'] ?? null,
                'stock' => $item['stock'] ?? null,
                'availability' => (string) ($item['availability'] ?? ''),
                'image' => (string) (($item['images'] ?? [])[0] ?? ''),
                'url' => (string) ($item['url'] ?? ''),
                'product_id' => $product_id,
                'edit' => $product_id ? get_edit_post_link($product_id, 'raw') : '',
                // Descripciones del producto importado (texto plano) y si están pendientes.
                'description' => $texts[$product_id]['description'] ?? '',
                'short_description' => $texts[$product_id]['short_description'] ?? '',
                'description_pending' => $product_id ? ($texts[$product_id]['description_pending'] ?? true) : false,
                'short_pending' => $product_id ? ($texts[$product_id]['short_pending'] ?? true) : false,
            ];
        }
        return $rows;
    }
}
