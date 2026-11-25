<?php

/**
 * Marcas (taxonomía product_brand): única fuente de cómo se empareja la marca del catálogo
 * con una marca de la tienda, para no crear duplicados.
 *
 * Se comparan claves normalizadas: sin mayúsculas, acentos, espacios ni signos, y sin palabras
 * de relleno (FILLER: «reloj», «relojes», «relog»…). Así «ARMANI EXCHANGE», «Armani  Exchange»,
 * «Armaní Exchange» y «Relojes Armani Exchange» son la misma marca. Otras palabras sí cuentan:
 * «Michael Kors Joyas» es una marca distinta de «Michael Kors».
 *
 * Orden: primero por nombre y después por slug (el slug no cambia al renombrar una marca). Si
 * encajan varias, gana la que tiene más productos. Si no encaja ninguna, se crea.
 */
class HSY_BRANDS
{
    const TAXONOMY = 'product_brand';

    /** Palabras que no distinguen una marca (se quitan antes de comparar). */
    const FILLER = ['reloj', 'relojes', 'relog', 'relogs', 'watch', 'watches', 'marca'];

    /** @var WP_Term[]|null */
    private static $terms = null;

    public static function available()
    {
        return taxonomy_exists(self::TAXONOMY);
    }

    /**
     * Clave de comparación: «Relojes Armaní-Exchange» → «armaniexchange».
     */
    public static function key($name)
    {
        $name = remove_accents(html_entity_decode((string) $name, ENT_QUOTES, 'UTF-8'));
        $name = mb_strtolower($name, 'UTF-8');
        $words = preg_split('/[^a-z0-9]+/', $name, -1, PREG_SPLIT_NO_EMPTY);
        $words = array_diff($words, self::FILLER);
        return implode('', $words);
    }

    /**
     * Marca de la tienda que corresponde a $name, o null.
     */
    public static function find($name)
    {
        $key = self::key($name);
        if ('' === $key) {
            return null;
        }
        foreach (['name', 'slug'] as $field) {
            $matches = [];
            foreach (self::terms() as $term) {
                if (self::key($term->$field) === $key) {
                    $matches[] = $term;
                }
            }
            if ($matches) {
                usort($matches, function ($a, $b) {
                    return (int) $b->count - (int) $a->count;
                });
                return $matches[0];
            }
        }
        return null;
    }

    /**
     * Asigna la marca al producto (la existente que encaje o una nueva). Devuelve el term_id o WP_Error.
     */
    public static function assign($product_id, $name)
    {
        if (!self::available()) {
            return new WP_Error('hsy_brand', 'No existe la taxonomía de marcas (product_brand): la marca ' . $name . ' no se asignó.');
        }
        $term = self::find($name);
        if ($term) {
            $term_id = (int) $term->term_id;
        } else {
            $created = wp_insert_term($name, self::TAXONOMY);
            if (is_wp_error($created)) {
                return $created;
            }
            $term_id = (int) $created['term_id'];
            self::$terms = null;
            HSY_LOG_SERVICE::add('marca_creada', ['marca' => $name, 'term_id' => $term_id]);
        }
        $set = wp_set_object_terms($product_id, [$term_id], self::TAXONOMY);
        return is_wp_error($set) ? $set : $term_id;
    }

    /**
     * Marcas del catálogo guardado y a qué marca de la tienda irán:
     * [['brand' => …, 'products' => n, 'term' => WP_Term|null, 'exact' => bool]].
     */
    public static function catalog_report()
    {
        $counts = [];
        foreach ((new HSY_USE_DATA_CATALOG())->items() as $item) {
            $brand = (string) ($item['brand'] ?? '');
            if ('' !== $brand) {
                $counts[$brand] = ($counts[$brand] ?? 0) + 1;
            }
        }
        ksort($counts, SORT_NATURAL | SORT_FLAG_CASE);
        $report = [];
        foreach ($counts as $brand => $n) {
            $term = self::available() ? self::find($brand) : null;
            $report[] = [
                'brand' => $brand,
                'products' => $n,
                'term' => $term,
                'exact' => $term && $term->name === $brand,
            ];
        }
        return $report;
    }

    /**
     * Todas las marcas de la tienda, también las vacías (una consulta por petición).
     */
    private static function terms()
    {
        if (null === self::$terms) {
            $terms = get_terms(['taxonomy' => self::TAXONOMY, 'hide_empty' => false]);
            self::$terms = is_wp_error($terms) ? [] : $terms;
        }
        return self::$terms;
    }
}
