<?php

// Variables disponibles: $config (HSY_USE_DATA_CONFIG::get()). Los campos van en hsy[<clave>].
// La contraseña nunca se imprime: vacío = conservar la guardada.

?>
<table class="form-table" role="presentation">
    <tr>
        <th scope="row"><label for="hsy-base-url">URL del catálogo</label></th>
        <td>
            <input type="url" id="hsy-base-url" name="hsy[base_url]" value="<?= esc_attr($config['base_url']) ?>" class="regular-text" placeholder="https://host.ejemplo.com/~tienda/">
            <p class="description">Raíz del catálogo (sin <code>auth/login</code>).</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="hsy-email">Correo</label></th>
        <td><input type="email" id="hsy-email" name="hsy[email]" value="<?= esc_attr($config['email']) ?>" class="regular-text" autocomplete="off"></td>
    </tr>
    <tr>
        <th scope="row"><label for="hsy-password">Contraseña</label></th>
        <td>
            <input type="password" id="hsy-password" name="hsy[password]" value="" class="regular-text" autocomplete="new-password"
                placeholder="<?= $config['password'] ? esc_attr('•••••••• (guardada)') : '' ?>">
            <?php if ($config['password']) : ?>
                <p class="description">Déjala vacía para conservar la guardada.
                    <label><input type="checkbox" name="hsy[password_clear]" value="1"> Borrar la contraseña guardada</label>
                </p>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="hsy-login-path">Ruta del login</label></th>
        <td><input type="text" id="hsy-login-path" name="hsy[login_path]" value="<?= esc_attr($config['login_path']) ?>" class="regular-text code"></td>
    </tr>
    <tr>
        <th scope="row"><label for="hsy-catalog-path">Ruta del catálogo</label></th>
        <td><input type="text" id="hsy-catalog-path" name="hsy[catalog_path]" value="<?= esc_attr($config['catalog_path']) ?>" class="regular-text code"></td>
    </tr>
    <tr>
        <th scope="row">Probar</th>
        <td>
            <button type="button" class="button" id="hsy-test" <?= HSY_USE_DATA_CONFIG::is_connected_config($config) ? '' : 'disabled' ?>>Probar conexión</button>
            <span class="spinner" id="hsy-test-spinner"></span>
            <div id="hsy-test-message"></div>
            <p class="description">Prueba con los datos guardados: guarda primero si los cambiaste.</p>
        </td>
    </tr>
</table>
