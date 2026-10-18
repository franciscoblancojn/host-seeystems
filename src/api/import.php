<?php

use franciscoblancojn\wordpress_utils\FWUSystemLog;

/**
 * Carga los productos sincronizados (HSEY_PRODUCTS) en WooCommerce.
 * - No existe (por SKU = ref, o meta HSEY_META_ID) → lo crea con todos los datos.
 * - Existe → actualiza solo los campos activos en Configuracion → Carga.
 * Se ejecuta por lotes vía AJAX (wp_ajax_hsey_import_batch).
 */
class HSEY_API_IMPORT
{
    const BATCH_MAX = 20;
    const META_ID = '_hsey_id';
    const META_IMG_SRC = '_hsey_img_src';

    /**
     * Campos actualizables cuando el producto ya existe => [label, activo por defecto].
     */
    const FIELDS = [
        'stock' => ['Cantidad (stock)', true],
        'price' => ['Precio', false],
        'name' => ['Nombre', false],
        'description' => ['Descripción', false],
        'attributes' => ['Atributos (género, tamaño, correa, caja, resistencia)', false],
        'brand' => ['Marca', false],
        'images' => ['Imágenes', false],
    ];

    const PRICE_SOURCES = [
        'pvp' => 'PVP (si es 0 usa el precio cliente)',
        'precio' => 'Precio cliente',
    ];

    const STATUSES = [
        'draft' => 'Borrador',
        'pending' => 'Pendiente de revisión',
        'publish' => 'Publicado',
    ];

    const ATTRIBUTES = [
        'genero' => 'Género',
        'tamano' => 'Tamaño',
        'correa' => 'Correa',
        'caja' => 'Caja',
        'resistencia_agua' => 'Resistencia al agua',
    ];

    public static function init()
    {
        add_action('wp_ajax_hsey_import_batch', [__CLASS__, 'ajaxBatch']);
    }

