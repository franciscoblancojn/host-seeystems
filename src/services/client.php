<?php

/**
 * Cliente HTTP del catálogo de origen: login por formulario (email + password + csrf_token)
 * y sesión por cookie. Es la única clase que habla con el origen.
 *
 * Las cookies de la sesión se guardan en el transient HSY_SESSION. Si una petición termina
 * en la página de login (sesión caducada), se vuelve a iniciar sesión una vez y se repite.
 * Solo pide URLs del mismo host que `base_url`.
 */
class HSY_CLIENT
{
    const TIMEOUT = 30;
    const SESSION_TTL = 2 * HOUR_IN_SECONDS;
    const MAX_REDIRECTS = 5;

    /** @var array */
    private static $cookies = null;

    public static function config()
    {
        return (new HSY_USE_DATA_CONFIG())->get();
    }

    /**
     * URL absoluta de una ruta del catálogo (relativa a base_url).
     */
    public static function url($path = '')
    {
        $config = self::config();
        return $config['base_url'] . ltrim((string) $path, '/');
    }

    /**
     * Resuelve un enlace (absoluto, /raíz, relativo o //host) respecto a la página donde aparece.
     */
    public static function absolute($href, $page_url)
    {
        $href = trim(html_entity_decode((string) $href, ENT_QUOTES, 'UTF-8'));
        if ('' === $href || 0 === strpos($href, 'data:') || 0 === strpos($href, '#')) {
            return '';
        }
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $parts = wp_parse_url($page_url);
        $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if (0 === strpos($href, '//')) {
            return $parts['scheme'] . ':' . $href;
        }
        if (0 === strpos($href, '/')) {
            return $origin . $href;
        }
        $dir = preg_replace('#/[^/]*$#', '/', $parts['path'] ?? '/');
        return $origin . $dir . $href;
    }

    /**
     * ¿La URL es del mismo host que el catálogo?
     */
    public static function is_same_host($url)
    {
        $base = wp_parse_url(self::config()['base_url']);
        $target = wp_parse_url($url);
        return !empty($base['host']) && !empty($target['host'])
            && strtolower($base['host']) === strtolower($target['host'])
            && in_array(strtolower($target['scheme'] ?? ''), ['http', 'https'], true);
    }

    /**
     * Inicia sesión y comprueba que el catálogo responde. Devuelve true o WP_Error.
     */
    public static function login()
    {
        $config = self::config();
        if (!HSY_USE_DATA_CONFIG::is_connected_config($config)) {
            return new WP_Error('hsy_config', 'Falta la URL, el correo o la contraseña en la pestaña Conexión.');
        }
        self::$cookies = [];
        delete_transient(HSY_SESSION);

        $login_url = self::url($config['login_path']);

        // 1. Formulario: cookie de sesión inicial + csrf_token.
        $form = self::raw('GET', $login_url);
        if (is_wp_error($form)) {
            return $form;
        }
        $html = wp_remote_retrieve_body($form);
        $csrf = '';
        if (preg_match('/<input[^>]+name=["\']csrf_token["\'][^>]*>/i', $html, $input)
            && preg_match('/value=["\']([^"\']*)["\']/i', $input[0], $value)) {
            $csrf = $value[1];
        }

        // 2. Envío del formulario.
        $body = [
            'email' => $config['email'],
            'password' => $config['password'],
        ];
        if ('' !== $csrf) {
            $body['csrf_token'] = $csrf;
        }
        $sent = self::raw('POST', $login_url, ['body' => $body, 'headers' => ['Referer' => $login_url]]);
        if (is_wp_error($sent)) {
            return $sent;
        }

        // 3. Comprobación: el catálogo no debe redirigir al login.
        $check = self::raw('GET', self::url($config['catalog_path']));
        if (is_wp_error($check)) {
            return $check;
        }
        if (self::is_login_redirect($check) || self::is_login_page(wp_remote_retrieve_body($check))) {
            $message = self::login_error(wp_remote_retrieve_body($sent));
            HSY_LOG_SERVICE::add('login_error', ['email' => $config['email'], 'message' => $message]);
            return new WP_Error('hsy_login', $message ?: 'No se pudo iniciar sesión: revisa el correo y la contraseña.');
        }

        set_transient(HSY_SESSION, self::$cookies, self::SESSION_TTL);
        HSY_LOG_SERVICE::add('login_ok', ['email' => $config['email']]);
        return true;
    }

