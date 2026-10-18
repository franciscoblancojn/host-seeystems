---
name: hsey-plugin
description: >-
  Use ONLY when working on Host Seeystems plugin features: connection to the
  Swiss Sport B2B platform (host.seeystems.com), login/session (HSEY_CLIENT),
  catalog parsing (HSEY_PARSER), product sync (HSEY_API_PRODUCTS), data layer
  (HSEY_USE_DATA_*) or admin pages built with wordpress_utils. Contains the
  read-only platform rule, class references and validation rules.
---

# HSEY Plugin — Reglas Específicas del Plugin

## 1. Plataforma de SOLO LECTURA (regla crítica)

- Único POST permitido: login en `HSEY_CLIENT::login()`.
- Todo acceso a datos con `HSEY_CLIENT::get($path, $query)`.
- **Nunca** llamar: `cart`, `cart/add`, `cart/ids`, `orders`, `auth/logout`, `auth/cambiarPassword`, `auth/recover`, ni ningún POST/PUT/DELETE.
- `HSEY_CLIENT::logout()` solo borra el transient local; **no** llama a `/auth/logout`.
- Al explorar con curl: login + solo GET. Credenciales en `.env`, que nunca se lee en voz alta ni se commitea.
- `bin/harness.sh` falla si se rompe esta regla.

Detalle de la plataforma: `doc/DOC-PLATAFORMA.md`.

---

## 2. Cliente HTTP (`HSEY_CLIENT`)

- Usa `wp_remote_request()` con `redirection => 0` y un jar de cookies propio (`$COOKIES`, nombre → valor).
- Sesión en transient `HSEY_SESSION` (20 min). Se renueva en cada GET exitoso.
- Detección de sesión vencida: redirect a `/auth/login` o body con `csrf_token` + `password` → re-login una sola vez.
- Respuestas: `['status' => 'ok', 'code', 'location', 'content_type', 'body']` o `['status' => 'error', 'message']`. Mantener este contrato: las secciones lo pasan directo a `FWURespond::render()`.
- Errores HTTP y de login → `FWUSystemLog::add(HSEY_KEY, [...])` sin datos sensibles.

## 3. Parser (`HSEY_PARSER`)

- Fuente de verdad: JSON en `onclick="openProductModal({...})"` de cada `.product-card`. No scrapear el texto de la tarjeta.
- `nextPage()` sigue el `a.page-link` con texto "Siguiente" y quita el `#fragment`.
- Si la plataforma cambia el marcado: verificar con el tab **HTML** y ajustar solo `HSEY_PARSER`.

## 4. Sincronización (`HSEY_API_PRODUCTS::sync()`)

- Recorre desde `catalog_path` (default `/catalog`), máximo `MAX_PAGES`, evita ciclos con `$visited`.
- Guarda `['date', 'items']` en `HSEY_USE_DATA_PRODUCTS`.
- ~23 páginas / ~30 s: no bajar `set_time_limit(300)`. Para crecer, mover a WP-Cron o procesar por lotes.

## 5. Carga en WooCommerce (`HSEY_API_IMPORT`)

- No existe (SKU = `ref`, o meta `_hsey_id`) → crear con **todos** los campos. Existe → actualizar **solo** los activos en `getImport()['update_fields']` (default: solo `stock`).
- Nuevo campo: agregarlo a `FIELDS` (`key => [label, default]`) y aplicarlo en `apply()`; documentar en `doc/DOC-CARGA-WOOCOMMERCE.md`.
- Nunca cambiar el estado de productos existentes; `status` aplica solo al crear.
- Imágenes: reutilizar adjuntos por `_hsey_img_src`; no duplicar.
- Por lotes vía `wp_ajax_hsey_import_batch` (nonce `hsey_nonce`, `manage_options`); no procesar todo en una sola petición.

## 6. Capa de datos

| Clase | Opción | Campos |
|---|---|---|
| `HSEY_USE_DATA_CONFIG` | `HSEY_CONFIG` | `base_url`, `catalog_path`, `email`, `password` (cifrada), `import` |
| `HSEY_USE_DATA_PRODUCTS` | `HSEY_PRODUCTS` | `date`, `items` |

- Contraseña: solo vía `setPassword()` / `getPassword()`. Nunca imprimirla (el input muestra "(guardada)") ni loguearla.
- Campo de contraseña vacío al guardar = conservar la actual.
- Nueva opción → nueva subclase de `HSEY_USE_DATA_BASE` con su constante en `index.php`.

## 7. Páginas admin con `wordpress_utils`

- Submenú: `src/page/pages/{subpagina}/add.php` + `page.php`; tabs en `src/page/sections/{tab}.php`; require en `src/page/pages/_.php`.
- `page.php`: `FWUPage::css()`, `FWUPage::tabs($TAGS, $defaultTag)`, `FWUPage::js(HSEY_KEY)`.
- Formularios: `save` + `wp_nonce_field('hsey_{tab}', 'hsey_nonce')`; handler valida `current_user_can('manage_options')` y `check_admin_referer('hsey_{tab}', 'hsey_nonce')`.
- `FWUTooltip::render()` en cada `<th>`; `FWURespond::render($respond)` para el resultado.
- Todo dato de la plataforma se escapa (`esc_html`, `esc_url`, `esc_textarea`).

Ver `doc/DOC-LIBS.md`.

## 8. Checklist antes de terminar

1. `npm run check` sin FAILURES.
2. `CHANGELOG.md` actualizado; `CONTEXT.md` / `HOOKS.md` / `doc/` si cambió la arquitectura.
3. Sugerir commit (no ejecutar git add/commit/merge/push).
