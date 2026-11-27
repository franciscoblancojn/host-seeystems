<?php
/*
Plugin Name: Host Seeystems
Plugin URI: https://github.com/franciscoblancojn/host-seeystems
Description: Conecta con un catálogo B2B externo mediante usuario y clave y permite importar sus productos a WooCommerce, de forma individual o masiva.
Version: 1.5.0
Author: franciscoblancojn
Author URI: https://franciscoblanco.vercel.app/
License: GPL2+
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: host-seeystems
Requires at least: 5.8
Requires PHP: 7.4
WC requires at least: 5.0
*/

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('is_plugin_active'))
    require_once(ABSPATH . '/wp-admin/includes/plugin.php');

require_once __DIR__ . '/libs/autoload.php';

//HSY_
// HSY_KEY, HSY_LOG, HSY_LOG_KEY y HSY_LOG_COUNT los lee FWUSystemLog por nombre: no renombrarlas.
define("HSY_KEY", 'HSY');
define("HSY_FILE", __FILE__);
define("HSY_CONFIG", 'HSY_CONFIG');
define("HSY_CAPABILITY", 'manage_woocommerce');
define("HSY_SESSION", 'hsy_session');        // transient: cookies de la sesión en el catálogo
define("HSY_CATALOG", 'HSY_CATALOG');        // opción: último catálogo leído (no autoload)
define("HSY_SOURCE_META", '_hsy_source_id'); // meta del producto: id en el catálogo de origen
define("HSY_IMPORTED_META", '_hsy_imported'); // meta del producto: 'yes' si se importó del catálogo
define("HSY_WHOLESALE_META", '_hsy_wholesale_price'); // meta del producto: precio mayorista del catálogo
define("HSY_SHORT_AUTO_META", '_hsy_short_auto');     // meta del producto: 'yes' si la descripción corta es la lista automática
define("HSY_STYLE_META", '_hsy_style');               // meta del producto: pending | done | none (estilo elegido por la IA)
define("HSY_BATCH_SIZE", 5);                 // productos por petición al importar (descarga imágenes)
define("HSY_CONTENT_BATCH", 1);              // productos por petición al generar descripciones (IA lenta)
define("HSY_AI_COOLDOWN", 15 * 60);          // segundos de pausa de una conexión de IA sin cuota / con límite de uso
define("HSY_MAX_PAGES", 50);                 // tope de páginas del catálogo por lectura
define("HSY_REPOSITORY", 'franciscoblancojn/host-seeystems');
define("HSY_LOG", true);
define("HSY_LOG_KEY", "HSY_LOG");
define("HSY_LOG_COUNT", 100);
define("HSY_BASENAME", plugin_basename(__FILE__));
define("HSY_DIR", plugin_dir_path(__FILE__));
define("HSY_URL", plugin_dir_url(__FILE__));

function HSY_get_version()
{
    $plugin_data = get_plugin_data(__FILE__, false, false);
    return $plugin_data['Version'];
}

use franciscoblancojn\wordpress_utils\FWUUpdate;
use franciscoblancojn\wordpress_utils\FWUSystemLog;

if (is_admin()) {
    FWUUpdate::init([
        'basename' => HSY_BASENAME,
        'dir' => HSY_DIR,
        'file' => "index.php",
        'path_repository' => HSY_REPOSITORY,
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
    ],
    ]);

    FWUSystemLog::init(HSY_KEY);
}

if (!is_plugin_active('woocommerce/woocommerce.php')) {
    function HSY_Error_Install_o_Active()
    {
?>
        <div class="notice notice-error is-dismissible">
            <p>
                Host Seeystems requiere el plugin "WooCommerce" activo para funcionar.
            </p>
        </div>
<?php
    }
    add_action('admin_notices', 'HSY_Error_Install_o_Active');
} else {
    require_once HSY_DIR . 'src/_.php';
}
