<?php

class HSEY_USE_DATA_CONFIG extends HSEY_USE_DATA_BASE
{
    protected $KEY = HSEY_CONFIG;

    public function getBaseUrl()
    {
        $url = $this->DATA['base_url'] ?? '';
        return untrailingslashit($url ? $url : HSEY_BASE_URL_DEFAULT);
    }

    public function getEmail()
    {
        return $this->DATA['email'] ?? '';
    }

    public function getPassword()
    {
        return self::decrypt($this->DATA['password'] ?? '');
    }

    /**
     * Configuración de carga a WooCommerce con valores por defecto.
     * update_fields: campos que se actualizan si el producto ya existe (solo stock activo por defecto).
     */
    public function getImport()
    {
        $import = $this->DATA['import'] ?? [];
        $update_fields = [];
        foreach (HSEY_API_IMPORT::FIELDS as $key => $field) {
            $update_fields[$key] = isset($import['update_fields'][$key]) ? (bool) $import['update_fields'][$key] : $field[1];
        }
        $price_source = $import['price_source'] ?? '';
        $status = $import['status'] ?? '';
        return [
            'update_fields' => $update_fields,
            'price_source' => isset(HSEY_API_IMPORT::PRICE_SOURCES[$price_source]) ? $price_source : 'pvp',
            'status' => isset(HSEY_API_IMPORT::STATUSES[$status]) ? $status : 'draft',
        ];
    }

    public function hasPassword()
    {
        return !empty($this->DATA['password']);
    }

    public function setPassword($password)
    {
        $this->setField('password', self::encrypt($password));
    }

    // La contraseña se guarda cifrada con las salts de wp-config.php
    private static function cryptKey()
    {
        $salt = (defined('AUTH_KEY') ? AUTH_KEY : '') . (defined('SECURE_AUTH_SALT') ? SECURE_AUTH_SALT : '');
        return hash('sha256', $salt . HSEY_KEY, true);
    }

    private static function encrypt($value)
    {
        if ($value === '') {
            return '';
        }
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($value, 'aes-256-cbc', self::cryptKey(), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $cipher);
    }

    private static function decrypt($value)
    {
        if ($value === '') {
            return '';
        }
        $raw = base64_decode($value, true);
        if ($raw === false || strlen($raw) <= 16) {
            return '';
        }
        $plain = openssl_decrypt(substr($raw, 16), 'aes-256-cbc', self::cryptKey(), OPENSSL_RAW_DATA, substr($raw, 0, 16));
        return $plain === false ? '' : $plain;
    }
}
