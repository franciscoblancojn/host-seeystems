<?php

/**
 * Estructura fija de la descripción larga: única fuente del HTML de la descripción, para que todos
 * los productos salgan iguales.
 *
 * La IA solo escribe el texto (JSON con intro, beneficios y caracteristicas); el plugin arma:
 *   <p>intro</p>
 *   <h2>Beneficios principales</h2> <ul> 3–5 beneficios </ul>
 *   <h2>Características del producto</h2> <p>caracteristicas</p>
 *   <h2>Especificaciones técnicas</h2> tabla con filas y emojis fijos, con los datos del producto
 *   <p>cierre (ajuste description_closing)</p>
 * La tabla lleva estilos en línea: no depende del CSS del tema.
 */
class HSY_LAYOUT
{
    /** Filas de la tabla: etiqueta => emoji. El orden es el de la tabla. */
    const WATCH_ROWS = [
        'Marca' => '🏷️',
        'Modelo' => '⌚',
        'Referencia' => '🔢',
        'Género' => '👤',
        'Correa' => '🔗',
        'Caja' => '⬛',
        'Tamaño' => '📏',
        'Resistencia al agua' => '💧',
    ];

    const JEWELRY_ROWS = [
        'Marca' => '🏷️',
        'Tipo' => '💎',
        'Referencia' => '🔢',
        'Género' => '👤',
        'Material' => '🛡️',
        'Color' => '🎨',
        'Tamaño' => '📏',
    ];

    const DEFAULT_CLOSING = 'Compra con confianza en <strong>{tienda}</strong>: producto original, compra segura y envío a toda Colombia.';

    /** HTML permitido dentro de los textos de la IA. */
    const INLINE_TAGS = ['strong' => [], 'em' => []];

    /**
     * Partes de texto de la respuesta de la IA, o null si no es el JSON esperado (prompt propio que devuelve HTML).
     */
    public static function parse($raw)
    {
        $text = preg_replace('/^\s*```(?:json)?\s*|\s*```\s*$/i', '', trim((string) $raw));
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if (false === $start || false === $end || $end <= $start) {
            return null;
        }
        $data = json_decode(substr($text, $start, $end - $start + 1), true);
        if (!is_array($data) || empty($data['intro'])) {
            return null;
        }
        $benefits = array_values(array_filter(array_map([self::class, 'inline'], (array) ($data['beneficios'] ?? [])), 'strlen'));
        return [
            'intro' => self::inline($data['intro']),
            'benefits' => array_slice($benefits, 0, 5),
            'features' => self::inline($data['caracteristicas'] ?? ''),
        ];
    }

    /**
     * Descripción completa con la estructura fija.
     */
    public static function build($parts, $product, $vars, $config)
    {
        $html = '<p>' . $parts['intro'] . '</p>' . "\n";
        if ($parts['benefits']) {
            $html .= '<h2>Beneficios principales</h2>' . "\n" . '<ul>' . "\n";
            foreach ($parts['benefits'] as $benefit) {
                $html .= '<li>' . $benefit . '</li>' . "\n";
            }
            $html .= '</ul>' . "\n";
        }
        if ('' !== $parts['features']) {
            $html .= '<h2>Características del producto</h2>' . "\n" . '<p>' . $parts['features'] . '</p>' . "\n";
        }
        $table = self::table(self::rows($product, $vars));
        if ('' !== $table) {
            $html .= '<h2>Especificaciones técnicas</h2>' . "\n" . $table . "\n";
        }
        $closing = trim((string) ($config['description_closing'] ?? ''));
        if ('' === $closing) {
            $closing = self::DEFAULT_CLOSING;
        }
        $closing = wp_kses(HSY_CONTENT::render($closing, $vars), self::INLINE_TAGS);
        if ('' !== trim(wp_strip_all_tags($closing))) {
            $html .= '<p>' . $closing . '</p>';
        }
        return $html;
    }

    /**
     * Filas de la tabla con los datos del producto (solo las que tienen valor).
     * [['emoji', 'label', 'value']].
     */
    public static function rows($product, $vars)
    {
        $jewelry = !empty($vars['es_joya']);
        $values = [
            'Marca' => $vars['marca'],
            'Modelo' => $jewelry ? '' : $vars['nombre'],
            'Tipo' => $jewelry ? $vars['nombre'] : '',
            'Referencia' => $vars['referencia'],
            'Género' => self::gender($vars['genero']),
            'Correa' => $vars['correa'],
            'Caja' => $vars['caja'],
            'Material' => $vars['material'],
            'Color' => $vars['color'],
            'Tamaño' => $vars['tamano'],
            'Resistencia al agua' => $vars['resistencia_agua'],
        ];
        $rows = [];
        foreach ($jewelry ? self::JEWELRY_ROWS : self::WATCH_ROWS as $label => $emoji) {
            $value = trim((string) ($values[$label] ?? ''));
            if ('' !== $value) {
                $rows[] = [$emoji, $label, $value];
            }
        }
        return $rows;
    }

    /**
     * Tabla con estilos en línea (bordes, relleno y fondo en la columna de etiquetas).
     */
    public static function table($rows)
    {
        if (!$rows) {
            return '';
        }
        $border = 'border:1px solid #e3e3e3;';
        $html = '<table style="width:100%;max-width:640px;border-collapse:collapse;margin:0 0 1.5em;' . $border . '"><tbody>';
        foreach ($rows as $i => $row) {
            $bg = 0 === $i % 2 ? '#ffffff' : '#fafafa';
            $html .= '<tr style="background:' . $bg . ';">'
                . '<th scope="row" style="' . $border . 'width:40%;padding:10px 14px;text-align:left;font-weight:600;background:#f5f5f5;">'
                . esc_html($row[0] . ' ' . $row[1]) . '</th>'
                . '<td style="' . $border . 'padding:10px 14px;">' . esc_html($row[2]) . '</td>'
                . '</tr>';
        }
        return $html . '</tbody></table>';
    }

    private static function inline($text)
    {
        $text = wp_kses((string) $text, self::INLINE_TAGS);
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** «DAMA» → «Dama», «UNISEX» → «Unisex». */
    private static function gender($gender)
    {
        $gender = trim((string) $gender);
        return '' === $gender ? '' : mb_convert_case(mb_strtolower($gender, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }
}
