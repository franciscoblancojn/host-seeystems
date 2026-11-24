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
        'category' => '',          // categoría para los productos nuevos ('' = ninguna)
        'name_template' => '{nombre} {genero} {marca} {ref}', // variables: {nombre} {genero} {marca} {ref}
        // Stock mínimo: con min_stock unidades o menos el producto importado queda con stock 0.
        'min_stock' => 3,
        // Descripciones pendientes (HSY_CONTENT): IA del plugin IA Conector y/o Google (Serper.dev).
        'ai_enabled' => 0,
        'ai_connection' => '',     // key de la conexión en IA Conector
        'ai_prompt_description' => '', // prompt de la descripción ('' = HSY_CONTENT::DEFAULT_PROMPT_DESCRIPTION)
        'ai_prompt_short' => '',   // prompt de la descripción corta ('' = HSY_CONTENT::DEFAULT_PROMPT_SHORT)
        'content_after_import' => 1, // generar lo pendiente justo después de importar
        'describe' => 0,           // buscar en Google (contexto para la IA o descripción directa sin IA)
        'serper_key' => '',
    ];

    const STATUSES = ['draft' => 'Borrador', 'pending' => 'Pendiente de revisión', 'publish' => 'Publicado'];

    const UPDATE_FIELDS = [
        'name' => 'Nombre',
        'price' => 'Precio (PVP)',
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
        // Prompt vacío = el de por defecto (así se restaura vaciando el campo).
        if ('' === trim((string) $config['ai_prompt_description'])) {
            $config['ai_prompt_description'] = HSY_CONTENT::DEFAULT_PROMPT_DESCRIPTION;
        }
        if ('' === trim((string) $config['ai_prompt_short'])) {
            $config['ai_prompt_short'] = HSY_CONTENT::DEFAULT_PROMPT_SHORT;
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
            $out['name_template'] = false !== strpos($template, '{nombre}') ? $template : self::DEFAULTS['name_template'];
            $out['min_stock'] = max(0, (int) ($input['min_stock'] ?? self::DEFAULTS['min_stock']));
            $out['describe'] = empty($input['describe']) ? 0 : 1;
            $out['ai_enabled'] = empty($input['ai_enabled']) ? 0 : 1;
            $out['ai_connection'] = sanitize_text_field($input['ai_connection'] ?? '');
            $out['ai_prompt_description'] = self::prompt($input['ai_prompt_description'] ?? '', HSY_CONTENT::DEFAULT_PROMPT_DESCRIPTION);
            $out['ai_prompt_short'] = self::prompt($input['ai_prompt_short'] ?? '', HSY_CONTENT::DEFAULT_PROMPT_SHORT);
            $out['content_after_import'] = empty($input['content_after_import']) ? 0 : 1;
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
