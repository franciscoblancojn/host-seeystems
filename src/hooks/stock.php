<?php

/**
 * Stock mínimo automático: cuando el stock de un producto importado queda en el mínimo
 * o por debajo (venta, edición manual, API), se pone a 0.
 *
 * WooCommerce dispara woocommerce_product_set_stock cada vez que cambia _stock (pedidos y
 * guardado del producto). Volver a poner 0 dispara el hook otra vez, pero con stock 0 ya no
 * hace nada.
 */
class HSY_HOOK_STOCK
{
    public static function init()
    {
        add_action('woocommerce_product_set_stock', [self::class, 'check']);
    }

    public static function check($product)
    {
        if (!HSY_STOCK::is_imported($product) || !$product->managing_stock()) {
            return;
        }
        $quantity = (int) $product->get_stock_quantity();
        if ($quantity <= 0 || HSY_STOCK::apply($quantity) === $quantity) {
            return;
        }
        wc_update_product_stock($product, 0, 'set');
        HSY_LOG_SERVICE::add('stock_minimo', [
            'product_id' => $product->get_id(),
            'sku' => $product->get_sku(),
            'stock' => $quantity,
            'min' => HSY_STOCK::min(),
        ]);
    }
}

HSY_HOOK_STOCK::init();
