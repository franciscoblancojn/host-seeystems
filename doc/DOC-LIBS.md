# DOC-LIBS.md

> Convenciones y uso de `franciscoblancojn/wordpress_utils` en Host Seeystems.
> Toda interfaz del admin, el updater y los logs se construyen con esta librería.

---

## Instalación

La librería vive en `libs/` (Composer vendor renombrado). **Nunca edites `libs/` a mano.**
`composer.json` usa `"autoloader-suffix": "HSEY"` para que el autoloader (`ComposerAutoloaderInitHSEY`) no choque con otros plugins que usan la misma librería.

| Comando | Propósito |
|---|---|
| `npm run install` | `composer install --no-dev` + limpia archivos sobrantes + renombra `vendor` → `libs` |
| `npm run update` | Borra `libs/` y `composer.lock`, reinstala desde cero (trae la última `^1`) |
| `npm run optimize` | `composer install --no-dev --optimize-autoloader` (deja `vendor/`) |

Requisito: `libs/` usa tipos de retorno `: void`, así que el plugin necesita **PHP 7.1+**.

---

## Clases y API

Siempre importar con `use franciscoblancojn\wordpress_utils\{Clase};` al inicio del archivo.

### `FWUPage` — Layout de páginas admin

Patrón de cada submenú (`src/page/pages/{subpagina}/page.php`):

```php
use franciscoblancojn\wordpress_utils\FWUPage;

echo FWUPage::css();

$TAGS = [
    ['key' => 'productos', 'title' => 'Productos'],
    ['key' => 'html', 'title' => 'HTML'],
];
$defaultTag = $TAGS[0]['key'];
?>
<div id="page-<?= HSEY_KEY ?>" class="wrap">
    <h1>Host Seeystems</h1>
    <?php FWUPage::tabs($TAGS, $defaultTag); ?>
    <?php foreach ($TAGS as $value) { ?>
        <div class="tab-content <?= $value['key'] == $defaultTag ? "nav-tab-active" : "" ?>" id="<?= $value['key'] ?>">
            <?php require_once HSEY_DIR . 'src/page/sections/' . $value['key'] . ".php"; ?>
        </div>
    <?php } ?>
</div>
<?php
echo FWUPage::js(HSEY_KEY);
```

- `FWUPage::css()` → CSS del layout admin (tabs, mensajes, loader).
- `FWUPage::tabs($TAGS, $default)` → navegación por tabs (`#tag-{key}` en la URL abre ese tab).
- `FWUPage::js($pluginKey)` → JS de tabs + clase `fwue-loader` en `[type="submit"]` al hacer click.
- Alternativa: `FWUPage::render($pageKey, $title, $TAGS, $sectionsDir, $data)` arma todo en una llamada.

Para agregar un submenú: crear `pages/{subpagina}/add.php` (con `add_submenu_page(HSEY_KEY, ...)`) y `page.php`, y hacer require en `src/page/pages/_.php`.

### `FWUTooltip` — Ayuda contextual

```php
<?php FWUTooltip::render("Usuario", "Correo con el que se inicia sesión en la plataforma.") ?>
```

Se usa en los `<th>` de los formularios.

### `FWURespond` — Mensajes de resultado

```php
FWURespond::render(['status' => 'ok', 'message' => 'Conexión exitosa.']);
FWURespond::render(['status' => 'error', 'message' => '...']);
```

Acepta directamente las respuestas de `HSEY_CLIENT` y `HSEY_API_PRODUCTS::sync()` (`status` + `message`). Con `null` no imprime nada.

### `FWUCollapse` — Secciones plegables

```php
FWUCollapse::render($title, $contentHtml, $startOpen);
$html = FWUCollapse::html($title, $contentHtml, $startOpen);
```

### `FWUModal` — Diálogos modales

```php
echo FWUModal::css() . FWUModal::js();
```

### `FWUExportImport` — Exportar/importar JSON

```php
echo FWUExportImport::css() . FWUExportImport::js();
echo FWUExportImport::exportButtonHtml($ajaxAction, $payload, $filename);
echo FWUExportImport::importButtonHtml($modalId);
echo FWUExportImport::html($modalId, $title, $ajaxAction, $extraData, $showPostSelector);
```

Útil si se agrega exportar el catálogo a JSON (vía AJAX `hsey_*` con nonce).

### `FWUSystemLog` — Logs del plugin

```php
FWUSystemLog::add(HSEY_KEY, [
    'type'  => 'sync_products',
    'count' => count($products),
]);
```

- Inicializado en `index.php` con `FWUSystemLog::init(HSEY_KEY)` (solo en admin).
- Se ve en **Ajustes → HSEY_LOG** y desde la barra de admin.
- Opción `HSEY_LOG_KEY`, máximo 100 entradas.
- **Nunca** loguear contraseña ni cookies. **Nunca** `error_log()`, `var_dump()`, `print_r()`.

Eventos actuales: `save_config`, `login_error`, `http_error`, `sync_products`.

### `FWUUpdate` — Auto-update vía GitHub

Inicializado en `index.php`:

```php
FWUUpdate::init([
    'basename' => HSEY_BASENAME,
    'dir' => HSEY_DIR,
    'file' => "index.php",
    'path_repository' => 'franciscoblancojn/host-seeystems',
    'branch' => 'master',
    'token_array_split' => [/* token GitHub partido en caracteres */],
]);
```

- Consulta `releases/latest` del repo y compara con `Version:` de `index.php`.
- El paquete se descarga de `archive/refs/heads/master.zip`.
- Las releases se crean con `npm run push-v -- patch|minor|major` (solo el desarrollador).

---

## El loader `fwue-loader` y el evento `invalid`

`FWUPage::js()` agrega `fwue-loader` (spinner) al `[type="submit"]` clickeado. Si la validación HTML5 bloquea el envío (`required`, `type="email"`, `type="url"`), el spinner queda puesto. En secciones con campos validados agregar:

```javascript
document.addEventListener('invalid', function (e) {
    var form = e.target.form;
    if (!form) return;
    var btns = form.querySelectorAll('[type="submit"]');
    for (var i = 0; i < btns.length; i++) {
        btns[i].classList.remove('fwue-loader');
    }
}, true);
```

---

## Convenciones

1. **Namespace**: `franciscoblancojn\wordpress_utils\` — importar con `use`.
2. **Prefijo CSS**: la librería usa `fwue-`; el plugin usa `hsey-`.
3. **Paths**: `HSEY_DIR` y `HSEY_URL`.
4. **Logs**: `FWUSystemLog::add(HSEY_KEY, ...)`.
5. **Respuestas**: arrays `status` / `message` mostrados con `FWURespond`; en AJAX, `wp_send_json_success()` / `wp_send_json_error()`.
