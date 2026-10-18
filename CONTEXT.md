# Host Seeystems — Contexto para IAs

> Plugin WordPress v1.0.0 — Contexto de arquitectura para que las IAs entren en contexto rápido.
> Reglas obligatorias en `AGENTS.md`.

---

## ¿Qué hace este plugin?

Conecta WordPress con la plataforma B2B **Swiss Sport B2B** (`https://host.seeystems.com/~swissstg`) en **modo solo lectura**:
- Guarda URL, usuario y contraseña (cifrada) de la plataforma.
- Inicia sesión (formulario con `csrf_token` + cookie de sesión `ssb2b`) y reutiliza la sesión.
- Recorre el catálogo paginado (`/catalog?page=N`) y guarda todos los productos (~1.100, 23 páginas).
- Lista los productos en el admin y permite inspeccionar el HTML autenticado de cualquier ruta.
- Carga los productos en WooCommerce por lotes: crea los que no existen con todos los datos y en los existentes actualiza solo los campos configurados (por defecto solo stock).
- Auto-update vía GitHub (`FWUUpdate`) y logs (`FWUSystemLog`).

---

## Constantes globales

| Constante | Valor | Dónde se usa |
|---|---|---|
| `HSEY_KEY` | `'HSEY'` | Prefijo de opciones, slugs de admin, logs |
| `HSEY_MODE_DEV` | `true` en `wordpress.local`, `localhost`, `127.0.0.1` | Funciones solo de desarrollo |
| `HSEY_CONFIG` | `'HSEY_CONFIG'` | `wp_options` → configuración |
| `HSEY_PRODUCTS` | `'HSEY_PRODUCTS'` | `wp_options` → productos sincronizados |
| `HSEY_SESSION` | `'HSEY_SESSION'` | Transient → cookies de sesión de la plataforma |
| `HSEY_BASE_URL_DEFAULT` | `'https://host.seeystems.com/~swissstg'` | URL por defecto de la plataforma |
| `HSEY_BASENAME` | `plugin_basename(__FILE__)` | Updater |
| `HSEY_DIR` | `plugin_dir_path(__FILE__)` | Base del plugin |
| `HSEY_URL` | `plugin_dir_url(__FILE__)` | URL base del plugin |

---

## Estructura de archivos

```
index.php                     → Header, constantes, FWUUpdate (GitHub), FWUSystemLog
composer.json                 → franciscoblancojn/wordpress_utils (autoloader-suffix HSEY)
package.json                  → Scripts de libs (install/update) y versión (push-v, push-tag)
libs/                         → Composer vendor renombrado (NO editar)
scripts/bump-version.sh       → Sube major|minor|patch en index.php y llama push-tag
bin/harness.sh                → Validaciones del proyecto (npm run check)
src/
  _.php                       → Cargador maestro
  data/
    base.php                  → HSEY_USE_DATA_BASE: CRUD genérico sobre wp_options
    config.php                → HSEY_USE_DATA_CONFIG: config + cifrado de contraseña
    products.php              → HSEY_USE_DATA_PRODUCTS: productos sincronizados
  api/
    client.php                → HSEY_CLIENT: login + GET autenticado con sesión
    parser.php                → HSEY_PARSER: JSON de .product-card + paginación
    products.php              → HSEY_API_PRODUCTS: sincroniza el catálogo completo
    import.php                → HSEY_API_IMPORT: carga en WooCommerce (AJAX por lotes)
  js/
    hsey-import.js            → Progreso de la carga (ES5 + jQuery)
  page/
    add.php                   → Menú principal (redirige a Configuracion)
    pages/config/             → Submenú "Configuracion" (tabs Conexión, Carga WooCommerce)
    pages/productos/          → Submenú "Productos" (tabs Productos, HTML)
    sections/config.php       → Form URL / ruta catálogo / usuario / contraseña + probar conexión
    sections/carga.php        → Campos a actualizar, fuente de precio y estado al crear
    sections/productos.php    → "Traer productos", "Cargar en WooCommerce" + tabla (imagen después de id)
    sections/html.php         → Inspector de HTML autenticado (GET)
doc/                          → Guías DOC-*.md
.rules/                       → Skills (hsey-plugin, php-wordpress)
.opencode/agents/             → Agentes opencode (hsey-dev, hsey-reviewer)
.claude/                      → Configuración Claude Code (permisos, hooks, skills, agentes)
```

