<?php

use franciscoblancojn\wordpress_utils\FWURespond;
use franciscoblancojn\wordpress_utils\FWUTooltip;

// Herramienta para inspeccionar el HTML autenticado de la plataforma y ajustar HSEY_PARSER
$respond_html = null;
$path_html = '/catalog';
if (
    isset($_POST['save']) && $_POST['save'] == "html"
    && current_user_can('manage_options')
    && check_admin_referer('hsey_html', 'hsey_nonce')
) {
    $path_html = '/' . ltrim(sanitize_text_field(wp_unslash($_POST['path'] ?? '')), '/');
    $respond_html = (new HSEY_CLIENT())->get($path_html);
}

?>
<form method="post">
    <?php if ($respond_html && $respond_html['status'] !== 'ok') FWURespond::render($respond_html) ?>
    <input type="hidden" name="save" value="html">
    <?php wp_nonce_field('hsey_html', 'hsey_nonce'); ?>
    <table class="form-table">
        <tr>
            <th scope="row">
                <?php FWUTooltip::render("Ruta", "Ruta de la plataforma a consultar con la sesión iniciada.") ?>
            </th>
            <td>
                <input type="text" name="path" value="<?= esc_attr($path_html) ?>" class="regular-text" />
                <button type="submit" name="submit" class="button">Consultar</button>
            </td>
        </tr>
    </table>
</form>
<?php if ($respond_html && $respond_html['status'] === 'ok') { ?>
    <p>
        HTTP <strong><?= esc_html($respond_html['code']) ?></strong>
        <?= $respond_html['location'] ? '→ ' . esc_html($respond_html['location']) : '' ?>
        — <?= esc_html($respond_html['content_type']) ?>
    </p>
    <textarea readonly class="large-text code" rows="30"><?= esc_textarea($respond_html['body']) ?></textarea>
<?php } ?>
<?php
