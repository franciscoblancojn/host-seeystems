<?php

/**
 * Configuración del plugin (opción HSY_CONFIG).
 *
 * DEFAULTS es la única lista de ajustes: get() la mezcla con lo guardado y sanitize()
 * normaliza lo que llega del formulario. Un ajuste nuevo se agrega en los dos.
 */
class HSY_USE_DATA_CONFIG extends HSY_USE_DATA_BASE
{
    protected $KEY = HSY_CONFIG;

    const DEFAULTS = [
        // Conexión con el catálogo de origen.
        'base_url' => '',          // raíz del catálogo, p. ej. https://host.ejemplo.com/~tienda/
        'login_path' => 'auth/login',
        'catalog_path' => 'catalog',
        'email' => '',
        'password' => '',
        // Importación.
        'status' => 'draft',       // estado de los productos nuevos: draft | pending | publish
        'update_existing' => 1,    // volver a importar actualiza el producto ya creado
        'update_fields' => ['price', 'stock'], // qué se actualiza en los existentes (el precio mayorista, siempre)
        'import_images' => 1,
        'category' => '',          // categoría si el género no tiene regla ('' = ninguna)
        'category_map' => [],      // [GÉNERO => [term_id…]]; vacío = HSY_CATEGORIES::default_map()
        'jewelry_words' => 'joyas, joyería, jewelry', // palabras de la marca que la hacen «joya»
        'jewelry_category' => 'Joyas', // categoría raíz de las joyas (se crea con Mujer / Hombre dentro)
        'name_template' => '{nombre} {genero} {marca} {ref}', // variables: {tipo} {nombre} {genero} {marca} {ref}
        'type_watch' => 'Reloj',   // {tipo} de los relojes
        'type_jewelry' => '',      // {tipo} de las joyas ('' = el nombre del catálogo ya es el tipo: «Aretes»)
        // Stock mínimo: con min_stock unidades o menos el producto importado queda con stock 0.
        'min_stock' => 3,
        // Precio y categorías de descuento (HSY_PRICING).
        'profit_margin' => 20,      // utilidad mínima sobre el mayorista, en % (precio con descuentos > mayorista × 1,20)
        'global_discount' => 27,    // descuento que otro plugin aplica a todos, en %
        'discount_combine' => 'sum', // sum: 27 % + 5 % = 32 %  ·  chain: 1 − 0,73 × 0,95
        'price_round' => 100,       // redondeo hacia arriba cuando el precio se ajusta por utilidad mínima
        // Envío por defecto (en las unidades de la tienda): solo se rellenan los datos vacíos (HSY_HOOK_SHIPPING).
        'default_length' => 20,
        'default_width' => 15,
        'default_height' => 15,
        'default_weight' => 1,
        'discount_tiers' => [],     // [['min' => 50, 'slug' => 'ofertas-5', 'percent' => 5], …]; vacío = HSY_PRICING::DEFAULT_TIERS
        // Descripciones pendientes (HSY_CONTENT): IA del plugin IA Conector y/o Google (Serper.dev).
        'ai_enabled' => 0,
        'ai_connections' => [],    // keys de conexiones de IA Conector, en orden de prioridad (la 1.ª que responda)
        'ai_prompt_description' => '', // prompt de la descripción ('' = HSY_CONTENT::DEFAULT_PROMPT_DESCRIPTION)
        'description_closing' => '', // cierre de la descripción ('' = HSY_LAYOUT::DEFAULT_CLOSING); admite {tienda} y demás variables
        'ai_prompt_short' => '',   // prompt de la descripción corta ('' = HSY_CONTENT::DEFAULT_PROMPT_SHORT)
        'ai_style' => 1,           // la IA elige el estilo (subcategorías: Análogo, Digital…)
        'ai_prompt_style' => '',   // prompt del estilo ('' = HSY_CONTENT::DEFAULT_PROMPT_STYLE)
        'describe' => 0,           // buscar en Google (contexto para la IA o descripción directa sin IA)
        'serper_key' => '',
    ];

    const STATUSES = ['draft' => 'Borrador', 'pending' => 'Pendiente de revisión', 'publish' => 'Publicado'];

    const UPDATE_FIELDS = [
        'name' => 'Nombre',
        'price' => 'Precio y categoría de descuento',
        'stock' => 'Inventario',
        'attributes' => 'Características y marca',
        'images' => 'Imágenes',
    ];

