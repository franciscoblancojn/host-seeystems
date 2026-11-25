<?php

/**
 * Descripción y descripción corta de los productos importados que las tienen pendientes.
 *
 * Única fuente de la regla «pendiente» y de quién escribe esos textos:
 * - Pendiente = producto importado sin descripción, o cuya descripción corta sigue siendo la
 *   lista automática de características (meta HSY_SHORT_AUTO_META = 'yes'). Lo escrito a mano
 *   o generado antes nunca se reemplaza.
 * - Con IA (plugin IA Conector, clase IACON_AI) se generan las dos, una llamada por campo, con las
 *   conexiones de ai_connections en orden de prioridad (si una falla se prueba la siguiente), con los
 *   prompts editables ai_prompt_description y ai_prompt_short (variables en VARIABLES). Si la
 *   búsqueda en Google (HSY_DESCRIBER) está activa, lo encontrado llega en {info_web}.
 * - Sin IA y con Google activo, la descripción es el texto encontrado en Google (como antes).
 *
 * Se ejecuta por AJAX de a un producto (HSY_API_IMPORT::content): las llamadas a la IA tardan.
 */
class HSY_CONTENT
{
    /** Largo máximo del texto que se envía a la tabla (tooltip). */
    const TEXT_MAX = 1500;

    /** HTML permitido en lo que devuelve la IA. */
    const ALLOWED_TAGS = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'em' => [],
        'i' => [],
        'small' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        // Tablas de características / especificaciones, con estilos en línea (wp_kses filtra el CSS).
        'table' => ['style' => true],
        'thead' => [],
        'tbody' => [],
        'tr' => ['style' => true],
        'th' => ['style' => true, 'colspan' => true],
        'td' => ['style' => true, 'colspan' => true],
    ];

    /** ¿Está el plugin IA Conector activo? */
    public static function ai_available()
    {
        return class_exists('IACON_AI');
    }

    /**
     * Conexiones activas del IA Conector: [key => etiqueta].
     */
    public static function ai_connections()
    {
        if (!self::ai_available()) {
            return [];
        }
        $list = [];
        foreach ((array) IACON_AI::getListIas() as $conn) {
            $list[$conn['key']] = $conn['label'] . ' (' . $conn['type'] . ')';
        }
        return $list;
    }

    /** ¿Hay algún generador configurado (IA o Google)? */
    public static function enabled($config = null)
    {
        $config = $config ?: (new HSY_USE_DATA_CONFIG())->get();
        return self::ai_enabled($config) || HSY_DESCRIBER::enabled($config);
    }

    public static function ai_enabled($config)
    {
        return !empty($config['ai_enabled']) && !empty($config['ai_connections']) && self::ai_available();
    }

    public static function is_pending($product)
    {
        if (!HSY_STOCK::is_imported($product)) {
            return false;
        }
        return '' === trim(wp_strip_all_tags($product->get_description())) || self::short_pending($product);
    }

    /**
     * ¿Falta elegir el estilo (subcategoría) con IA? Solo si la clasificación por IA está activa.
     */
    public static function style_pending($product, $config = null)
    {
        $config = $config ?: (new HSY_USE_DATA_CONFIG())->get();
        return !empty($config['ai_style']) && 'pending' === $product->get_meta(HSY_STYLE_META);
    }

    /**
     * ¿La descripción corta falta o es la lista automática? Los productos importados antes de que
     * existiera HSY_SHORT_AUTO_META se reconocen por la forma de la lista (<ul><li><strong>X:</strong> …).
     */
    public static function short_pending($product)
    {
        return self::short_pending_raw($product->get_short_description(), $product->get_meta(HSY_SHORT_AUTO_META));
    }

    /**
     * Misma regla con los valores en crudo (post_excerpt y la meta), para listados sin cargar el producto.
     */
    public static function short_pending_raw($short, $auto)
    {
        $short = trim((string) $short);
        if ('' === trim(wp_strip_all_tags($short))) {
            return true;
        }
        if ('' === (string) $auto) {
            return self::is_auto_list($short);
        }
        return 'yes' === $auto;
    }

    /**
     * Descripción y descripción corta de varios productos en una consulta, para la tabla:
     * [product_id => ['description' => texto, 'short_description' => texto, 'description_pending', 'short_pending']].
     */
    public static function texts($product_ids)
    {
        global $wpdb;
        $product_ids = array_filter(array_map('intval', (array) $product_ids));
        if (!$product_ids) {
            return [];
        }
        $in = implode(',', $product_ids);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_content, p.post_excerpt, sa.meta_value AS short_auto
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} sa ON sa.post_id = p.ID AND sa.meta_key = %s
             WHERE p.ID IN ($in)", // $in son enteros (intval)
            HSY_SHORT_AUTO_META
        ));
        $out = [];
        foreach ($rows as $row) {
            $description = self::text($row->post_content);
            $out[(int) $row->ID] = [
                'description' => mb_substr($description, 0, self::TEXT_MAX),
                'short_description' => mb_substr(self::text($row->post_excerpt), 0, self::TEXT_MAX),
                'description_pending' => '' === $description,
                'short_pending' => self::short_pending_raw($row->post_excerpt, $row->short_auto),
            ];
        }
        return $out;
    }

    public static function is_auto_list($html)
    {
        return (bool) preg_match('#^<ul>(<li><strong>[^<]+:</strong>[^<]*</li>)+</ul>$#u', trim((string) $html));
    }

    /**
     * IDs de los productos importados con contenido pendiente (una consulta).
     */
    public static function pending_ids($limit = 2000)
    {
        global $wpdb;
        // El estilo solo cuenta como pendiente si la IA lo clasifica (ajuste ai_style).
        $style = !empty((new HSY_USE_DATA_CONFIG())->get()['ai_style']) ? 'pending' : '__off__';
        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} imp ON imp.post_id = p.ID AND imp.meta_key = %s AND imp.meta_value = 'yes'
             LEFT JOIN {$wpdb->postmeta} sa ON sa.post_id = p.ID AND sa.meta_key = %s
             LEFT JOIN {$wpdb->postmeta} st ON st.post_id = p.ID AND st.meta_key = %s
             WHERE p.post_type = 'product' AND p.post_status <> 'trash'
               AND (TRIM(p.post_content) = '' OR TRIM(p.post_excerpt) = '' OR sa.meta_value = 'yes'
                    OR (sa.meta_value IS NULL AND p.post_excerpt LIKE '<ul><li><strong>%%')
                    OR st.meta_value = %s)
             ORDER BY p.ID ASC LIMIT %d",
            HSY_IMPORTED_META,
            HSY_SHORT_AUTO_META,
            HSY_STYLE_META,
            $style,
            $limit
        )));
    }

    /**
     * Variables de los prompts: {clave} => qué contiene.
     */
    const VARIABLES = [
        'nombre' => 'Nombre del producto en el catálogo (LEILA SET)',
        'nombre_tienda' => 'Nombre del producto en la tienda (según la plantilla del nombre)',
        'marca' => 'Marca (Armani Exchange)',
        'referencia' => 'Referencia / SKU (AX7186SET)',
        'genero' => 'Género (DAMA)',
        'correa' => 'Correa (Acero Dorado)',
        'caja' => 'Caja (Acero, Dorado)',
        'tamano' => 'Tamaño (27 mm)',
        'resistencia_agua' => 'Resistencia al agua',
        'caracteristicas' => 'Todas las características, una por línea («- Género: DAMA»)',
        'info_web' => 'Texto encontrado en Google con la referencia (vacío si no hay o si la búsqueda está apagada)',
        'fuente_web' => 'URL de donde salió {info_web}',
        'descripcion' => 'Descripción del producto (la recién generada o la que ya tenía; útil en el prompt de la corta)',
        'info_marca' => 'Descripción de la marca (la de la marca en Productos → Marcas)',
        'tienda' => 'Nombre de la tienda (Ajustes → Generales → Título del sitio)',
        'opciones' => 'Solo en el prompt del estilo: las subcategorías posibles, una por línea («- Análogo»)',
        'contexto' => 'Todos los datos del producto que existan, uno por línea (referencia, nombre, marca, info de la marca, características e información de la web); los vacíos se omiten',
    ];

    // Prompts por defecto (adaptados de los del plugin de importación desde Drive/Excel del mismo autor).
    const DEFAULT_PROMPT_DESCRIPTION = "Eres un especialista senior en contenido para ecommerce, SEO orgánico, GEO, búsqueda por IA, búsqueda por voz, WooCommerce y conversión.

