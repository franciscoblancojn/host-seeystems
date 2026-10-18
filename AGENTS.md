# Host Seeystems — Reglas para IAs

Este archivo contiene las reglas, validaciones y convenciones que toda IA debe seguir al programar en este proyecto.
Para arquitectura, clases y flujos ver `CONTEXT.md`; para hooks ver `HOOKS.md`.

---

## 0. Regla principal: la plataforma es de SOLO LECTURA

El plugin se conecta a `host.seeystems.com` (Swiss Sport B2B) **solo para leer**.

- El **único POST** permitido es el login (`HSEY_CLIENT::login()` → `/auth/login`).
- Todo lo demás es `GET` (`HSEY_CLIENT::get()`).
- **Prohibido** llamar rutas que crean o modifican datos: `cart`, `cart/add`, `orders`, `auth/logout`, `auth/cambiarPassword`, `auth/recover`, o cualquier otro POST/PUT/DELETE.
- Al explorar la plataforma (curl, pestaña HTML del admin) aplica la misma regla.
- `bin/harness.sh` falla si aparece otro POST o alguna de esas rutas en `src/`.

Ver `doc/DOC-PLATAFORMA.md`.

---

## 1. Estándares de Código

### PHP
- **WordPress Coding Standards**: Sigue los estándares de codificación de WordPress para PHP.
- **PHP 7.1+**: El código del plugin evita sintaxis moderna (nullsafe `?->`, named arguments, match, readonly, typed properties, arrow functions `fn`). El operador `??` está permitido. `libs/` usa `: void`, por eso el mínimo real es 7.1.
- **Nombrado**: Las clases usan prefijo `HSEY_` (ej: `HSEY_CLIENT`, `HSEY_PARSER`). Métodos y propiedades en `camelCase` o `UPPER_SNAKE` para constantes.
- **Sanitización**: Toda salida de datos debe escaparse. Usa `esc_html()`, `esc_attr()`, `esc_url()`, `esc_textarea()`, `wp_kses_post()` según contexto.
- **Nonces**: Todo formulario y AJAX debe verificar nonce con `check_admin_referer()`, `wp_verify_nonce()` o `check_ajax_referer()`.
- **Capabilities**: Toda operación admin debe verificar `current_user_can('manage_options')`.

### JavaScript
- **ES5**: El plugin soporta WordPress 5.0+, usa ES5 (no arrow functions, no let/const, no template literals).
- **jQuery**: Usa `jQuery(function($){ ... })` para DOM ready.
- **AJAX**: Toda llamada AJAX debe usar `jQuery.ajax()` con action registrada via `wp_ajax_*` y nonce.
- **Nombrado**: Funciones en `snake_case` con prefijo `hsey_`.

### CSS
- **Prefijo**: Todas las clases CSS deben llevar prefijo `hsey-` (las de la librería usan `fwue-`).
- **Especificidad**: Evita `!important`. Usa clases con la especificidad adecuada.

---

## 2. Arquitectura del Plugin

### Sistema de Archivos
- `index.php` → Plugin header, constantes, `FWUUpdate` y `FWUSystemLog`. No agregues lógica aquí.
- `src/_.php` → Cargador maestro. Todo nuevo módulo debe ser require desde aquí o desde `src/*/_.php`.
- `src/api/` → Cliente HTTP de la plataforma (`HSEY_CLIENT`), parser (`HSEY_PARSER`) y sincronización (`HSEY_API_PRODUCTS`).
- `src/data/` → Capa de datos (wp_options CRUD).
- `src/page/` → Páginas admin: `pages/{subpagina}/add.php` registra el submenú, `pages/{subpagina}/page.php` arma los tabs y `sections/{tab}.php` contiene cada tab.
- `libs/` → Composer vendor (`franciscoblancojn/wordpress_utils`). Ver `doc/DOC-LIBS.md`.
- `bin/harness.sh` → Validaciones del proyecto (`npm run check`). Ver `doc/DOC-HARNESS.md`.

### Interfaces admin, updater y logs
Usa siempre `franciscoblancojn/wordpress_utils`:
- Páginas y tabs: `FWUPage::css()`, `FWUPage::tabs()`, `FWUPage::js(HSEY_KEY)`.
- Ayuda en formularios: `FWUTooltip::render()`. Resultados: `FWURespond::render()`.
- Modales, colapsables, export/import: `FWUModal`, `FWUCollapse`, `FWUExportImport`.
- Updater: `FWUUpdate::init()` (solo en `index.php`). Logs: `FWUSystemLog`.
- No crees componentes UI propios si la librería ya los tiene.

