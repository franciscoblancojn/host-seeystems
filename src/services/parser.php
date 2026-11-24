<?php

/**
 * Convierte el HTML del catálogo de origen en productos normalizados (ver HSY_IMPORTER).
 * Única clase que conoce el HTML del origen.
 *
 * Cada tarjeta (.product-card) lleva todos los datos del producto en JSON dentro de
 * onclick="openProductModal({...})": id, ref, nombre, marca, precio (precio del cliente),
 * pvp, genero, tamano, descripcion (etiqueta de disponibilidad), correa_*, caja_*,
 * resistencia_agua, stock, stock_reservado, transito, imgs.
 * La paginación es catalog?page=N con un enlace «Siguiente».
 */
class HSY_PARSER
{
    /** Etiqueta de disponibilidad que deja el producto agotado aunque el stock diga otra cosa. */
    const OUT_OF_STOCK_LABEL = 'SIN STOCK';

    /**
     * @return array[] productos normalizados de una página del catálogo.
     */
    public static function products($html, $page_url)
    {
        $items = [];
        if (!preg_match_all('/openProductModal\((\{.*?\})\)"/s', (string) $html, $matches)) {
            return $items;
        }
        foreach ($matches[1] as $raw) {
            $data = json_decode(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
            if (is_array($data) && isset($data['id'])) {
                $items[] = self::normalize($data, $page_url);
            }
        }
        return $items;
    }

    /**
     * URL de la página siguiente o '' si es la última.
     */
    public static function next_page($html, $page_url)
    {
        if (preg_match('/<a[^>]+class=["\'][^"\']*page-link[^"\']*["\'][^>]*href=["\']([^"\']+)["\'][^>]*>\s*Siguiente/i', (string) $html, $m)
            || preg_match('/<a[^>]+href=["\']([^"\']+)["\'][^>]*class=["\'][^"\']*page-link[^"\']*["\'][^>]*>\s*Siguiente/i', (string) $html, $m)) {
            $url = HSY_CLIENT::absolute($m[1], $page_url);
            return preg_replace('/#.*$/', '', $url);
        }
        return '';
    }

    /**
     * Producto del origen → producto normalizado.
     */
    public static function normalize($d, $page_url = '')
    {
        $text = function ($key) use ($d) {
            return trim((string) ($d[$key] ?? ''));
        };
        $join = function ($parts) {
            return implode(' ', array_unique(array_filter(array_map('trim', $parts))));
        };

        $label = $text('descripcion');
        $stock = max(0, (int) ($d['stock'] ?? 0) - (int) ($d['stock_reservado'] ?? 0));
        if (0 === strcasecmp($label, self::OUT_OF_STOCK_LABEL)) {
            $stock = 0;
        }

        $attributes = array_filter([
            'Marca' => $text('marca'),
            'Género' => ucfirst(strtolower($text('genero'))),
            'Tamaño' => $text('tamano'),
            'Correa' => $join([$text('correa_material'), $text('correa_color')]),
            'Caja' => $join([$text('caja_material'), $text('caja_color')]),
            'Resistencia al agua' => $text('resistencia_agua'),
        ], 'strlen');

        $list = '';
        foreach ($attributes as $name => $value) {
            $list .= '<li><strong>' . esc_html($name) . ':</strong> ' . esc_html($value) . '</li>';
        }

        $pvp = (float) ($d['pvp'] ?? 0);
        $cost = (float) ($d['precio'] ?? 0);
        $images = [];
        foreach ((array) ($d['imgs'] ?? []) as $src) {
            $src = HSY_CLIENT::absolute($src, $page_url ?: HSY_CLIENT::url());
            if ($src) {
                $images[] = $src;
            }
        }

        return [
            'id' => (string) $d['id'],
            'sku' => $text('ref'),
            'name' => $text('nombre'),
            'brand' => $text('marca'),
            'description' => '',
            'short_description' => $list ? '<ul>' . $list . '</ul>' : '',
            'pvp' => $pvp > 0 ? $pvp : null,
            'cost' => $cost > 0 ? $cost : null,
            'price_type' => $text('tipo_precio'),
            'availability' => $label,
            'stock' => $stock,
            'incoming' => (int) ($d['transito'] ?? 0),
            'attributes' => $attributes,
            'images' => $images,
            'categories' => $text('marca') ? [$text('marca')] : [],
            'url' => '',
        ];
    }
}
