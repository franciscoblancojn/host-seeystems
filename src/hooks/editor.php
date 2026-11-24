<?php

/**
 * Editor de producto: muestra el precio mayorista del catálogo (solo lectura) junto a los
 * precios, para tenerlo a la vista al definir el precio rebajado.
 */
class HSY_HOOK_EDITOR
{
    public static function init()
    {
        add_action('woocommerce_product_options_pricing', [self::class, 'pricing']);
    }

    public static function pricing()
    {
        global $product_object;
        $product = $product_object instanceof WC_Product ? $product_object : wc_get_product(get_the_ID());
        if (!HSY_STOCK::is_imported($product)) {
            return;
        }
        $wholesale = $product->get_meta(HSY_WHOLESALE_META);
        $updated = (int) $product->get_meta('_hsy_imported_at');
        ?>
        <p class="form-field hsy-wholesale">
            <label>Precio mayorista (<?= esc_html(get_woocommerce_currency_symbol()) ?>)</label>
            <strong><?= '' !== $wholesale ? wp_kses_post(wc_price($wholesale)) : '—' ?></strong>
            <?php if ('' !== $wholesale && (float) $product->get_regular_price() > 0) : ?>
                <span class="description">
                    · margen sobre el precio normal: <?= esc_html(round((1 - (float) $wholesale / (float) $product->get_regular_price()) * 100, 1)) ?> %
                </span>
            <?php endif; ?>
            <br><span class="description">Del catálogo B2B<?= $updated ? esc_html(', actualizado el ' . wp_date('Y-m-d H:i', $updated)) : '' ?>. No se edita aquí: se actualiza al reimportar.</span>
        </p>
        <?php
    }
}

HSY_HOOK_EDITOR::init();
