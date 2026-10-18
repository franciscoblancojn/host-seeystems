<?php

use franciscoblancojn\wordpress_utils\FWUSystemLog;

/**
 * Cliente HTTP de la plataforma B2B.
 * Maneja el login (csrf_token + cookie de sesión) y reutiliza la sesión guardada en un transient.
 */
class HSEY_CLIENT
{
    const SESSION_TTL = 20 * MINUTE_IN_SECONDS;
    const LOGIN_PATH = '/auth/login';

    private $CONFIG;
    private $COOKIES = [];

    public function __construct()
    {
        $this->CONFIG = new HSEY_USE_DATA_CONFIG();
        $session = get_transient(HSEY_SESSION);
        if (is_array($session)) {
            $this->COOKIES = $session;
        }
    }

    public function url($path = '')
    {
        if (preg_match('#^https?://#', $path)) {
            return $path;
        }
        return $this->CONFIG->getBaseUrl() . '/' . ltrim($path, '/');
    }

    /**
     * Inicia sesión en la plataforma.
     * @return array ['status' => 'ok'|'error', 'message' => string]
     */
    public function login()
    {
        $email = $this->CONFIG->getEmail();
        $password = $this->CONFIG->getPassword();
        if ($email === '' || $password === '') {
            return ['status' => 'error', 'message' => 'Configura el usuario y la contraseña.'];
        }

        $this->COOKIES = [];
        $page = $this->send('GET', self::LOGIN_PATH);
        if ($page['status'] !== 'ok') {
            return $page;
        }
        if (!preg_match('/name="csrf_token"\s+value="([^"]+)"/', $page['body'], $m)) {
            return ['status' => 'error', 'message' => 'No se encontró el csrf_token en el login.'];
        }

        $res = $this->send('POST', self::LOGIN_PATH, [
            'csrf_token' => $m[1],
            'email' => $email,
            'password' => $password,
        ]);
        if ($res['status'] !== 'ok') {
            return $res;
        }

        if ($this->isLoginResponse($res)) {
            FWUSystemLog::add(HSEY_KEY, [
                'type' => 'login_error',
                'code' => $res['code'],
                'location' => $res['location'],
            ]);
            $this->logout();
            return ['status' => 'error', 'message' => 'Login rechazado: revisa usuario y contraseña.'];
        }

        set_transient(HSEY_SESSION, $this->COOKIES, self::SESSION_TTL);
        return ['status' => 'ok', 'message' => 'Conexión exitosa.'];
    }

    public function logout()
    {
        $this->COOKIES = [];
        delete_transient(HSEY_SESSION);
    }

    /**
     * GET autenticado. Si la sesión expiró vuelve a hacer login una vez.
     */
    public function get($path, $query = [])
    {
        if (!empty($query)) {
            $path = add_query_arg($query, $path);
        }
        if (empty($this->COOKIES)) {
            $login = $this->login();
            if ($login['status'] !== 'ok') {
                return $login;
            }
        }
        $res = $this->send('GET', $path);
        if ($res['status'] === 'ok' && $this->isLoginResponse($res)) {
            $login = $this->login();
            if ($login['status'] !== 'ok') {
                return $login;
            }
            $res = $this->send('GET', $path);
        }
        if ($res['status'] === 'ok') {
            set_transient(HSEY_SESSION, $this->COOKIES, self::SESSION_TTL);
        }
        return $res;
    }

    private function isLoginResponse($res)
    {
        if (in_array($res['code'], [301, 302, 303], true)) {
            return strpos($res['location'], self::LOGIN_PATH) !== false;
        }
        return strpos($res['body'], 'name="csrf_token"') !== false
            && strpos($res['body'], 'name="password"') !== false;
    }

    private function send($method, $path, $body = null)
    {
        $args = [
            'method' => $method,
            'timeout' => 30,
            'redirection' => 0,
            'cookies' => $this->COOKIES,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (WordPress; ' . HSEY_KEY . ')',
                'Accept' => 'text/html,application/json;q=0.9,*/*;q=0.8',
            ],
        ];
        if ($body !== null) {
            $args['body'] = $body;
        }

        $response = wp_remote_request($this->url($path), $args);
        if (is_wp_error($response)) {
            FWUSystemLog::add(HSEY_KEY, [
                'type' => 'http_error',
                'path' => $path,
                'error' => $response->get_error_message(),
            ]);
            return ['status' => 'error', 'message' => $response->get_error_message()];
        }

        foreach (wp_remote_retrieve_cookies($response) as $cookie) {
            $this->COOKIES[$cookie->name] = $cookie->value;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            return ['status' => 'error', 'message' => 'HTTP ' . $code . ' en ' . $path, 'code' => $code];
        }

        return [
            'status' => 'ok',
            'code' => $code,
            'location' => (string) wp_remote_retrieve_header($response, 'location'),
            'content_type' => (string) wp_remote_retrieve_header($response, 'content-type'),
            'body' => (string) wp_remote_retrieve_body($response),
        ];
    }
}