Genera SOLO la descripción larga del producto en HTML limpio.

OBJETIVO:
Crear una descripción enriquecida, clara, confiable y optimizada para motores de búsqueda tradicionales, Google AI, Gemini, ChatGPT, Copilot, Perplexity y asistentes de voz.

REGLAS OBLIGATORIAS:
- No coloques el precio.
- Usa únicamente los datos entregados en CONTEXTO.
- No inventes información técnica.
- Si falta un dato, omítelo.
- No menciones datos no confirmados.
- No uses frases genéricas como 'el mejor', 'alta calidad' o 'producto increíble'.
- No repitas información.
- No uses JavaScript.
- HTML permitido: <h2>, <h3>, <p>, <strong>, <small>, <i>, <ul>, <li>, <table>, <tr>, <th>, <td>.
- Extensión máxima: 1000 caracteres.
- Lenguaje comercial, natural y profesional.
- Optimiza para SEO, GEO, IA y búsqueda por voz.
- Responde de forma natural: qué es, para qué sirve, para quién es, beneficios y características.

ESTRUCTURA OBLIGATORIA:

1. Primer párrafo:
Describe qué es el producto, para quién está diseñado y cuál es su principal beneficio.

2. Sección:
<h2>Beneficios principales</h2>
Incluye 3 a 5 beneficios reales basados en el contexto.

