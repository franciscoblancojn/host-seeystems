<?php

use franciscoblancojn\wordpress_utils\FWUSystemLog;

class HSEY_API_PRODUCTS
{
    const MAX_PAGES = 50;

    /**
     * Recorre el catálogo página por página y guarda los productos en HSEY_PRODUCTS.
     */
    public static function sync()
    {
        $CONFIG = new HSEY_USE_DATA_CONFIG();
        $client = new HSEY_CLIENT();
        $path = ($CONFIG->get()['catalog_path'] ?? '') ?: '/catalog';

        // El catálogo tiene decenas de páginas
        if (function_exists('set_time_limit')) {
            set_time_limit(300);
        }

        $products = [];
        $visited = [];
        for ($i = 0; $i < self::MAX_PAGES && $path !== '' && !isset($visited[$path]); $i++) {
            $visited[$path] = true;
            $res = $client->get($path);
            if ($res['status'] !== 'ok') {
                return $res;
            }
            if (in_array($res['code'], [301, 302, 303], true)) {
                $path = $res['location'];
                continue;
            }
            $products = array_merge($products, HSEY_PARSER::parse($res['body']));
            $path = HSEY_PARSER::nextPage($res['body']);
        }

        (new HSEY_USE_DATA_PRODUCTS())->set([
            'date' => current_time('mysql'),
            'items' => $products,
        ]);
        FWUSystemLog::add(HSEY_KEY, [
            'type' => 'sync_products',
            'count' => count($products),
        ]);

        if (empty($products)) {
            return ['status' => 'error', 'message' => 'Se cargó el catálogo pero no se detectaron productos. Revisa la pestaña "HTML" para ver qué devuelve la plataforma.'];
        }
        return ['status' => 'ok', 'message' => count($products) . ' productos traídos.'];
    }
}