    /** Ajustes secretos: nunca se imprimen; vacío en el formulario = conservar. */
    const SECRETS = ['password', 'serper_key'];

    /** Largo máximo de cada prompt. */
    const PROMPT_MAX = 8000;

    public function get()
    {
        $config = array_merge(self::DEFAULTS, $this->DATA);
        // Hasta 1.1.0 había una sola conexión (ai_connection): pasa a ser la primera de la lista.
        if (!$config['ai_connections'] && !empty($this->DATA['ai_connection'])) {
            $config['ai_connections'] = [(string) $this->DATA['ai_connection']];
        }
        $config['ai_connections'] = array_values(array_unique(array_filter(array_map('strval', (array) $config['ai_connections']), 'strlen')));
        // Prompt vacío = el de por defecto (así se restaura vaciando el campo).
        if ('' === trim((string) $config['ai_prompt_description'])) {
            $config['ai_prompt_description'] = HSY_CONTENT::DEFAULT_PROMPT_DESCRIPTION;
        }
        if ('' === trim((string) $config['ai_prompt_short'])) {
            $config['ai_prompt_short'] = HSY_CONTENT::DEFAULT_PROMPT_SHORT;
        }
        if ('' === trim((string) $config['ai_prompt_style'])) {
            $config['ai_prompt_style'] = HSY_CONTENT::DEFAULT_PROMPT_STYLE;
        }
        return $config;
    }

