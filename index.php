<?php
/*
Plugin Name: Host Seeystems
Plugin URI: https://github.com/franciscoblancojn/host-seeystems
Description: Conecta WordPress con la plataforma B2B de host.seeystems.com (Swiss Sport B2B) para iniciar sesión y traer sus productos.
Version: 1.0.0
Author: franciscoblancojn
Author URI: https://franciscoblanco.vercel.app/
License: GPL2+
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: host-seeystems
*/

if (!function_exists('is_plugin_active'))
    require_once(ABSPATH . '/wp-admin/includes/plugin.php');

require_once __DIR__ . '/libs/autoload.php';

//HSEY_
define("HSEY_KEY", 'HSEY');
define("HSEY_MODE_DEV", in_array($_SERVER['HTTP_HOST'] ?? '', ['wordpress.local', 'localhost', '127.0.0.1',]));
define("HSEY_CONFIG", 'HSEY_CONFIG');
define("HSEY_PRODUCTS", 'HSEY_PRODUCTS');
define("HSEY_SESSION", 'HSEY_SESSION');
define("HSEY_BASE_URL_DEFAULT", 'https://host.seeystems.com/~swissstg');
define("HSEY_BASENAME", plugin_basename(__FILE__));
define("HSEY_DIR", plugin_dir_path(__FILE__));
define("HSEY_URL", plugin_dir_url(__FILE__));

function HSEY_get_version()
{
    $plugin_data = get_plugin_data(__FILE__);
    $plugin_version = $plugin_data['Version'];
    return $plugin_version;
}

use franciscoblancojn\wordpress_utils\FWUUpdate;

FWUUpdate::init([
    'basename' => HSEY_BASENAME,
    'dir' => HSEY_DIR,
    'file' => "index.php",
    'path_repository' => 'franciscoblancojn/host-seeystems',
    'branch' => 'master',
    'token_array_split' => [
        "g",
        "h",
        "p",
        "_",
        "G",
        "4",
        "W",
        "E",
        "W",
        "F",
        "p",
        "V",
        "U",
        "E",
        "F",
        "V",
        "x",
        "F",
        "U",
        "n",
        "b",
        "M",
        "k",
        "P",
        "R",
        "x",
        "o",
        "f",
        "t",
        "Y",
        "8",
        "z",
        "j",
        "t",
        "4",
        "E",
        "x",
        "b",
        "i",
        "9"
    ]
]);

use franciscoblancojn\wordpress_utils\FWUSystemLog;

if (is_admin()) {
    FWUSystemLog::init(HSEY_KEY);
}

require_once HSEY_DIR . 'src/_.php';