    public static function ajaxBatch()
    {
        check_ajax_referer('hsey_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No autorizado.');
        }
        if (!class_exists('WooCommerce')) {
            wp_send_json_error('WooCommerce no está activo.');
        }
        $offset = max(0, intval($_POST['offset'] ?? 0));
        $limit = min(self::BATCH_MAX, max(1, intval($_POST['limit'] ?? 5)));
        wp_send_json_success(self::batch($offset, $limit));
    }

    public static function batch($offset, $limit)
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(300);
        }
        $items = (new HSEY_USE_DATA_PRODUCTS())->get()['items'] ?? [];
        $total = count($items);
        $result = [
            'total' => $total,
            'next' => min($total, $offset + $limit),
            'created' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        foreach (array_slice($items, $offset, $limit) as $item) {
            try {
                $action = self::importProduct($item);
                $result[$action]++;
            } catch (Exception $e) {
                $result['errors'][] = ($item['ref'] ?? $item['id'] ?? '?') . ': ' . $e->getMessage();
            }
        }

        if (!empty($result['errors'])) {
            FWUSystemLog::add(HSEY_KEY, [
                'type' => 'import_errors',
                'offset' => $offset,
                'errors' => $result['errors'],
            ]);
        }
        if ($result['next'] >= $total) {
            FWUSystemLog::add(HSEY_KEY, [
                'type' => 'import_done',
                'total' => $total,
            ]);
        }
        return $result;
    }

    /**
     * @return string 'created' | 'updated'
     */
    public static function importProduct($item)
    {
        if (empty($item['ref']) && empty($item['id'])) {
            throw new Exception('producto sin ref ni id');
        }
        $config = (new HSEY_USE_DATA_CONFIG())->getImport();

        $product_id = self::findProductId($item);
        if ($product_id) {
            $product = wc_get_product($product_id);
            $fields = array_keys(array_filter($config['update_fields']));
            $action = 'updated';
        } else {
            $product = new WC_Product_Simple();
            $product->set_status($config['status']);
            $product->set_sku($item['ref'] ?? '');
            $fields = array_keys(self::FIELDS);
            $action = 'created';
        }
        if (!$product) {
            throw new Exception('no se pudo cargar el producto ' . $product_id);
        }

        $product->update_meta_data(self::META_ID, $item['id'] ?? '');
        $product_id = self::apply($product, $item, $fields, $config);

        // Marca e imágenes necesitan el ID del producto
        if (in_array('brand', $fields, true) && !empty($item['marca']) && taxonomy_exists('product_brand')) {
            wp_set_object_terms($product_id, [$item['marca']], 'product_brand');
        }
        if (in_array('images', $fields, true)) {
            self::applyImages($product, $item['imgs'] ?? []);
        }
        return $action;
    }

    private static function findProductId($item)
    {
        if (!empty($item['ref'])) {
            $id = wc_get_product_id_by_sku($item['ref']);
            if ($id) {
                return $id;
            }
        }
        if (empty($item['id'])) {
            return 0;
        }
        $ids = get_posts([
            'post_type' => 'product',
            'post_status' => 'any',
            'meta_key' => self::META_ID,
            'meta_value' => $item['id'],
            'fields' => 'ids',
            'posts_per_page' => 1,
        ]);
        return $ids ? $ids[0] : 0;
    }

    private static function apply($product, $item, $fields, $config)
    {
        if (in_array('name', $fields, true)) {
            $product->set_name($item['nombre'] ?? ($item['ref'] ?? ''));
        }
        if (in_array('price', $fields, true)) {
            $product->set_regular_price((string) self::price($item, $config['price_source']));
        }
        if (in_array('stock', $fields, true)) {
            $product->set_manage_stock(true);
            $product->set_stock_quantity(intval($item['stock'] ?? 0));
        }
        if (in_array('description', $fields, true)) {
            $product->set_description(self::description($item));
        }
        if (in_array('attributes', $fields, true)) {
            $product->set_attributes(self::attributes($item));
        }
        return $product->save();
    }

    private static function price($item, $source)
    {
        $pvp = floatval($item['pvp'] ?? 0);
        $precio = floatval($item['precio'] ?? 0);
        if ($source === 'pvp' && $pvp > 0) {
            return $pvp;
        }
        return $precio;
    }

    private static function specs($item)
    {
        return [
            'genero' => $item['genero'] ?? '',
            'tamano' => $item['tamano'] ?? '',
            'correa' => trim(($item['correa_material'] ?? '') . ' ' . ($item['correa_color'] ?? '')),
            'caja' => trim(($item['caja_material'] ?? '') . ' ' . ($item['caja_color'] ?? '')),
            'resistencia_agua' => $item['resistencia_agua'] ?? '',
        ];
    }

    private static function description($item)
    {
        $html = '<ul>';
        foreach (self::specs($item) as $key => $value) {
            if ($value !== '') {
                $html .= '<li><strong>' . esc_html(self::ATTRIBUTES[$key]) . ':</strong> ' . esc_html($value) . '</li>';
            }
        }
        $html .= '</ul>';
        if (!empty($item['descripcion'])) {
            $html .= '<p>' . esc_html($item['descripcion']) . '</p>';
        }
        return $html;
    }

    private static function attributes($item)
    {
        $attributes = [];
        $position = 0;
        foreach (self::specs($item) as $key => $value) {
            if ($value === '') {
                continue;
            }
            $attribute = new WC_Product_Attribute();
            $attribute->set_name(self::ATTRIBUTES[$key]);
            $attribute->set_options([$value]);
            $attribute->set_position($position++);
            $attribute->set_visible(true);
            $attribute->set_variation(false);
            $attributes[] = $attribute;
        }
        return $attributes;
    }

    /**
     * Descarga (GET) las imágenes y las asigna: la primera destacada, el resto galería.
     * Reutiliza adjuntos ya descargados (meta META_IMG_SRC) para no duplicar.
     */
    private static function applyImages($product, $urls)
    {
        if (empty($urls)) {
            return;
        }
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $ids = [];
        foreach ($urls as $url) {
            $id = self::imageId($url, $product->get_id());
            if ($id) {
                $ids[] = $id;
            }
        }
        if (empty($ids)) {
            return;
        }
        $product->set_image_id(array_shift($ids));
        $product->set_gallery_image_ids($ids);
        $product->save();
    }

    private static function imageId($url, $product_id)
    {
        $url = esc_url_raw($url);
        if ($url === '') {
            return 0;
        }
        $existing = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'any',
            'meta_key' => self::META_IMG_SRC,
            'meta_value' => $url,
            'fields' => 'ids',
            'posts_per_page' => 1,
        ]);
        if ($existing) {
            return $existing[0];
        }

        $tmp = download_url($url, 30);
        if (is_wp_error($tmp)) {
            // Imágenes que no existen en la plataforma (la web usa no-image.svg)
            return 0;
        }
        $id = media_handle_sideload([
            'name' => basename(wp_parse_url($url, PHP_URL_PATH)),
            'tmp_name' => $tmp,
        ], $product_id);
        if (is_wp_error($id)) {
            @unlink($tmp);
            return 0;
        }
        update_post_meta($id, self::META_IMG_SRC, $url);
        return $id;
    }
}
