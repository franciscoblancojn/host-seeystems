# Host Seeystems — HOOKS.md

> Referencia de hooks, endpoints y formularios del plugin. Mantener actualizado al agregar hooks (ver `AGENTS.md`).

---

## 1. Acciones de WordPress

| Hook | Archivo | Callback | Propósito |
|---|---|---|---|
| `admin_menu` | `src/page/add.php` | closure | Menú principal `HSEY` (icono `dashicons-cloud`) |
| `admin_menu` | `src/page/pages/config/add.php` | closure → `HSEY_PAGE_CONFIG_VIEW` | Submenú `HSEY_config` y quita el submenú duplicado |
| `admin_menu` | `src/page/pages/productos/add.php` | closure → `HSEY_PAGE_PRODUCTOS_VIEW` | Submenú `HSEY_productos` |
| `wp_ajax_hsey_import_batch` | `src/api/import.php` | `HSEY_API_IMPORT::ajaxBatch` | Carga un lote de productos en WooCommerce |
| `admin_menu` | `libs/…/FWUSystemLog.php` | `FWUSystemLog::init(HSEY_KEY)` | Página `HSEY_LOG` en Ajustes |
| `admin_bar_menu` | `libs/…/FWUSystemLog.php` | `FWUSystemLog::init(HSEY_KEY)` | Acceso al log desde la barra de admin |

## 2. Filtros de WordPress

| Hook | Archivo | Propósito |
|---|---|---|
| `site_transient_update_plugins` | `libs/…/FWUUpdate.php` | Ofrece la última release de `franciscoblancojn/host-seeystems` (solo en `plugins.php` / `update.php`) |
| `plugin_action_links_{HSEY_BASENAME}` | `libs/…/FWUUpdate.php` | Botón "Actualizar" en la lista de plugins |

## 3. Hooks propios

Ninguno por ahora. Si se agregan, usar prefijo `hsey_` y documentarlos aquí.

## 4. Formularios admin (POST al propio WordPress)

| Tab | `save` | Nonce (action / campo) | Capability | Acción |
|---|---|---|---|---|
| `config` | `config` | `hsey_config` / `hsey_nonce` | `manage_options` | Guarda la config; con `test` hace `HSEY_CLIENT::login()` |
| `carga` | `carga` | `hsey_carga` / `hsey_nonce` | `manage_options` | Guarda `HSEY_CONFIG['import']` (campos a actualizar, precio, estado) |
| `productos` | `productos` | `hsey_productos` / `hsey_nonce` | `manage_options` | `HSEY_API_PRODUCTS::sync()` |
| `html` | `html` | `hsey_html` / `hsey_nonce` | `manage_options` | `HSEY_CLIENT::get($path)` y muestra el body escapado |

## 5. AJAX Endpoints

| Action | Nonce | Capability | Parámetros | Respuesta |
|---|---|---|---|---|
| `hsey_import_batch` | `hsey_nonce` (campo `nonce`) | `manage_options` | `offset`, `limit` (máx. 20) | `success` + `{ total, next, created, updated, errors[] }` |

Script: `src/js/hsey-import.js` (encolado en Productos, datos en `HSEY_IMPORT`). Convención: `wp_ajax_hsey_*`, nonce `hsey_nonce`, handlers en `src/api/`, respuesta `wp_send_json_success()` / `wp_send_json_error()`.

## 6. REST API

Ninguna.

## 7. Peticiones a la plataforma (solo lectura)

| Método | Ruta | Dónde |
|---|---|---|
| GET | `/auth/login` | `HSEY_CLIENT::login()` (obtener `csrf_token`) |
| POST | `/auth/login` | `HSEY_CLIENT::login()` — **único POST permitido** |
| GET | `/catalog?page=N` | `HSEY_API_PRODUCTS::sync()` |
| GET | cualquier ruta | Tab HTML (inspección) |
| GET | `/img/productos/...` | `HSEY_API_IMPORT` (descarga de imágenes con `download_url()`) |

Rutas prohibidas: ver `doc/DOC-PLATAFORMA.md`.