---

## Clases y métodos clave

### HSEY_USE_DATA_BASE (`src/data/base.php`)
- `get()`, `set($data)`, `setField($key, $value)`, `add($data)` (merge). Persisten en `get_option($KEY)`.

### HSEY_USE_DATA_CONFIG (`src/data/config.php`)
- Campos: `base_url`, `catalog_path`, `email`, `password` (cifrada), `import` (configuración de carga).
- `getImport()` → `update_fields` (stock activo, el resto inactivo por defecto), `price_source` (`pvp`), `status` (`draft`).
- `getBaseUrl()` (default `HSEY_BASE_URL_DEFAULT`, sin `/` final), `getEmail()`, `getPassword()`, `hasPassword()`, `setPassword($plain)`.
- Cifrado AES-256-CBC con clave `sha256(AUTH_KEY . SECURE_AUTH_SALT . HSEY_KEY)` e IV aleatorio. Si cambian las salts de `wp-config.php`, la contraseña hay que volver a guardarla.

### HSEY_USE_DATA_PRODUCTS (`src/data/products.php`)
- Estructura: `['date' => 'Y-m-d H:i:s', 'items' => [producto, ...]]`.

### HSEY_CLIENT (`src/api/client.php`)
- `login()` → GET `/auth/login` (toma `csrf_token` y cookie) → POST `/auth/login` (`csrf_token`, `email`, `password`). OK si redirige fuera de `/auth/login` (hoy a `/brands`).
- `get($path, $query = [])` → GET autenticado; si no hay sesión o la respuesta es el login, re-loguea una vez.
- `logout()` → solo borra la sesión local (transient). **No** llama `/auth/logout`.
- `url($path)` → URL absoluta a partir de `getBaseUrl()`.
- Respuesta: `['status' => 'ok', 'code', 'location', 'content_type', 'body']` o `['status' => 'error', 'message']`.
- Sesión en transient `HSEY_SESSION` por 20 min. Peticiones con `redirection => 0`.

### HSEY_PARSER (`src/api/parser.php`)
- `parse($html)` → lee el atributo `onclick="openProductModal({...})"` de cada `.product-card` y hace `json_decode`.
- `nextPage($html)` → URL del enlace `a.page-link` "Siguiente" (sin `#catalogTop`), o `''`.

### HSEY_API_PRODUCTS (`src/api/products.php`)
- `sync()` → recorre desde `catalog_path` (default `/catalog`) siguiendo `nextPage()` hasta `MAX_PAGES` (50), guarda en `HSEY_PRODUCTS` y registra `sync_products` en el log. Sube `set_time_limit(300)` (~30 s para 23 páginas).

### HSEY_API_IMPORT (`src/api/import.php`)
- `init()` registra `wp_ajax_hsey_import_batch`.
- `batch($offset, $limit)` → `{ total, next, created, updated, errors[] }` sobre `HSEY_PRODUCTS`.
- `importProduct($item)` → busca por SKU (`ref`) o meta `_hsey_id`; crea con todos los campos o actualiza solo los activos.
- Constantes: `FIELDS`, `PRICE_SOURCES`, `STATUSES`, `ATTRIBUTES`, `BATCH_MAX`, `META_ID` (`_hsey_id`), `META_IMG_SRC` (`_hsey_img_src`).
- Detalle: `doc/DOC-CARGA-WOOCOMMERCE.md`.

---

## Producto (campos de la plataforma)

