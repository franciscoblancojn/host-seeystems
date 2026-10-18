<?php

use franciscoblancojn\wordpress_utils\FWUSystemLog;
use franciscoblancojn\wordpress_utils\FWURespond;
use franciscoblancojn\wordpress_utils\FWUTooltip;

$respond_config = null;
if (
    isset($_POST['save']) && $_POST['save'] == "config"
    && current_user_can('manage_options')
    && check_admin_referer('hsey_config', 'hsey_nonce')
) {
    $HSEY_USE_DATA_CONFIG->add([
        'base_url' => esc_url_raw(wp_unslash($_POST['base_url'] ?? '')),
        'catalog_path' => sanitize_text_field(wp_unslash($_POST['catalog_path'] ?? '')),
        'email' => sanitize_email(wp_unslash($_POST['email'] ?? '')),
    ]);
    // Contraseña vacía = conservar la actual
    $password = wp_unslash($_POST['password'] ?? '');
    if ($password !== '') {
        $HSEY_USE_DATA_CONFIG->setPassword($password);
    }
    FWUSystemLog::add(HSEY_KEY, [
        'type' => "save_config",
        'email' => $HSEY_USE_DATA_CONFIG->getEmail(),
    ]);

    $client = new HSEY_CLIENT();
    $client->logout();
    $respond_config = isset($_POST['test']) ? $client->login() : ['status' => 'ok', 'message' => 'Configuración guardada.'];
}

$CONFIG = $HSEY_USE_DATA_CONFIG->get();

?>
<form method="post">
    <?php FWURespond::render($respond_config) ?>
    <input type="hidden" name="save" value="config">
    <?php wp_nonce_field('hsey_config', 'hsey_nonce'); ?>
    <table class="form-table">
        <tr>
            <th scope="row">
                <?php FWUTooltip::render("URL plataforma", "URL base de la plataforma, sin /auth/login.") ?>
            </th>
            <td>
                <input
                    type="url"
                    name="base_url"
                    placeholder="<?= esc_attr(HSEY_BASE_URL_DEFAULT) ?>"
                    value="<?= esc_attr($HSEY_USE_DATA_CONFIG->getBaseUrl()) ?>"
                    class="regular-text" />
            </td>
        </tr>
        <tr>
            <th scope="row">
                <?php FWUTooltip::render("Ruta catálogo", "Ruta donde la plataforma lista los productos.") ?>
            </th>
            <td>
                <input
                    type="text"
                    name="catalog_path"
                    placeholder="/catalog"
                    value="<?= esc_attr($CONFIG['catalog_path'] ?? '/catalog') ?>"
                    class="regular-text" />
            </td>
        </tr>
        <tr>
            <th scope="row">
                <?php FWUTooltip::render("Usuario", "Correo con el que se inicia sesión en la plataforma.") ?>
            </th>
            <td>
                <input
                    type="email"
                    name="email"
                    placeholder="tu@email.com"
                    value="<?= esc_attr($HSEY_USE_DATA_CONFIG->getEmail()) ?>"
                    autocomplete="off"
                    class="regular-text" />
            </td>
        </tr>
        <tr>
            <th scope="row">
                <?php FWUTooltip::render("Contraseña", "Se guarda cifrada. Déjala vacía para conservar la actual.") ?>
            </th>
            <td>
                <input
                    type="password"
                    name="password"
                    placeholder="<?= $HSEY_USE_DATA_CONFIG->hasPassword() ? '•••••••• (guardada)' : 'Contraseña' ?>"
                    autocomplete="new-password"
                    class="regular-text" />
            </td>
        </tr>
    </table>

    <div class="content-btn">
        <button
            type="submit"
            name="submit"
            value="Guardar"
            class="button button-primary">
            Guardar
        </button>
        <button
            type="submit"
            name="test"
            value="1"
            class="button">
            Guardar y probar conexión
        </button>
    </div>
</form>
<?php
