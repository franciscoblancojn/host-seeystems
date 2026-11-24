<?php

/**
 * Regla del stock mínimo de los productos importados (ajuste min_stock).
 *
 * Única fuente de la regla: con min_stock unidades o menos, el producto queda con stock 0.
 * Se aplica al importar (HSY_IMPORTER) y cada vez que cambia el stock de un producto
 * importado (HSY_HOOK_STOCK): pedidos, edición en el admin, API…
 */
class HSY_STOCK
{
    public static function min()
    {
        return max(0, (int) (new HSY_USE_DATA_CONFIG())->get()['min_stock']);
    }

    /**
     * Cantidad que debe quedar según la regla.
     */
    public static function apply($quantity, $min = null)
    {
        $min = null === $min ? self::min() : (int) $min;
        $quantity = (int) $quantity;
        return $min > 0 && $quantity <= $min ? 0 : max(0, $quantity);
    }

    public static function is_imported($product)
    {
        return $product instanceof WC_Product && 'yes' === $product->get_meta(HSY_IMPORTED_META);
    }
}