    /**
     * Prompt del formulario: texto libre (puede llevar etiquetas como <p> como instrucción), sin
     * caracteres de control. Igual al de por defecto = se guarda vacío, para seguir sus mejoras.
     */
    public static function prompt($value, $default)
    {
        $value = str_replace("\r\n", "\n", wp_check_invalid_utf8((string) $value));
        $value = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value));
        $value = mb_substr($value, 0, self::PROMPT_MAX);
        return $value === trim($default) ? '' : $value;
    }

    /**
     * Lo guardado tal cual (sin rellenar los prompts por defecto), para reescribirlo.
     */
    public function get_saved()
    {
        return array_merge(self::DEFAULTS, $this->DATA);
    }

    public function set($data)
    {
        $this->DATA = $data;
        $this->save();
    }

    /**
     * ¿Hay datos suficientes para conectar?
     */
    public static function is_connected_config($config)
    {
        return '' !== $config['base_url'] && '' !== $config['email'] && '' !== $config['password'];
    }

    /**
     * Normaliza lo que llega del formulario de ajustes (ya con wp_unslash).
     * Los formularios van por pestañas: lo que no llega conserva su valor guardado.
     */
    public static function sanitize($input)
    {
        $input = (array) $input;
        $saved = new self();
        $current = array_merge(self::DEFAULTS, $saved->DATA); // lo guardado, sin rellenar los prompts por defecto
        if (!$current['ai_connections'] && !empty($saved->DATA['ai_connection'])) {
            $current['ai_connections'] = [(string) $saved->DATA['ai_connection']]; // migración desde 1.1.0
        }
        $out = $current;
        $has = function ($key) use ($input) {
            return array_key_exists($key, $input);
        };

        // Pestaña Conexión.
        if ($has('base_url')) {
            $base_url = esc_url_raw(trim($input['base_url']), ['https', 'http']);
            $out['base_url'] = '' !== $base_url ? trailingslashit($base_url) : '';
            $out['login_path'] = self::path($input['login_path'] ?? '', self::DEFAULTS['login_path']);
            $out['catalog_path'] = self::path($input['catalog_path'] ?? '', self::DEFAULTS['catalog_path']);
            $out['email'] = sanitize_email($input['email'] ?? '');
        }

        // Pestaña Importación (marcada con el campo oculto import_tab).
        if (!empty($input['import_tab'])) {
            $status = sanitize_key($input['status'] ?? '');
            $template = sanitize_text_field($input['name_template'] ?? '');
            $out['status'] = isset(self::STATUSES[$status]) ? $status : self::DEFAULTS['status'];
            $out['update_existing'] = empty($input['update_existing']) ? 0 : 1;
            $out['update_fields'] = array_values(array_intersect(array_keys(self::UPDATE_FIELDS), (array) ($input['update_fields'] ?? [])));
            $out['import_images'] = empty($input['import_images']) ? 0 : 1;
            $out['category'] = sanitize_text_field($input['category'] ?? '');
            $map = [];
            foreach ((array) ($input['category_map'] ?? []) as $gender => $ids) {
                $gender = HSY_CATEGORIES::gender_key($gender);
                if ('' !== $gender) {
                    $map[$gender] = array_values(array_unique(array_filter(array_map('absint', (array) $ids))));
                }
            }
            $out['category_map'] = $map;
            $out['jewelry_words'] = sanitize_text_field($input['jewelry_words'] ?? '');
            $out['jewelry_category'] = sanitize_text_field($input['jewelry_category'] ?? '') ?: self::DEFAULTS['jewelry_category'];
            $out['ai_style'] = empty($input['ai_style']) ? 0 : 1;
            $out['ai_prompt_style'] = self::prompt($input['ai_prompt_style'] ?? '', HSY_CONTENT::DEFAULT_PROMPT_STYLE);
            $out['name_template'] = false !== strpos($template, '{nombre}') ? $template : self::DEFAULTS['name_template'];
            $out['type_watch'] = sanitize_text_field($input['type_watch'] ?? self::DEFAULTS['type_watch']);
            $out['type_jewelry'] = sanitize_text_field($input['type_jewelry'] ?? self::DEFAULTS['type_jewelry']);
            $out['min_stock'] = max(0, (int) ($input['min_stock'] ?? self::DEFAULTS['min_stock']));
            $out['profit_margin'] = max(0, (float) str_replace(',', '.', (string) ($input['profit_margin'] ?? 20)));
            $out['global_discount'] = min(95, max(0, (float) str_replace(',', '.', (string) ($input['global_discount'] ?? 27))));
            $out['discount_combine'] = 'chain' === ($input['discount_combine'] ?? '') ? 'chain' : 'sum';
            $out['price_round'] = max(1, (int) ($input['price_round'] ?? 100));
            $tiers = [];
            foreach ((array) ($input['discount_tiers'] ?? []) as $t) {
                $slug = sanitize_title($t['slug'] ?? '');
                if ('' === $slug || !isset($t['min']) || '' === (string) $t['min']) {
                    continue;
                }
                $tiers[] = [
                    'min' => max(0, (float) str_replace(',', '.', (string) $t['min'])),
                    'slug' => $slug,
                    'percent' => max(0, (float) str_replace(',', '.', (string) ($t['percent'] ?? 0))),
                ];
            }
            $out['discount_tiers'] = $tiers;
            foreach (['default_length', 'default_width', 'default_height', 'default_weight'] as $key) {
                $value = str_replace(',', '.', trim((string) ($input[$key] ?? '')));
                $out[$key] = '' === $value ? '' : max(0, (float) $value); // vacío = no rellenar ese dato
            }
            $out['describe'] = empty($input['describe']) ? 0 : 1;
            $out['ai_enabled'] = empty($input['ai_enabled']) ? 0 : 1;
            // Orden del formulario = prioridad. Solo las marcadas.
            $out['ai_connections'] = array_values(array_unique(array_filter(array_map('sanitize_text_field', (array) ($input['ai_connections'] ?? [])), 'strlen')));
            $out['ai_prompt_description'] = self::prompt($input['ai_prompt_description'] ?? '', HSY_CONTENT::DEFAULT_PROMPT_DESCRIPTION);
            $out['description_closing'] = wp_kses(trim((string) ($input['description_closing'] ?? '')), HSY_LAYOUT::INLINE_TAGS);
            if ($out['description_closing'] === HSY_LAYOUT::DEFAULT_CLOSING) {
                $out['description_closing'] = '';
            }
            $out['ai_prompt_short'] = self::prompt($input['ai_prompt_short'] ?? '', HSY_CONTENT::DEFAULT_PROMPT_SHORT);
        }

        // Secretos: vacío = conservar; casilla <clave>_clear = borrar.
        foreach (self::SECRETS as $key) {
            $value = trim((string) ($input[$key] ?? ''));
            if ('' !== $value) {
                $out[$key] = 'password' === $key ? (string) $input[$key] : sanitize_text_field($value);
            }
            if (!empty($input[$key . '_clear'])) {
                $out[$key] = '';
            }
        }

        return array_intersect_key($out, self::DEFAULTS);
    }

    private static function path($value, $default)
    {
        $value = trim(sanitize_text_field((string) $value), " /");
        return '' === $value ? $default : $value;
    }
}