`id`, `ref`, `nombre`, `marca`, `precio`, `pvp`, `precio_mayorista`, `precio_especial`, `tipo_precio`, `genero`, `tamano`, `descripcion`, `correa_material`, `correa_color`, `caja_material`, `caja_color`, `resistencia_agua`, `stock`, `stock_reservado`, `transito`, `imgs` (array de URLs).

`precio` es el precio del cliente (número). `pvp` y los demás precios llegan como string decimal (`"204950.00"`). Detalle en `doc/DOC-PLATAFORMA.md`.

---

## wp_options / transients

| Clave | Tipo | Contenido |
|---|---|---|
| `HSEY_CONFIG` | option | `base_url`, `catalog_path`, `email`, `password` (cifrada), `import` |
| `HSEY_PRODUCTS` | option | `date`, `items` |
| `HSEY_SESSION` | transient | Cookies de sesión (`ssb2b`) |
| `HSEY_LOG_KEY` | option | Logs de `FWUSystemLog` (JSON, máx. 100, gestionado por la librería) |
| `github_updater_*` | transient | Cache de la release de GitHub (`FWUUpdate`) |
| `_hsey_id` | post meta (product) | `id` del producto en la plataforma |
| `_hsey_img_src` | post meta (attachment) | URL de origen de la imagen (evita duplicados) |

---

## Páginas admin

| Slug | Menú | Tabs |
|---|---|---|
| `HSEY` | Host Seeystems | Redirige a `HSEY_config` |
| `HSEY_config` | Configuracion | `config` (Conexión), `carga` (Carga WooCommerce) |
| `HSEY_productos` | Productos | `productos`, `html` |
| `HSEY_LOG` | Ajustes → logs | Generada por `FWUSystemLog::init()` |

---

## Flujo: Traer productos

1. Admin → Productos → **Traer productos** (POST al propio WP con nonce `hsey_productos`).
2. `HSEY_API_PRODUCTS::sync()` → `HSEY_CLIENT::get('/catalog')`.
3. Sin sesión → `login()` (único POST a la plataforma).
4. `HSEY_PARSER::parse()` por página + `nextPage()` hasta la última.
5. Guarda `HSEY_PRODUCTS` y muestra la tabla.

## Flujo: Cargar en WooCommerce

1. Admin → Productos → **Cargar N productos**.
2. `hsey-import.js` llama `wp_ajax_hsey_import_batch` en lotes de 5.
3. `HSEY_API_IMPORT::importProduct()` por producto: crear (todo) o actualizar (campos activos).
4. Imágenes por GET con `download_url()`, reutilizando adjuntos por `_hsey_img_src`.

---

## Dependencias

- WordPress 5.0+, PHP 7.1+ (por `libs/`), extensiones `openssl` y `dom`.
- WooCommerce (solo para la carga; `product_brand` para la marca).
- `franciscoblancojn/wordpress_utils` ^1 (en `libs/`). Ver `doc/DOC-LIBS.md`.

---

## Notas para debugging

- **Login falla**: revisar log `login_error` (code/location) y probar con "Guardar y probar conexión".
- **0 productos**: usar el tab **HTML** con `/catalog` y verificar que existan `.product-card` con `onclick="openProductModal(`. Si la plataforma cambia el marcado, ajustar `HSEY_PARSER`.
- **Sesión pegada**: `HSEY_CLIENT::logout()` (se llama al guardar la configuración).
- **Página Productos pesada**: renderiza todos los productos (~4 MB HTML con ~1.100 filas).

---

## Referencias

- `AGENTS.md` — reglas obligatorias.
- `HOOKS.md` — hooks y endpoints.
- `doc/DOC-PLATAFORMA.md` — plataforma, login, catálogo, regla de solo lectura.
- `doc/DOC-CARGA-WOOCOMMERCE.md` — carga en WooCommerce.
- `doc/DOC-LIBS.md` — uso de `wordpress_utils`.
- `doc/DOC-HARNESS.md` — validaciones y configuración de IA.
