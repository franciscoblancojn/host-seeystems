<?php

/**
 * Dimensiones y peso por defecto de los productos importados (relojes y joyas).
 *
 * Justo antes de guardar un producto importado (meta HSY_IMPORTED_META), se rellenan SOLO los datos
 * vacíos con los ajustes default_length / default_width / default_height / default_weight (20 × 15 × 15
 * y 1 por defecto, en las unidades de la tienda). Lo que ya tiene valor no se toca: si tiene ancho pero no
 * alto, solo se pone el alto. Cubre cualquier guardado: importar, actualizar, precios, descripciones,
 * CSV y la edición manual.
 */
class HSY_HOOK_SHIPPING
{
    const FIELDS = [
        'length' => 'default_length',
        'width' => 'default_width',
        'height' => 'default_height',
        'weight' => 'default_weight',
    ];

    public static function init()
    {
        add_action('woocommerce_before_product_object_save', [self::class, 'fill']);
    }

    /**
     * @param WC_Product $product
     */
    public static function fill($product)
    {
        if (!$product instanceof WC_Product || $product->is_type('variation') || 'yes' !== $product->get_meta(HSY_IMPORTED_META)) {
            return;
        }
        $config = (new HSY_USE_DATA_CONFIG())->get();
        foreach (self::FIELDS as $field => $setting) {
            $default = $config[$setting] ?? '';
            if ('' === (string) $default || (float) $default <= 0) {
                continue; // ajuste vacío: no se rellena ese dato
            }
            if ('' === trim((string) $product->{'get_' . $field}('edit'))) {
                $product->{'set_' . $field}(wc_format_decimal($default));
            }
        }
    }
}

HSY_HOOK_SHIPPING::init();
