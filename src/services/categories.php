<?php

/**
 * Categorías de los productos importados: única fuente de qué categorías lleva cada producto.
 *
 * - Por género (atributo «Género» del catálogo): ajuste category_map [GÉNERO => [term_id…]]. Si no
 *   está configurado, default_map() lo deduce por nombre: DAMA/MUJER → Relojes + Mujer,
 *   HOMBRE → Relojes + Hombre, UNISEX → Relojes + Hombre + Mujer, NIÑO/NIÑOS → Relojes + Niños.
 * - Joyas: si la marca contiene una de las palabras de jewelry_words («joyas», «joyería»…), el
 *   producto va a jewelry_category («Joyas») → Mujer / Hombre según el género; se crean si faltan.
 * - Estilo (Análogo, Digital, Deportivo…): subcategorías de las categorías asignadas. Las elige la
 *   IA en HSY_CONTENT (meta HSY_STYLE_META: pending → done | none).
 * - Solo se asignan si el producto no tiene categorías (has_categories): las ya asignadas, a mano o
 *   por el plugin, no se vuelven a tocar al reimportar.
 */
class HSY_CATEGORIES
{
    const TAXONOMY = 'product_cat';

    /** @var WP_Term[]|null */
    private static $terms = null;

    /**
     * Clave de un género: «Dama», «DAMA», «dáma » → «DAMA».
     */
    public static function gender_key($gender)
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', remove_accents((string) $gender))));
    }

    /**
     * Mapa por defecto, deducido de las categorías existentes por nombre.
     */
    public static function default_map()
    {
        $root = self::find_by_name('relojes', 0);
        // Sin «Relojes», solo categorías de primer nivel (no la «Mujer» de dentro de «Joyas» u otra rama).
        $parent = $root ? (int) $root->term_id : 0;
        $women = self::find_by_name('mujer', $parent) ?: self::find_by_name('dama', $parent);
        $men = self::find_by_name('hombre', $parent) ?: self::find_by_name('caballero', $parent);
        $kids = self::find_by_name('ninos', $parent) ?: self::find_by_name('nino', $parent);
        $ids = function () {
            return array_values(array_filter(array_map(function ($term) {
                return $term ? (int) $term->term_id : 0;
            }, func_get_args())));
        };
        return [
            'DAMA' => $ids($root, $women),
            'MUJER' => $ids($root, $women),
            'HOMBRE' => $ids($root, $men),
            'UNISEX' => $ids($root, $men, $women),
            'NINO' => $ids($root, $kids),
            'NINOS' => $ids($root, $kids),
        ];
    }

    /**
     * Mapa en uso: el configurado o, si está vacío, el por defecto.
     */
    public static function map($config)
    {
        $map = array_filter((array) ($config['category_map'] ?? []));
        return $map ? $map : self::default_map();
    }

    /**
     * ¿La marca es de joyas? (contiene una de las palabras de jewelry_words).
     */
    public static function is_jewelry($brand, $config)
    {
        $brand = ' ' . HSY_BRANDS::key_words($brand) . ' ';
        foreach (self::words($config['jewelry_words'] ?? '') as $word) {
            if ('' !== $word && false !== strpos($brand, ' ' . $word . ' ')) {
                return true;
            }
        }
        return false;
    }

    /**
     * IDs de categorías para un producto normalizado del catálogo. Puede crear «Joyas» y sus géneros.
     */
    public static function ids_for($item, $config)
    {
        $gender = self::gender_key($item['attributes']['Género'] ?? '');
        if (self::is_jewelry($item['brand'] ?? '', $config)) {
            return self::jewelry_ids($gender, $config);
        }
        $map = self::map($config);
        $ids = array_map('intval', (array) ($map[$gender] ?? []));
        if (!$ids && '' !== trim((string) ($config['category'] ?? ''))) {
            $ids = HSY_IMPORTER::category_ids([$config['category']]);
        }
        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Estilos posibles para un producto: subcategorías de sus categorías asignadas más profundas
     * (las que no tienen otra categoría asignada debajo). Para Relojes + Mujer → los hijos de Mujer;
     * para un unisex (Relojes + Hombre + Mujer) → los de Hombre y Mujer, agrupados por nombre.
     * [nombre => [term_id…]]. Sin las de ofertas.
     */
    public static function style_options($product)
    {
        $assigned = array_map('intval', $product->get_category_ids());
        $parents_of_assigned = [];
        foreach (self::terms() as $term) {
            if (in_array((int) $term->term_id, $assigned, true)) {
                $parents_of_assigned[] = (int) $term->parent;
            }
        }
        $options = [];
        foreach ($assigned as $id) {
            if (in_array($id, $parents_of_assigned, true)) {
                continue; // tiene una asignada debajo (p. ej. Relojes): sus hijos son géneros, no estilos
            }
            foreach (self::terms() as $term) {
                if ((int) $term->parent === $id && !self::is_offer($term) && !in_array((int) $term->term_id, $assigned, true)) {
                    $options[$term->name][] = (int) $term->term_id;
                }
            }
        }
        ksort($options, SORT_NATURAL | SORT_FLAG_CASE);
        return $options;
    }

    /**
     * Añade al producto los estilos elegidos (por nombre, comparando claves normalizadas).
     * Devuelve los nombres aplicados.
     */
    public static function apply_styles($product, $names)
    {
        $options = self::style_options($product);
        $by_key = [];
        foreach ($options as $name => $ids) {
            $by_key[HSY_BRANDS::plain_key($name)] = [$name, $ids];
        }
        $applied = [];
        $ids = $product->get_category_ids();
        foreach ((array) $names as $name) {
            $key = HSY_BRANDS::plain_key($name);
            if (isset($by_key[$key])) {
                $applied[] = $by_key[$key][0];
                $ids = array_merge($ids, $by_key[$key][1]);
            }
        }
        if ($applied) {
            $product->set_category_ids(array_values(array_unique(array_map('intval', $ids))));
        }
        return array_values(array_unique($applied));
    }

    /**
     * ¿El producto ya tiene categorías? (sin contar la de por defecto, «Sin categorizar»).
     * Si ya tiene, el plugin no las vuelve a tocar.
     */
    public static function has_categories($product)
    {
        $ignored = self::ignored_ids();
        foreach ($product->get_category_ids() as $id) {
            if (!in_array((int) $id, $ignored, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Categorías que no cuentan como «tiene categorías»: la de por defecto de WooCommerce y las de ofertas
     * (Ofertas, Ofertas 5 %…; las de descuento las pone HSY_PRICING y no sustituyen a Relojes / Mujer…).
     */
    public static function ignored_ids()
    {
        $ids = [(int) get_option('default_product_cat', 0)];
        foreach (self::terms() as $term) {
            if (self::is_offer($term)) {
                $ids[] = (int) $term->term_id;
            }
        }
        return array_values(array_unique(array_filter($ids)));
    }

    /** ignored_ids() como lista SQL de enteros («1,2,3»; «0» si no hay). */
    public static function ignored_sql()
    {
        $ids = self::ignored_ids();
        return $ids ? implode(',', array_map('intval', $ids)) : '0';
    }

    /**
     * Asigna las categorías a un producto importado que no tiene (por su género / joyas), sin guardar.
     * Deja el estilo pendiente si hay subcategorías. Devuelve los nombres asignados ([] si no hay regla).
     */
    public static function assign_missing($product, $config)
    {
        if (self::has_categories($product)) {
            return [];
        }
        $brands = taxonomy_exists('product_brand') ? wp_get_post_terms($product->get_id(), 'product_brand', ['fields' => 'names']) : [];
        $gender = '';
        foreach ($product->get_attributes() as $attribute) {
            if ($attribute instanceof WC_Product_Attribute && 'genero' === sanitize_title($attribute->get_name())) {
                $gender = implode(' ', $attribute->get_options());
            }
        }
        $ids = self::ids_for([
            'brand' => is_array($brands) ? implode(' ', $brands) : '',
            'attributes' => ['Género' => $gender],
        ], $config);
        if (!$ids) {
            return [];
        }
        $product->set_category_ids($ids);
        if ('done' !== $product->get_meta(HSY_STYLE_META)) {
            $product->update_meta_data(HSY_STYLE_META, self::style_options($product) ? 'pending' : 'none');
        }
        return array_map(function ($id) {
            $term = get_term($id, self::TAXONOMY);
            return $term instanceof WP_Term ? $term->name : '';
        }, $ids);
    }

    /**
     * Categorías de varios productos en una consulta: [product_id => ['names' => [todas], 'has' => bool]].
     * «has» = tiene alguna además de la de por defecto y las de ofertas (misma regla que has_categories()).
     */
    public static function names_for($product_ids)
    {
        $product_ids = array_filter(array_map('intval', (array) $product_ids));
        if (!$product_ids) {
            return [];
        }
        $ignored = self::ignored_ids();
        $terms = wp_get_object_terms($product_ids, self::TAXONOMY, ['fields' => 'all_with_object_id', 'orderby' => 'parent']);
        $out = [];
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $id = (int) $term->object_id;
                if (!isset($out[$id])) {
                    $out[$id] = ['names' => [], 'has' => false];
                }
                // Se muestran todas (también la de por defecto, que puede ser «Relojes»); «has» = alguna además de esa.
                $out[$id]['names'][] = $term->name;
                if (!in_array((int) $term->term_id, $ignored, true)) {
                    $out[$id]['has'] = true;
                }
            }
        }
        return $out;
    }

    /**
     * Árbol de categorías para el formulario: [['term' => WP_Term, 'depth' => n]] en orden jerárquico.
     */
    public static function tree()
    {
        $children = [];
        foreach (self::terms() as $term) {
            $children[(int) $term->parent][] = $term;
        }
        $out = [];
        $walk = function ($parent, $depth) use (&$walk, &$out, $children) {
            $list = $children[$parent] ?? [];
            usort($list, function ($a, $b) {
                return strnatcasecmp($a->name, $b->name);
            });
            foreach ($list as $term) {
                $out[] = ['term' => $term, 'depth' => $depth];
                $walk((int) $term->term_id, $depth + 1);
            }
        };
        $walk(0, 0);
        return $out;
    }

    public static function is_offer($term)
    {
        return false !== strpos(HSY_BRANDS::plain_key($term->name), 'oferta');
    }

    // ------------------------------------------------------------------ internos

    private static function jewelry_ids($gender, $config)
    {
        $root_name = trim((string) ($config['jewelry_category'] ?? '')) ?: 'Joyas';
        $root = self::ensure($root_name, 0);
        if (!$root) {
            return [];
        }
        $ids = [$root];
        $genders = [
            'DAMA' => ['Mujer'],
            'MUJER' => ['Mujer'],
            'HOMBRE' => ['Hombre'],
            'UNISEX' => ['Hombre', 'Mujer'],
        ];
        foreach ($genders[$gender] ?? [] as $name) {
            $child = self::ensure($name, $root);
            if ($child) {
                $ids[] = $child;
            }
        }
        return $ids;
    }

    /**
     * term_id de la categoría $name bajo $parent; la crea si no existe.
     */
    private static function ensure($name, $parent)
    {
        $term = self::find_by_name(HSY_BRANDS::plain_key($name), $parent);
        if ($term) {
            return (int) $term->term_id;
        }
        $created = wp_insert_term($name, self::TAXONOMY, ['parent' => (int) $parent]);
        if (is_wp_error($created)) {
            // Slug repetido en otra rama (p. ej. «mujer»): WordPress pide un slug único.
            $created = wp_insert_term($name, self::TAXONOMY, [
                'parent' => (int) $parent,
                'slug' => sanitize_title(get_term((int) $parent, self::TAXONOMY)->slug . '-' . $name),
            ]);
        }
        if (is_wp_error($created)) {
            HSY_LOG_SERVICE::add('categoria_error', ['name' => $name, 'parent' => $parent, 'message' => $created->get_error_message()]);
            return 0;
        }
        self::$terms = null;
        HSY_LOG_SERVICE::add('categoria_creada', ['name' => $name, 'parent' => $parent, 'term_id' => $created['term_id']]);
        return (int) $created['term_id'];
    }

    /**
     * Categoría cuyo nombre normalizado es $key, bajo $parent (null = cualquier padre).
     */
    private static function find_by_name($key, $parent)
    {
        foreach (self::terms() as $term) {
            if (HSY_BRANDS::plain_key($term->name) === $key && (null === $parent || (int) $term->parent === (int) $parent)) {
                return $term;
            }
        }
        return null;
    }

    private static function words($csv)
    {
        return array_filter(array_map(function ($w) {
            return HSY_BRANDS::key_words($w);
        }, explode(',', (string) $csv)));
    }

    private static function terms()
    {
        if (null === self::$terms) {
            $terms = get_terms(['taxonomy' => self::TAXONOMY, 'hide_empty' => false]);
            self::$terms = is_wp_error($terms) ? [] : $terms;
        }
        return self::$terms;
    }
}