3. Sección:
<h2>Características del producto</h2>
Explica materiales, diseño, uso, resistencia, garantía, marca o funcionalidad solo si existen en el contexto.
Usa una tabla para mostrar las características, y usa estilos y emojis para una mejor presentación.

4. Sección:
<h2>Especificaciones técnicas</h2>
Crea una tabla HTML solo con los datos disponibles.
Usa estilos y emojis para una mejor presentación.
No incluyas filas vacías.

5. Cierre comercial:
Incluye una frase natural de confianza para comprar en {tienda}, mencionando originalidad, compra segura, envío en Colombia, IVA incluido o financiación solo si aplica según el contexto.

CONTEXTO DEL PRODUCTO:
{contexto}

SALIDA:
Devuelve únicamente el HTML final de la descripción larga.";

    const DEFAULT_PROMPT_STYLE = "Eres un experto en relojería y catalogación de productos para ecommerce.

Clasifica el producto en el estilo o los estilos que le correspondan, eligiendo SOLO entre estas opciones:
{opciones}

REGLAS:
- Elige entre 1 y 2 opciones, la más evidente primero.
- Usa los datos del CONTEXTO y lo que sepas del modelo por su referencia; si dudas, elige solo la más segura.
- Digital = pantalla digital; Análogo = agujas; Cronógrafo = con cronómetro; Smartwatch = reloj inteligente; Deportivo, Casual y Elegante según el diseño y el material.
- Responde SOLO con los nombres exactos de las opciones, separados por coma. Sin explicaciones.

CONTEXTO DEL PRODUCTO:
{contexto}";

    const DEFAULT_PROMPT_SHORT = "Eres un especialista senior en contenido para ecommerce, SEO orgánico, búsqueda por IA y WooCommerce.

