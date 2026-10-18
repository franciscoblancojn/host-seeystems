<?php

/**
 * Convierte el HTML del catálogo en una lista de productos.
 * Cada tarjeta `.product-card` trae el producto completo como JSON en
 * onclick="openProductModal({...})" (id, ref, nombre, marca, precio, pvp, stock, imgs, ...).
 */
class HSEY_PARSER
{
    const CARD = "//*[contains(concat(' ', normalize-space(@class), ' '), ' product-card ')]";

    public static function parse($html)
    {
        if (trim($html) === '') {
            return [];
        }
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $products = [];
        foreach ($xpath->query(self::CARD) as $card) {
            $product = self::parseCardJson($card->getAttribute('onclick'));
            if ($product === null) {
                continue;
            }
            $products[] = $product;
        }
        return $products;
    }

    private static function parseCardJson($onclick)
    {
        $start = strpos($onclick, '{');
        $end = strrpos($onclick, '}');
        if ($start === false || $end === false) {
            return null;
        }
        $product = json_decode(substr($onclick, $start, $end - $start + 1), true);
        return is_array($product) && isset($product['id']) ? $product : null;
    }

    /**
     * URL de la siguiente página del catálogo (enlace "Siguiente" de .pagination), si existe.
     */
    public static function nextPage($html)
    {
        if (!preg_match('/<a[^>]+class="page-link"[^>]+href="([^"]+)"[^>]*>\s*Siguiente/i', $html, $m)) {
            return '';
        }
        return strtok(html_entity_decode($m[1]), '#');
    }
}
