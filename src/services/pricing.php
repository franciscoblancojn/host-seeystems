<?php

/**
 * Precio de venta y categoría de descuento de los productos importados: única fuente del cálculo.
 *
 * Con el precio mayorista (W) y el PVP sugerido (P) del catálogo:
 * 1. Margen aprox. sobre PVP = round((1 − W / P) × 100)  (lo mismo que muestra el catálogo).
 * 2. Precio base = el mayor entre W × 2 y P.
 * 3. Categoría de descuento según el margen (ajuste discount_tiers): p. ej. 50–59 % → Ofertas 5 %,
 *    60–69 % → Ofertas 10 %, 70 % o más → Ofertas 15 %; menos → ninguna. Esas categorías las usa otro
 *    plugin para aplicar el descuento; este plugin solo las asigna (y quita las de otros tramos).
 * 4. Descuento total = global_discount (27 %) + % de su categoría (o encadenado, ajuste discount_combine).
 * 5. Utilidad mínima: si precio × (1 − descuento total) ≤ W × (1 + profit_margin), el precio sube lo
 *    justo (redondeado hacia arriba a price_round) para quedar por encima.
 *
 * Metas del producto: _hsy_wholesale_price, _hsy_pvp, _hsy_margin, _hsy_wholesale_x2, _hsy_price_calc.
 */
class HSY_PRICING
{
    const MARGIN_META = '_hsy_margin';
    const X2_META = '_hsy_wholesale_x2';
    const CALC_META = '_hsy_price_calc';

    /** Meta con la que «Descuentos por Volumen» marca un producto como manual (sin descuentos automáticos). */
    const DPV_MANUAL_META = '_dpv_manual';

    /** Tramos por defecto: margen mínimo (incluido) → slug de la categoría y % de descuento que aplica el otro plugin. */
    const DEFAULT_TIERS = [
        ['min' => 50, 'slug' => 'ofertas-5', 'percent' => 5],
        ['min' => 60, 'slug' => 'ofertas-10', 'percent' => 10],
        ['min' => 70, 'slug' => 'ofertas-15', 'percent' => 15],
    ];

    public static function margin($wholesale, $pvp)
    {
        return ($wholesale > 0 && $pvp > 0) ? (int) round((1 - $wholesale / $pvp) * 100) : null;
    }

    /**
     * Tramos configurados, ordenados de mayor a menor margen mínimo.
     */
    public static function tiers($config)
    {
        $tiers = array_values(array_filter((array) ($config['discount_tiers'] ?? []), function ($t) {
            return isset($t['min'], $t['slug']) && '' !== (string) $t['slug'];
        }));
        if (!$tiers) {
            $tiers = self::DEFAULT_TIERS;
        }
        usort($tiers, function ($a, $b) {
            return (float) $b['min'] - (float) $a['min'];
        });
        return $tiers;
    }

    /** Tramo que corresponde a un margen (el de mayor «min» que el margen alcanza), o null. */
    public static function tier($margin, $config)
    {
        if (null === $margin) {
            return null;
        }
        foreach (self::tiers($config) as $tier) {
            if ($margin >= (float) $tier['min']) {
                return $tier;
            }
        }
        return null;
    }

    /** Descuento total (0–1) con el global y el de la categoría. */
    public static function total_discount($tier_percent, $config)
    {
        $global = max(0, (float) ($config['global_discount'] ?? 27)) / 100;
        $cat = max(0, (float) $tier_percent) / 100;
        $total = 'chain' === ($config['discount_combine'] ?? 'sum') ? 1 - (1 - $global) * (1 - $cat) : $global + $cat;
        return min(0.99, $total);
    }

    /**
     * Cálculo completo (sin guardar). Devuelve null si no hay precio mayorista ni PVP.
     */
    public static function calc($wholesale, $pvp, $config)
    {
        $w = (float) $wholesale;
        $p = (float) $pvp;
        if ($w <= 0 && $p <= 0) {
            return null;
        }
        $decimals = wc_get_price_decimals();
        $margin = self::margin($w, $p);
        $x2 = $w > 0 ? round($w * 2, $decimals) : 0;
        $base = max($x2, $p);
        $tier = self::tier($margin, $config);
        $discount = self::total_discount($tier ? $tier['percent'] : 0, $config);
        $minimum = $w > 0 ? $w * (1 + max(0, (float) ($config['profit_margin'] ?? 20)) / 100) : 0;
        $price = $base;
        $adjusted = false;
        if ($w > 0 && $price * (1 - $discount) <= $minimum) {
            // Precio mínimo que deja el neto por encima del mínimo, redondeado hacia arriba.
            $round = max(1, (int) ($config['price_round'] ?? 100));
            $price = ceil(($minimum / (1 - $discount)) / $round) * $round;
            if ($price * (1 - $discount) <= $minimum) {
                $price += $round;
            }
            $adjusted = true;
        }
        $price = round($price, $decimals);
        return [
            'wholesale' => $w,
            'pvp' => $p,
            'margin' => $margin,
            'x2' => $x2,
            'base' => $base,
            'base_from' => $x2 > $p ? 'x2' : 'pvp',
            'tier' => $tier,
            'discount' => round($discount * 100, 2),
            'price' => $price,
            'net' => round($price * (1 - $discount), $decimals),
            'minimum' => round($minimum, $decimals),
            'adjusted' => $adjusted,
        ];
    }