ADVERTENCIA: Si incluyes HTML, títulos, listas, emojis, comillas o cualquier formato que no sea texto plano, la respuesta será RECHAZADA automáticamente.

Genera ÚNICAMENTE la descripción corta del producto en una sola frase.

REGLAS OBLIGATORIAS (incumplirlas invalida la respuesta):
- No coloques el precio.
- Una sola frase.
- Máximo 200 caracteres.
- Texto plano. SIN HTML. SIN etiquetas. SIN títulos. SIN emojis. SIN comillas. SIN listas.
- No inventes información. Usa solo los datos del CONTEXTO.
- Si falta un dato, omítelo.
- Debe ser comercial, clara, natural y persuasiva.
- Incluye el nombre o tipo de producto.
- Menciona el beneficio principal.
- Incluye marca, material, uso o característica destacada solo si existe en el contexto.
- Finaliza obligatoriamente con: Ref. {referencia}
- NO finalices con puntos suspensivos (...).

CONTEXTO DEL PRODUCTO:
{contexto}

SALIDA (texto plano, una sola línea, sin formato):";

    /**
     * Completa lo pendiente de un producto. Devuelve ['fields' => [...], 'source' => ..., 'warnings' => [...]] o WP_Error.
     */
    public static function fill($product_id, $config = null)
    {
        $config = $config ?: (new HSY_USE_DATA_CONFIG())->get();
        $product = wc_get_product($product_id);
        if (!$product || !HSY_STOCK::is_imported($product)) {
            return new WP_Error('hsy_content_product', 'El producto #' . (int) $product_id . ' no es un producto importado.');
        }
        $need_style = self::style_pending($product, $config) && self::ai_enabled($config);
        if (!self::is_pending($product) && !$need_style) {
            return ['fields' => [], 'source' => '', 'warnings' => []];
        }
        if (!self::enabled($config)) {
            return new WP_Error('hsy_content_off', 'No hay generador de descripciones: activa la IA o la búsqueda en Google en Importación.');
        }

        $need_description = '' === trim(wp_strip_all_tags($product->get_description()));
        $need_short = self::short_pending($product);
        $web = self::web($product, $config);
        $warnings = $web['warnings'];
        $web = $web['web'];

        $fields = [];
        $source = '';
        if (self::ai_enabled($config)) {
            $generated = self::generate($product, $web, $config, $need_description, $need_short);
            if (is_wp_error($generated)) {
                HSY_LOG_SERVICE::add('content_error', ['product_id' => $product->get_id(), 'message' => $generated->get_error_message()]);
                return $generated;
            }
            if ($need_description && '' !== $generated['description']) {
                $product->set_description($generated['description']);
                $fields[] = 'description';
            }
            if ($need_short && '' !== $generated['short_description']) {
                $product->set_short_description($generated['short_description']);
                $product->update_meta_data(HSY_SHORT_AUTO_META, 'no');
                $fields[] = 'short_description';
            }
            $warnings = array_merge($warnings, $generated['warnings']);
            if ($generated['connections']) {
                $source = 'ia:' . implode(',', $generated['connections']);
                $product->update_meta_data('_hsy_content_ai', implode(',', $generated['connections']));
            }
        } elseif ($web && $need_description) {
            $product->set_description(wpautop(esc_html($web['text'])));
            $fields[] = 'description';
            $source = 'google';
        }

        // Estilo (subcategorías) elegido por la IA entre las opciones del producto.
        if ($need_style) {
            $style = self::classify_style($product, $web, $config);
            if (is_wp_error($style)) {
                $warnings[] = 'Estilo: ' . $style->get_error_message();
            } else {
                $warnings = array_merge($warnings, $style['warnings']);
                $product->update_meta_data(HSY_STYLE_META, $style['applied'] ? 'done' : 'none');
                $product->update_meta_data('_hsy_style_names', implode(', ', $style['applied']));
                if ($style['applied']) {
                    $fields[] = 'style';
                    $source = $source ?: 'ia:' . $style['connection'];
                    if ('' === (string) $product->get_meta('_hsy_content_ai')) {
                        $product->update_meta_data('_hsy_content_ai', $style['connection']);
                    }
                } else {
                    $warnings[] = 'Estilo: la IA no eligió ninguna opción válida.';
                    $product->save(); // guarda «none» aunque no haya otros cambios
                }
            }
        }

        if ($web) {
            $product->update_meta_data('_hsy_description_source', $web['source']);
        }
        if ($fields) {
            $product->update_meta_data('_hsy_content_at', time());
            $product->save();
        } elseif (!$warnings) {
            $warnings[] = 'No se generó contenido.';
        }

        HSY_LOG_SERVICE::add('content', [
            'product_id' => $product->get_id(),
            'fields' => $fields,
            'source' => $source,
            'web' => $web ? $web['source'] : '',
            'warnings' => $warnings,
        ]);
        return ['fields' => $fields, 'source' => $source, 'warnings' => $warnings];
    }

    /**
     * Prueba de los prompts (los del formulario, sin guardar) con un producto: no escribe nada.
     * Devuelve prompts finales y textos generados, o WP_Error.
     */
    public static function preview($product_id, $prompts, $config = null)
    {
        $config = $config ?: (new HSY_USE_DATA_CONFIG())->get();
        $config = array_merge($config, array_filter($prompts, 'strlen'));
        $product = wc_get_product($product_id);
        if (!$product) {
            return new WP_Error('hsy_preview_product', 'No existe el producto #' . (int) $product_id . '.');
        }
        if (!self::ai_enabled($config)) {
            return new WP_Error('hsy_preview_ai', 'Activa la IA, marca al menos una conexión y guarda antes de probar.');
        }
        $web = self::web($product, $config);
        $vars = self::variables($product, $web['web']);
        $prompt_description = self::render($config['ai_prompt_description'], $vars);
        $description = self::ask($prompt_description, $config);
        if (is_wp_error($description)) {
            return $description;
        }
        $vars['descripcion'] = self::text($description['html']);
        $prompt_short = self::render($config['ai_prompt_short'], $vars);
        $short = self::ask($prompt_short, $config);
        if (is_wp_error($short)) {
            return $short;
        }
        // Estilo: se calcula pero no se aplica (vista previa).
        $style = null;
        if (!empty($config['ai_style'])) {
            $style = self::classify_style($product, $web['web'], $config, false);
            if (is_wp_error($style)) {
                $style = ['applied' => [], 'prompt' => '', 'answer' => $style->get_error_message(), 'warnings' => []];
            }
        }
        return [
            'product' => $product->get_name(),
            'style' => $style ? $style['applied'] : null,
            'style_options' => array_keys(HSY_CATEGORIES::style_options($product)),
            'style_answer' => $style ? $style['answer'] : '',
            'prompt_style' => $style ? $style['prompt'] : '',
            'prompt_description' => $prompt_description,
            'description' => $description['html'],
            'prompt_short' => $prompt_short,
            'short_description' => $short['html'],
            'connections' => array_values(array_unique([$description['connection'], $short['connection']])),
            'web' => $web['web'] ? $web['web']['source'] : '',
            'warnings' => array_values(array_unique(array_merge($web['warnings'], $description['warnings'], $short['warnings']))),
        ];
    }

    /**
     * Valores de las variables de los prompts para un producto.
     */
    public static function variables($product, $web = null, $description = null)
    {
        $brands = taxonomy_exists('product_brand') ? wp_get_post_terms($product->get_id(), 'product_brand', ['fields' => 'names']) : [];
        $attrs = [];
        $lines = [];
        foreach ($product->get_attributes() as $attribute) {
            if ($attribute instanceof WC_Product_Attribute && !$attribute->is_taxonomy()) {
                $value = implode(', ', $attribute->get_options());
                $attrs[sanitize_title($attribute->get_name())] = $value;
                $lines[] = '- ' . $attribute->get_name() . ': ' . $value;
            }
        }
        // Nombre del catálogo: meta (productos importados desde 1.1) o el catálogo guardado (anteriores).
        $source_name = (string) $product->get_meta('_hsy_source_name');
        if ('' === $source_name) {
            $item = (new HSY_USE_DATA_CATALOG())->item($product->get_meta(HSY_SOURCE_META));
            $source_name = $item ? (string) $item['name'] : '';
        }
        $current = null === $description ? $product->get_description() : $description;

        // Descripción de la marca (primera marca con descripción).
        $brand_info = '';
        if (taxonomy_exists('product_brand')) {
            foreach ((array) wp_get_post_terms($product->get_id(), 'product_brand') as $term) {
                if ($term instanceof WP_Term && '' !== trim($term->description)) {
                    $brand_info = self::text($term->description);
                    break;
                }
            }
        }
        $name = '' !== $source_name ? $source_name : $product->get_name();
        $brand = is_array($brands) && $brands ? implode(', ', $brands) : '';
        // {contexto}: solo los datos que existen, como «Etiqueta: valor».
        $context = [];
        foreach ([
            'Referencia' => $product->get_sku(),
            'Nombre' => $name,
            'Marca' => $brand,
            'Info marca' => $brand_info,
        ] as $label => $value) {
            if ('' !== trim((string) $value)) {
                $context[] = $label . ': ' . $value;
            }
        }
        foreach ($lines as $line) {
            $line = ltrim($line, '- ');
            if (0 !== stripos($line, 'Referencia:')) { // ya está arriba (SKU)
                $context[] = $line;
            }
        }
        if ($web) {
            $context[] = 'Información encontrada en la web (' . $web['source'] . '): ' . $web['text'];
        }

        return [
            'nombre' => '' !== $source_name ? $source_name : $product->get_name(),
            'nombre_tienda' => $product->get_name(),
            'marca' => is_array($brands) && $brands ? implode(', ', $brands) : '',
            'referencia' => $product->get_sku(),
            'genero' => $attrs['genero'] ?? '',
            'correa' => $attrs['correa'] ?? '',
            'caja' => $attrs['caja'] ?? '',
            'tamano' => $attrs['tamano'] ?? '',
            'resistencia_agua' => $attrs['resistencia-al-agua'] ?? '',
            'caracteristicas' => implode("\n", $lines),
            'info_web' => $web ? $web['text'] : '',
            'fuente_web' => $web ? $web['source'] : '',
            'descripcion' => self::text($current),
            'info_marca' => $brand_info,
            'tienda' => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
            'contexto' => implode("\n", $context),
        ];
    }

    /**
     * Sustituye {variable} por su valor. Las variables desconocidas se dejan tal cual.
     */
    public static function render($template, $vars)
    {
        $map = [];
        foreach ($vars as $key => $value) {
            $map['{' . $key . '}'] = (string) $value;
        }
        return trim(strtr((string) $template, $map));
    }

    // ------------------------------------------------------------------ internos

    /**
     * Búsqueda en Google (opcional). Devuelve ['web' => ['text','source']|null, 'warnings' => []].
     */
    private static function web($product, $config)
    {
        if (!HSY_DESCRIBER::enabled($config)) {
            return ['web' => null, 'warnings' => []];
        }
        $vars = self::variables($product);
        $web = HSY_DESCRIBER::find($vars['marca'], $vars['nombre'], $vars['referencia'], $config);
        return is_wp_error($web)
            ? ['web' => null, 'warnings' => [$web->get_error_message()]]
            : ['web' => $web, 'warnings' => []];
    }

    /**
     * Pide a la IA el estilo entre las opciones del producto y lo aplica (sin guardar).
     * Devuelve ['applied' => nombres, 'connection', 'warnings', 'prompt', 'answer'] o WP_Error.
     */
    public static function classify_style($product, $web, $config, $apply = true)
    {
        $options = HSY_CATEGORIES::style_options($product);
        if (!$options) {
            return ['applied' => [], 'connection' => '', 'warnings' => [], 'prompt' => '', 'answer' => ''];
        }
        $vars = self::variables($product, $web);
        $vars['opciones'] = implode("\n", array_map(function ($name) {
            return '- ' . $name;
        }, array_keys($options)));
        $prompt = self::render($config['ai_prompt_style'], $vars);
        $answer = self::ask($prompt, $config);
        if (is_wp_error($answer)) {
            return $answer;
        }
        $names = preg_split('/[,\n;]+/', wp_strip_all_tags($answer['html']));
        $names = array_values(array_filter(array_map(function ($n) {
            return trim(preg_replace('/^[\s\-*.]+|[\s.\"\']+$/u', '', $n));
        }, $names), 'strlen'));
        $applied = $apply ? HSY_CATEGORIES::apply_styles($product, array_slice($names, 0, 3)) : [];
        if (!$apply) {
            // Vista previa: solo los nombres que existen entre las opciones.
            $keys = array_map(['HSY_BRANDS', 'plain_key'], array_keys($options));
            foreach (array_slice($names, 0, 3) as $n) {
                $i = array_search(HSY_BRANDS::plain_key($n), $keys, true);
                if (false !== $i) {
                    $applied[] = array_keys($options)[$i];
                }
            }
        }
        return [
            'applied' => $applied,
            'connection' => $answer['connection'],
            'warnings' => $answer['warnings'],
            'prompt' => $prompt,
            'answer' => trim(wp_strip_all_tags($answer['html'])),
        ];
    }

    /**
     * Una llamada por campo: primero la descripción y después la corta (que puede usar {descripcion}).
     * Devuelve ['description', 'short_description', 'connections' => usadas, 'warnings' => saltos] o WP_Error.
     */
    private static function generate($product, $web, $config, $need_description, $need_short)
    {
        $vars = self::variables($product, $web);
        $out = ['description' => '', 'short_description' => '', 'connections' => [], 'warnings' => []];
        foreach (['description' => $need_description, 'short_description' => $need_short] as $field => $need) {
            if (!$need) {
                continue;
            }
            $prompt = 'description' === $field ? $config['ai_prompt_description'] : $config['ai_prompt_short'];
            $answer = self::ask(self::render($prompt, $vars), $config);
            if (is_wp_error($answer)) {
                return $answer;
            }
            $out[$field] = $answer['html'];
            $out['connections'][] = $answer['connection'];
            $out['warnings'] = array_merge($out['warnings'], $answer['warnings']);
            if ('description' === $field) {
                $vars['descripcion'] = self::text($answer['html']);
            }
        }
        $out['connections'] = array_values(array_unique($out['connections']));
        $out['warnings'] = array_values(array_unique($out['warnings']));
        return $out;
    }

    /**
     * Envía el prompt a las conexiones en orden de prioridad hasta que una responda.
     *
     * Si una falla (tokens o límite de uso agotados, modelo inexistente, red…) se prueba la siguiente.
     * Si el error es de cuota / límite, la conexión queda en pausa HSY_AI_COOLDOWN segundos
     * (transient) para no gastar una llamada fallida en cada producto.
     *
     * Devuelve ['html', 'connection', 'warnings' => conexiones saltadas] o WP_Error si fallaron todas.
     */
    private static function ask($prompt, $config)
    {
        $errors = [];
        $skipped = [];
        foreach ((array) $config['ai_connections'] as $key) {
            if (get_transient(self::cooldown_key($key))) {
                $skipped[] = $key . ': en pausa por límite de uso';
                continue;
            }
            $ai = IACON_AI::get($key);
            if (!$ai) {
                $errors[] = $key . ': no existe o está desactivada en IA Conector';
                continue;
            }
            $response = $ai->sendPrompt($prompt);
            if (!is_array($response) || 'ok' !== ($response['status'] ?? '')) {
                $message = is_array($response) ? (string) ($response['message'] ?? 'error') : 'respuesta vacía';
                if (self::is_limit_error($message)) {
                    set_transient(self::cooldown_key($key), $message, HSY_AI_COOLDOWN);
                    HSY_LOG_SERVICE::add('ai_limit', ['connection' => $key, 'message' => $message, 'pause' => HSY_AI_COOLDOWN]);
                }
                $errors[] = $key . ': ' . $message;
                continue;
            }
            $html = self::html((string) $response['data']);
            if ('' === $html) {
                $errors[] = $key . ': respuesta vacía';
                continue;
            }
            $warnings = [];
            if ($errors || $skipped) {
                $warnings[] = 'Respondió ' . $key . ' (falló: ' . implode(' · ', array_merge($skipped, $errors)) . ')';
            }
            return ['html' => $html, 'connection' => $key, 'warnings' => $warnings];
        }
        if (!$errors && $skipped) {
            return new WP_Error('hsy_ai_paused', 'Todas las conexiones de IA están en pausa por límite de uso; se reintentan solas en unos minutos.');
        }
        return new WP_Error('hsy_ai', 'Ninguna conexión de IA respondió. ' . implode(' · ', array_merge($skipped, $errors)));
    }

    /**
     * ¿El error es por tokens, cuota o límite de uso? (textos de Gemini, Groq, OpenAI…)
     */
    public static function is_limit_error($message)
    {
        return (bool) preg_match('/quota|rate.?limit|exhaust|too many requests|\b429\b|limit (reached|exceeded)|tokens? per|insufficient|billing|credit/i', (string) $message);
    }

    /**
     * Conexiones en pausa: [key => segundos restantes aprox.] (para mostrarlo en ajustes).
     */
    public static function paused_connections($keys)
    {
        $paused = [];
        foreach ((array) $keys as $key) {
            if (get_transient(self::cooldown_key($key))) {
                $timeout = (int) get_option('_transient_timeout_' . self::cooldown_key($key));
                $paused[$key] = $timeout ? max(0, $timeout - time()) : HSY_AI_COOLDOWN;
            }
        }
        return $paused;
    }

    public static function resume_connection($key)
    {
        delete_transient(self::cooldown_key($key));
    }

    private static function cooldown_key($key)
    {
        return 'hsy_ai_pause_' . md5((string) $key);
    }

    /**
     * HTML → texto para las variables: un párrafo / elemento de lista por línea.
     */
    public static function text($html)
    {
        $html = preg_replace('#<br\s*/?>|</(p|li|h[1-6]|ul|ol|tr|table)>#i', "\n", (string) $html);
        $html = preg_replace('#</t[hd]>#i', ' ', $html);
        $html = preg_replace('#<li[^>]*>#i', '- ', $html);
        $text = html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8');
        return trim(preg_replace("/\n\s*\n+/", "\n", preg_replace('/[ \t]+/', ' ', $text)));
    }

    /**
     * Limpia la respuesta: quita bloques ```html … ```, deja solo ALLOWED_TAGS y añade <p> si es texto plano.
     */
    private static function html($text)
    {
        $text = preg_replace('/^\s*```[a-z]*\s*|\s*```\s*$/i', '', trim($text));
        $html = trim(wp_kses($text, self::ALLOWED_TAGS));
        if ('' !== $html && false === strpos($html, '<p') && false === strpos($html, '<ul')) {
            $html = wpautop($html);
        }
        return $html;
    }
}
