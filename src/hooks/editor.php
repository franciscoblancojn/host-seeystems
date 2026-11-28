<?php

/**
 * Editor de producto: debajo de los precios muestra (solo lectura) precio mayorista, PVP sugerido,
 * margen aprox. sobre PVP, mayorista × 2, precio calculado, categoría y total de descuento (HSY_PRICING).
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
        $config = (new HSY_USE_DATA_CONFIG())->get();
        $wholesale = (float) $product->get_meta(HSY_WHOLESALE_META);
        $pvp = (float) $product->get_meta('_hsy_pvp');
        $calc = HSY_PRICING::calc($wholesale, $pvp, $config);
        $updated = (int) $product->get_meta('_hsy_imported_at');
        $money = function ($v) {
            return $v > 0 ? wp_kses_post(wc_price($v)) : '—';
        };
        ?>
        <div class="hsy-wholesale" style="padding:6px 12px 10px;margin:6px 12px;background:#f6f7f7;border-left:3px solid #2271b1;">
            <p style="margin:4px 0;"><strong>Precio mayorista:</strong> <?= $money($wholesale) ?>
                &nbsp;·&nbsp; <strong>PVP sugerido:</strong> <?= $money($pvp) ?>
                <?php if ($calc && null !== $calc['margin']) : ?>
                    &nbsp;·&nbsp; <strong>Margen aprox. <?= (int) $calc['margin'] ?> % sobre PVP</strong>
                <?php endif; ?>
            </p>
            <?php if ($calc) : ?>
                <p style="margin:4px 0;"><strong>Precio mayorista × 2:</strong> <?= $money($calc['x2']) ?>
                    &nbsp;·&nbsp; <strong>Precio calculado:</strong> <?= $money($calc['price']) ?>
                    <span class="description">(<?= $calc['adjusted'] ? 'ajustado por utilidad mínima' : ('x2' === $calc['base_from'] ? 'el mayor: mayorista × 2' : 'el mayor: PVP sugerido') ?>)</span>
                </p>
                <p style="margin:4px 0;"><strong>Categoría de descuento:</strong>
                    <?= $calc['tier'] ? esc_html($calc['tier']['slug'] . ' (' . $calc['tier']['percent'] . ' %)') : 'ninguna (margen menor al primer tramo)' ?>
                    &nbsp;·&nbsp; <strong>Descuento total:</strong> <?= esc_html($calc['discount']) ?> %
                    &nbsp;·&nbsp; <strong>Con descuentos:</strong> <?= $money($calc['net']) ?>
                    <span class="description">(mínimo: mayorista + <?= esc_html((float) $config['profit_margin']) ?> % = <?= $money($calc['minimum']) ?>)</span>
                </p>
                <?php if ((float) $product->get_regular_price() !== (float) $calc['price']) : ?>
                    <p style="margin:4px 0;color:#996800;">El precio normal actual no coincide con el calculado: usa «Actualizar precios y categorías de descuento» en Importar productos.</p>
                <?php endif; ?>
            <?php endif; ?>
            <p class="description" style="margin:4px 0;">Del catálogo B2B<?= $updated ? esc_html(', actualizado el ' . wp_date('Y-m-d H:i', $updated)) : '' ?>. No se edita aquí.</p>
        </div>
        <?php
    }
}

HSY_HOOK_EDITOR::init();