    /**
     * Aplica el cálculo al producto (sin guardar): metas y descuentos automáticos siempre; precio normal y
     * categoría de descuento solo con $set_price. Nunca toca el precio rebajado (es del plugin de descuentos).
     * Devuelve el cálculo o null.
     */
    public static function apply($product, $wholesale, $pvp, $config, $set_price = true)
    {
        $product->update_meta_data(HSY_WHOLESALE_META, $wholesale > 0 ? (string) $wholesale : '');
        $product->update_meta_data('_hsy_pvp', $pvp > 0 ? (string) $pvp : '');
        $calc = self::calc($wholesale, $pvp, $config);
        if (!$calc) {
            self::reactivate_discounts($product, $config);
            return null;
        }
        $old = $product->get_meta(self::CALC_META);
        $old_tier = is_array($old) ? (string) ($old['tier'] ?? '') : '';
        $product->update_meta_data(self::MARGIN_META, null === $calc['margin'] ? '' : (string) $calc['margin']);
        $product->update_meta_data(self::X2_META, $calc['x2'] > 0 ? (string) $calc['x2'] : '');
        $product->update_meta_data(self::CALC_META, [
            'price' => $calc['price'],
            'base_from' => $calc['base_from'],
            'tier' => $calc['tier'] ? $calc['tier']['slug'] : '',
            'discount' => $calc['discount'],
            'net' => $calc['net'],
            'minimum' => $calc['minimum'],
            'adjusted' => $calc['adjusted'],
            'tier_prev' => $old_tier, // tramo anterior: set_tier_category() también lo quita
            'at' => time(),
        ]);
        if ($set_price) {
            // Solo el precio normal. El precio rebajado NO se toca: lo calcula el plugin de descuentos al guardar
            // (si se vaciara aquí, «Descuentos por Volumen» lo tomaría como una edición manual y dejaría de aplicarlo).
            $product->set_regular_price((string) $calc['price']);
            self::set_tier_category($product, $calc['tier'], $config);
        }
        self::reactivate_discounts($product, $config);
        return $calc;
    }

    /** ¿Está activo el plugin «Descuentos por Volumen»? */
    public static function dpv_active()
    {
        return class_exists('DPV_RULES');
    }

    /**
     * Con el ajuste reactivate_discounts (y «Descuentos por Volumen» activo), quita el modo manual del producto
     * para que ese plugin le aplique sus descuentos automáticos al guardar. Se llama en toda importación o
     * actualización (vía apply()).
     */
    public static function reactivate_discounts($product, $config)
    {
        if (!empty($config['reactivate_discounts']) && self::dpv_active()) {
            $product->delete_meta_data(self::DPV_MANUAL_META);
        }
    }

    /**
     * Deja al producto solo la categoría de descuento de su tramo (quita las de los demás tramos).
     */
    public static function set_tier_category($product, $tier, $config)
    {
        // Slugs de tramo que se pueden quitar: los configurados, los por defecto y el último asignado a este
        // producto (por si la configuración de tramos cambió desde entonces).
        $slugs = array_merge(wp_list_pluck(self::tiers($config), 'slug'), wp_list_pluck(self::DEFAULT_TIERS, 'slug'));
        $previous = $product->get_meta(self::CALC_META);
        if (is_array($previous) && !empty($previous['tier_prev'])) {
            $slugs[] = $previous['tier_prev'];
        }
        $tier_ids = [];
        $target = 0;
        foreach (array_unique($slugs) as $slug) {
            $term = get_term_by('slug', $slug, 'product_cat');
            if ($term instanceof WP_Term) {
                $tier_ids[] = (int) $term->term_id;
                if ($tier && $slug === $tier['slug']) {
                    $target = (int) $term->term_id;
                }
            }
        }
        $ids = array_values(array_diff(array_map('intval', $product->get_category_ids()), $tier_ids));
        if ($target) {
            $ids[] = $target;
        }
        $product->set_category_ids($ids);
    }

    /**
     * Recalcula un producto importado con los precios más recientes (catálogo guardado o, si no está, sus metas)
     * y lo guarda. Devuelve el cálculo o WP_Error.
     */
    public static function update_product($product_id, $config)
    {
        $product = wc_get_product($product_id);
        if (!$product || !HSY_STOCK::is_imported($product)) {
            return new WP_Error('hsy_price_product', 'El producto #' . (int) $product_id . ' no es un producto importado.');
        }
        $item = (new HSY_USE_DATA_CATALOG())->item($product->get_meta(HSY_SOURCE_META));
        $wholesale = $item && !empty($item['wholesale']) ? (float) $item['wholesale'] : (float) $product->get_meta(HSY_WHOLESALE_META);
        $pvp = $item && !empty($item['pvp']) ? (float) $item['pvp'] : (float) $product->get_meta('_hsy_pvp');
        $before = $product->get_regular_price();
        $calc = self::apply($product, $wholesale, $pvp, $config);
        if (!$calc) {
            return new WP_Error('hsy_price_empty', 'El producto #' . (int) $product_id . ' no tiene precio mayorista ni PVP.');
        }
        $product->save();
        HSY_LOG_SERVICE::add('precio', [
            'product_id' => $product->get_id(),
            'sku' => $product->get_sku(),
            'antes' => $before,
            'precio' => $calc['price'],
            'margen' => $calc['margin'],
            'categoria' => $calc['tier'] ? $calc['tier']['slug'] : '',
            'ajustado' => $calc['adjusted'],
        ]);
        return $calc;
    }
}