### Constantes
Usa las constantes definidas en `index.php`:
- `HSEY_KEY` para prefijos de opciones y slugs
- `HSEY_CONFIG` configuración (URL, ruta catálogo, usuario, contraseña cifrada)
- `HSEY_PRODUCTS` productos sincronizados
- `HSEY_SESSION` transient con las cookies de sesión
- `HSEY_BASE_URL_DEFAULT`, `HSEY_DIR`, `HSEY_URL`
- Nunca hardcodees strings como `'HSEY'` o `'HSEY_CONFIG'`

### wp_options
Toda opción global debe usar `HSEY_USE_DATA_BASE` o una subclase. No uses `add_option()`/`update_option()` directamente fuera de la capa data.

---

## 3. Validaciones de Seguridad

1. **Nunca** hardcodees credenciales. La contraseña se guarda cifrada vía `HSEY_USE_DATA_CONFIG::setPassword()` y nunca se imprime en el admin.
2. **Nunca** leas, imprimas ni subas el `.env` (credenciales de prueba). Está en `.gitignore`.
3. **Siempre** sanitiza input: `$_POST`, `$_GET`, `$_REQUEST` deben pasar por `wp_unslash()` + `sanitize_text_field()`, `sanitize_email()`, `esc_url_raw()`, `intval()`, etc.
4. **Siempre** valida nonces en formularios y AJAX.
5. **Siempre** valida capabilities: `current_user_can('manage_options')`.
6. **Nunca** confíes en el HTML/JSON de la plataforma: escápalo siempre al mostrarlo.

---

## 4. Convenciones del Proyecto

### Formularios admin
- Patrón: `<input type="hidden" name="save" value="{tab}">` + `wp_nonce_field('hsey_{tab}', 'hsey_nonce')`.
- El handler va al inicio de `src/page/sections/{tab}.php` y valida `save`, `current_user_can()` y `check_admin_referer()`.

### AJAX Endpoints
- Action registrada: `wp_ajax_{action}` donde action usa prefijo `hsey_`.
- Nonce action: `hsey_nonce`.
- Los handlers deben estar en `src/api/`.
- Respuesta siempre en JSON: `wp_send_json_success($data)` o `wp_send_json_error($message)`.

### Hooks
- Acciones: `add_action('hook', 'callback', priority)`.
- Filtros: `add_filter('hook', 'callback', priority, args)`.
- Prefijo `hsey_` para hooks propios. Documenta todo hook nuevo en `HOOKS.md`.

### Logging
- Usa siempre `FWUSystemLog::add(HSEY_KEY, [...])` para errores de conexión y eventos relevantes.
- Nunca registres la contraseña ni las cookies de sesión en el log.
- No uses `error_log()`, `var_dump()`, `print_r()` en producción.

### Documentación
- Cambios de arquitectura → `CONTEXT.md`. Hooks nuevos → `HOOKS.md`. Guías → `doc/DOC-*.md` (crear con `bash bin/harness.sh doc <Nombre>`).
- Cada cambio funcional se anota en `CHANGELOG.md`.

---

## 5. Git Workflow

1. **Commits**: No hacer commits automáticamente, solo dar sugerencias de commits.
2. **Prohibido para la IA**: `git add`, `git commit`, `git merge`, `git push`, `npm run push-v`, `npm run push-tag`. Están bloqueados en `opencode.json`, `.claude/settings.json` y el hook `.claude/hooks/block-git-write.sh`.
3. **Permitido**: `git status`, `git diff`, `git log`, `git show` (lectura).
4. **Release** (solo el desarrollador): `npm run push-v -- patch|minor|major` sube la versión en `index.php`, sincroniza `package.json`/`README.md`, hace commit, tag y push. `FWUUpdate` toma la última release de GitHub.

---

## 6. Antes de terminar una tarea

1. `npm run check` (o `bash bin/harness.sh`) sin FAILURES.
2. Actualizar `CHANGELOG.md` y, si aplica, `CONTEXT.md` / `HOOKS.md` / `doc/`.
3. Sugerir el mensaje de commit.

---

## 7. Lo que NO debes hacer

- ✗ NO hagas POST ni llames rutas que modifiquen datos en la plataforma (solo login).
- ✗ NO modifiques `index.php` (plugin header) salvo pedido explícito.
- ✗ NO elimines el prefijo `HSEY_` de ninguna clase/función.
- ✗ NO agregues dependencias npm/composer sin autorización explícita.
- ✗ NO edites archivos en `libs/` (vendor de Composer). Se regenera con `npm run install` / `npm run update`.
- ✗ NO uses sintaxis moderna de PHP (`?->`, named arguments, match, readonly, typed properties, `fn`).
- ✗ NO hardcodees URLs o paths — usa `HSEY_URL`, `HSEY_DIR`, `HSEY_BASE_URL_DEFAULT`.
- ✗ NO añadas archivos nuevos sin require desde `src/_.php` o desde subcarpetas `src/*/_.php`.
- ✗ NO ejecutes `git add`, `git commit`, `git merge`, `git push`.
