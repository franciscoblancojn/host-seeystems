<?php

/**
 * Convierte el HTML del catálogo de origen en productos normalizados (ver HSY_IMPORTER).
 * Única clase que conoce el HTML del origen.
 *
 * Cada tarjeta (.product-card) lleva todos los datos del producto en JSON dentro de
 * onclick="openProductModal({...})": id, ref, nombre, marca, precio (precio mayorista),
 * pvp (PVP sugerido), genero, tamano, descripcion (suele ser la disponibilidad), correa_*, caja_*,
 * resistencia_agua, stock, stock_reservado, transito, imgs.
 * La paginación es catalog?page=N con un enlace «Siguiente».
 */
class HSY_PARSER
{
    /** Etiqueta de disponibilidad que deja el producto agotado aunque el stock diga otra cosa. */
    const OUT_OF_STOCK_LABEL = 'SIN STOCK';

    /** Textos de «descripcion» que en realidad son la disponibilidad. */
    const AVAILABILITY_LABELS = ['DISPONIBLE', 'EN TRÁNSITO', 'EN TRANSITO', 'SIN STOCK', 'AGOTADO'];

    /**
     * Marca en mayúsculas y minúsculas normales: «ARMANI EXCHANGE» → «Armani Exchange».
     */
    public static function brand_name($name)
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) $name));
        return '' === $name ? '' : mb_convert_case(mb_strtolower($name, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Lista HTML de características («<ul><li><strong>Género:</strong> DAMA</li>…»): la descripción corta provisional.
     */
    public static function list_html($attributes)
    {
        $list = '';
        foreach ((array) $attributes as $name => $value) {
            $list .= '<li><strong>' . esc_html($name) . ':</strong> ' . esc_html($value) . '</li>';
        }
        return $list ? '<ul>' . $list . '</ul>' : '';
    }

    /**
     * Características de una joya. El catálogo usa los campos de reloj (Correa / Caja) para el material
     * y el color: «Caja: Acero, Plateado» → Material «Acero», Color «Plateado». Se quitan Correa, Caja y
     * Resistencia al agua, que no aplican a aretes, collares o pulseras.
     */
    public static function jewelry_attributes($attributes)
    {
        $attributes = (array) $attributes;
        $material = '';
        $color = '';
        $caja = trim((string) ($attributes['Caja'] ?? ''));
        if (false !== strpos($caja, ',')) {
            list($material, $color) = array_map('trim', explode(',', $caja, 2));
        } elseif ('' !== $caja) {
            $material = $caja;
        } elseif ('' !== trim((string) ($attributes['Correa'] ?? ''))) {
            $material = trim((string) $attributes['Correa']);
        }
        $out = [];
        foreach ($attributes as $name => $value) {
            if (in_array($name, ['Correa', 'Caja', 'Resistencia al agua'], true)) {
                continue;
            }
            $out[$name] = $value;
            if ('Género' === $name) {
                // Material y Color justo después del género, como estaban Correa y Caja.
                $out['Material'] = $material;
                $out['Color'] = $color;
            }
        }
        if (!isset($out['Material'])) {
            $out['Material'] = $material;
            $out['Color'] = $color;
        }
        return array_filter($out, 'strlen');
    }

    private static function upper($text)
    {
        return mb_strtoupper(trim((string) $text), 'UTF-8');
    }

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
        $join = function ($glue, $parts) {
            return implode($glue, array_unique(array_filter(array_map('trim', $parts), 'strlen')));
        };

        // «descripcion» suele traer solo la disponibilidad (DISPONIBLE, EN TRÁNSITO, SIN STOCK): no es descripción.
        $label = $text('descripcion');
        $is_label = in_array(self::upper($label), self::AVAILABILITY_LABELS, true);
        $stock = max(0, (int) ($d['stock'] ?? 0) - (int) ($d['stock_reservado'] ?? 0));
        if (self::upper($label) === self::OUT_OF_STOCK_LABEL) {
            $stock = 0;
        }

        // Características en el mismo orden y formato que la ficha del catálogo.
        $attributes = array_filter([
            'Género' => $text('genero'),
            'Correa' => $join(' ', [$text('correa_material'), $text('correa_color')]),
            'Caja' => $join(', ', [$text('caja_material'), $text('caja_color')]),
            'Tamaño' => $text('tamano'),
            'Resistencia al agua' => $text('resistencia_agua'),
            'Referencia' => $text('ref'),
        ], 'strlen');

        $list = self::list_html($attributes);

        $pvp = (float) ($d['pvp'] ?? 0);
        $wholesale = (float) ($d['precio'] ?? 0);
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
            'brand' => self::brand_name($text('marca')),
            'description' => $is_label ? '' : $label,
            'short_description' => $list,
            'pvp' => $pvp > 0 ? $pvp : null,
            'wholesale' => $wholesale > 0 ? $wholesale : null,
            'price_type' => $text('tipo_precio'),
            'availability' => $is_label ? $label : '',
            'stock' => $stock,
            'incoming' => (int) ($d['transito'] ?? 0),
            'attributes' => $attributes,
            'images' => $images,
            'url' => '',
        ];
    }
}
