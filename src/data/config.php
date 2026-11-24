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
        'update_fields' => ['name', 'price', 'stock'], // qué se actualiza en los existentes
        'import_images' => 1,
        'import_categories' => 1,
        'category_parent' => '',   // categoría padre de las categorías por marca ('' = de primer nivel)
        'name_template' => '{nombre}', // {nombre} {marca} {ref}
        'price_source' => 'pvp',   // pvp = precio de venta al público | cost = precio del cliente
        'price_factor' => 1,       // multiplicador del precio elegido (1 = igual; 1.3 = +30 %)
    ];

    const PRICE_SOURCES = [
        'pvp' => 'PVP del catálogo',
        'cost' => 'Precio del cliente (costo)',
    ];

    const STATUSES = ['draft' => 'Borrador', 'pending' => 'Pendiente de revisión', 'publish' => 'Publicado'];

    const UPDATE_FIELDS = [
        'name' => 'Nombre',
        'description' => 'Descripción',
        'price' => 'Precio',
        'stock' => 'Inventario',
        'attributes' => 'Atributos y marca',
        'images' => 'Imágenes',
        'categories' => 'Categorías',
    ];

    public function get()
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
     * La contraseña vacía conserva la guardada (el formulario nunca la muestra).
     */
    public static function sanitize($input)
    {
        $input = (array) $input;
        $current = (new self())->get();

        $base_url = esc_url_raw(trim($input['base_url'] ?? ''), ['https', 'http']);
        if ('' !== $base_url) {
            $base_url = trailingslashit($base_url);
        }

        $password = (string) ($input['password'] ?? '');
        if ('' === $password) {
            $password = $current['password'];
        }
        if (!empty($input['password_clear'])) {
            $password = '';
        }

        $status = sanitize_key($input['status'] ?? '');
        $fields = array_values(array_intersect(array_keys(self::UPDATE_FIELDS), (array) ($input['update_fields'] ?? [])));
        $factor = (float) str_replace(',', '.', (string) ($input['price_factor'] ?? '1'));
        $source = sanitize_key($input['price_source'] ?? '');
        $template = sanitize_text_field($input['name_template'] ?? '');

        return [
            'base_url' => $base_url,
            'login_path' => self::path($input['login_path'] ?? '', self::DEFAULTS['login_path']),
            'catalog_path' => self::path($input['catalog_path'] ?? '', self::DEFAULTS['catalog_path']),
            'email' => sanitize_email($input['email'] ?? ''),
            'password' => $password,
            'status' => isset(self::STATUSES[$status]) ? $status : self::DEFAULTS['status'],
            'update_existing' => empty($input['update_existing']) ? 0 : 1,
            'update_fields' => $fields,
            'import_images' => empty($input['import_images']) ? 0 : 1,
            'import_categories' => empty($input['import_categories']) ? 0 : 1,
            'category_parent' => sanitize_text_field($input['category_parent'] ?? ''),
            'name_template' => false !== strpos($template, '{nombre}') ? $template : self::DEFAULTS['name_template'],
            'price_source' => isset(self::PRICE_SOURCES[$source]) ? $source : self::DEFAULTS['price_source'],
            'price_factor' => $factor > 0 ? round($factor, 4) : 1,
        ];
    }

    private static function path($value, $default)
    {
        $value = trim(sanitize_text_field((string) $value), " /");
        return '' === $value ? $default : $value;
    }
}