    /**
     * GET autenticado. Devuelve ['url' => URL final, 'body' => HTML] o WP_Error.
     */
    public static function get($url)
    {
        if (!self::is_same_host($url)) {
            return new WP_Error('hsy_host', 'URL fuera del catálogo: ' . $url);
        }
        if (null === self::$cookies) {
            self::$cookies = (array) get_transient(HSY_SESSION);
        }
        if (!self::$cookies) {
            $login = self::login();
            if (is_wp_error($login)) {
                return $login;
            }
        }

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $response = self::follow($url);
            if (is_wp_error($response)) {
                return $response;
            }
            if (!self::is_login_page($response['body'])) {
                return $response;
            }
            // Sesión caducada: login y repetir una vez.
            $login = self::login();
            if (is_wp_error($login)) {
                return $login;
            }
        }
        return new WP_Error('hsy_session', 'El catálogo pide iniciar sesión otra vez.');
    }

    /**
     * Descarga binaria (imágenes) con la sesión. Devuelve la ruta de un archivo temporal o WP_Error.
     */
    public static function download($url)
    {
        if (!function_exists('wp_tempnam')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        $tmp = wp_tempnam($url);
        $args = ['stream' => true, 'filename' => $tmp, 'redirection' => 3];
        if (self::is_same_host($url)) {
            $args['cookies'] = (array) (self::$cookies ?? get_transient(HSY_SESSION));
        }
        $response = wp_remote_get($url, array_merge(self::args(), $args));
        $code = (int) wp_remote_retrieve_response_code($response);
        if (is_wp_error($response) || 200 !== $code) {
            @unlink($tmp);
            return is_wp_error($response) ? $response : new WP_Error('hsy_download', 'HTTP ' . $code . ' al descargar ' . $url);
        }
        return $tmp;
    }

    public static function logout()
    {
        self::$cookies = [];
        delete_transient(HSY_SESSION);
    }

    // ------------------------------------------------------------------ internos

    /**
     * GET siguiendo redirecciones a mano (para conservar las cookies entre saltos).
     */
    private static function follow($url)
    {
        for ($i = 0; $i <= self::MAX_REDIRECTS; $i++) {
            $response = self::raw('GET', $url);
            if (is_wp_error($response)) {
                return $response;
            }
            $code = (int) wp_remote_retrieve_response_code($response);
            $location = wp_remote_retrieve_header($response, 'location');
            if ($code >= 300 && $code < 400 && $location) {
                $url = self::absolute($location, $url);
                if (!self::is_same_host($url)) {
                    return new WP_Error('hsy_host', 'El catálogo redirige fuera de su dominio: ' . $url);
                }
                continue;
            }
            if (200 !== $code) {
                return new WP_Error('hsy_http', 'HTTP ' . $code . ' en ' . $url);
            }
            return ['url' => $url, 'body' => wp_remote_retrieve_body($response)];
        }
        return new WP_Error('hsy_redirect', 'Demasiadas redirecciones en ' . $url);
    }

    /**
     * Una petición sin seguir redirecciones; guarda las cookies que devuelva.
     */
    private static function raw($method, $url, $args = [])
    {
        $args = array_merge(self::args(), [
            'method' => $method,
            'redirection' => 0,
            'cookies' => (array) self::$cookies,
        ], $args);
        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            return new WP_Error('hsy_http', 'No se pudo conectar con el catálogo: ' . $response->get_error_message());
        }
        foreach (wp_remote_retrieve_cookies($response) as $cookie) {
            if ($cookie instanceof WP_Http_Cookie && '' !== (string) $cookie->name) {
                self::$cookies[$cookie->name] = $cookie->value;
            }
        }
        return $response;
    }

    private static function args()
    {
        return [
            'timeout' => self::TIMEOUT,
            'user-agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . '; ' . HSY_KEY . '/' . HSY_get_version() . ')',
        ];
    }

    private static function is_login_redirect($response)
    {
        $code = (int) wp_remote_retrieve_response_code($response);
        $location = (string) wp_remote_retrieve_header($response, 'location');
        return $code >= 300 && $code < 400 && false !== strpos($location, self::config()['login_path']);
    }

    /**
     * La página de login se reconoce por el campo password del formulario.
     */
    private static function is_login_page($html)
    {
        return (bool) preg_match('/<input[^>]+type=["\']password["\']/i', (string) $html);
    }

    /**
     * Mensaje de error que muestre el formulario de login (si lo hay).
     */
    private static function login_error($html)
    {
        if (preg_match('/<[^>]+class=["\'][^"\']*(?:alert|error|danger)[^"\']*["\'][^>]*>(.*?)<\/[a-z0-9]+>/is', (string) $html, $m)) {
            return trim(wp_strip_all_tags($m[1]));
        }
        return '';
    }
}
