# Host Seeystems — Reglas para IAs

## Código
- **PHP 7.4+**, estándares de WordPress. Clases y funciones con prefijo `HSY_`.
- **JavaScript ES5 + jQuery** (sin arrow functions, `let`/`const` ni template literals). Funciones con prefijo `hsy_`.
- **CSS** con prefijo `hsy-` (las clases `fwue-` son de `wordpress_utils`).
- Escapar toda salida (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`) y sanitizar toda entrada.

## Seguridad
- Guardar ajustes: `check_admin_referer(HSY_API_SETTINGS::ACTION)` + `current_user_can(HSY_CAPABILITY)`.
- `FWUUpdate` lee el token de `index.php` (`token_array_split`). Es el mismo token de los demás plugins del autor (solo lee repos públicos) y va troceado a propósito. No agregar otras claves o secretos al código: `npm run check` falla si encuentra un token escrito de corrido.
- Las credenciales del catálogo y la API key de Serper se guardan solo en `HSY_CONFIG` (wp_options, sin autoload). Los secretos (`HSY_USE_DATA_CONFIG::SECRETS`) **nunca** se imprimen en el HTML ni se escriben en el log; el campo vacío conserva el guardado.
- AJAX (`HSY_API_IMPORT`): `guard()` con `check_ajax_referer(HSY_API_IMPORT::NONCE)` + `current_user_can(HSY_CAPABILITY)`.

## Arquitectura
- `index.php`: cabecera y constantes `HSY_*`. Sin lógica. WooCommerce es **obligatorio**: sin él solo se muestra un aviso en el admin.
- `src/_.php`: cargador maestro (data → services → hooks → api → page). Cada carpeta tiene su `_.php`; un archivo nuevo se agrega a ese `_.php` con `require_once HSY_DIR . 'src/…'`.
- `src/data/`: opciones de `wp_options` (`HSY_USE_DATA_BASE` y sus hijas). `HSY_USE_DATA_CONFIG::DEFAULTS` es la única lista de ajustes; `sanitize()` la normaliza.
- `src/services/`: lógica (clases `HSY_*` estáticas). Una sola fuente por regla de negocio.
- `src/hooks/`: integración con WordPress / WooCommerce (`add_action` / `add_filter`).
- `src/api/`: entradas `admin-post` y AJAX.
- `src/page/`: menú (`add.php`), página con pestañas `FWUPage` (`page.php`) y una sección por pestaña (`sections/<key>.php`).
- Reglas propias (una sola fuente para cada cosa):
  - `HSY_CLIENT` es la **única** clase que habla con el catálogo de origen (login por formulario con `csrf_token`, cookies en el transient `HSY_SESSION`, re-login automático si la sesión caduca). Solo pide URLs del mismo host que `base_url`.
  - `HSY_PARSER` es la **única** que conoce el HTML del origen y lo convierte en el producto normalizado documentado en `HSY_IMPORTER`. Si el origen cambia su HTML, solo se toca el parser.
  - `HSY_USE_DATA_CATALOG` (opción `HSY_CATALOG`, sin autoload) es la **única** fuente de lo que se puede importar: la tabla y los lotes leen de ahí, no del origen.
  - `HSY_IMPORTER` es la **única** que escribe productos. Un producto importado se reconoce por la meta `_hsy_source_id` (y si no, por el SKU). Las imágenes se reutilizan por `_hsy_source_url`.
  - Importación por lotes de `HSY_BATCH_SIZE` desde el JS (sin peticiones largas ni cron).
  - Mapeo fijo: marca → `product_brand` (la equivalente según `HSY_BRANDS`; si no hay, se crea con `HSY_PARSER::brand_name()`, «ARMANI EXCHANGE» → «Armani Exchange»); referencia → SKU; características → atributos + descripción corta; **PVP sugerido → precio normal** (el rebajado no se toca); **precio mayorista → `HSY_WHOLESALE_META`**, que se actualiza siempre (aunque «actualizar existentes» esté apagado) y se muestra en el editor (`HSY_HOOK_EDITOR`).
  - Todo producto importado lleva `HSY_IMPORTED_META = 'yes'`: es lo que identifica a los productos del plugin (stock mínimo, editor).
  - `HSY_STOCK` es la **única** fuente de la regla del stock mínimo: con `min_stock` unidades o menos → 0. Se aplica al importar y en `woocommerce_product_set_stock` (`HSY_HOOK_STOCK`), solo a productos importados.
  - `HSY_CONTENT` es la **única** que escribe la descripción y la descripción corta definitivas, y la única fuente de «pendiente» (importado sin descripción, o con la descripción corta automática: meta `HSY_SHORT_AUTO_META` = 'yes' o, en productos antiguos, la lista `<ul><li><strong>X:</strong>…`). Nunca reemplaza texto escrito a mano o generado antes. Usa la IA del plugin IA Conector (`IACON_AI::get($key)->sendPrompt()`, opcional: se comprueba con `class_exists`) con las conexiones de `ai_connections` **en orden de prioridad**: `HSY_CONTENT::ask()` es la única que llama a la IA y pasa a la siguiente si una falla; si el error es de cuota/límite (`is_limit_error()`), la conexión queda en pausa `HSY_AI_COOLDOWN` (transient `hsy_ai_pause_<md5>`). Una llamada por campo con los prompts editables `ai_prompt_description` / `ai_prompt_short` (vacío = `DEFAULT_PROMPT_*`). Las variables de los prompts se definen **solo** en `HSY_CONTENT::VARIABLES` + `variables()`: una variable nueva se agrega en los dos. La respuesta es el HTML del campo y se limpia con `HSY_CONTENT::ALLOWED_TAGS`. `preview()` prueba prompts sin guardar y no escribe nada.
  - IA Conector solo carga sus clases con `is_admin()` (incluye admin-ajax): la generación solo funciona desde el admin/AJAX, no en cron ni en el frontend. Se ejecuta de a `HSY_CONTENT_BATCH` producto por petición.
  - `HSY_BRANDS` es la **única** que empareja y crea marcas (`product_brand`): clave normalizada (`key()`: sin mayúsculas, acentos, espacios, signos ni `FILLER`), primero por nombre y después por slug, y ante empate la de más productos. No añadir a `FILLER` palabras que distingan marcas (p. ej. «joyas»).
  - `HSY_CATEGORIES` es la **única** que decide las categorías: `category_map` por género (vacío = `default_map()` por nombre), joyas por palabras de la marca (`jewelry_words` → `jewelry_category` → Mujer/Hombre, se crean), y estilos = hijos de las categorías asignadas más profundas (sin ofertas). El estilo lo elige la IA en `HSY_CONTENT::classify_style()` (meta `HSY_STYLE_META`: pending → done | none). Solo asigna si el producto no tiene categorías (`has_categories()`, sin contar «Sin categorizar»): las ya asignadas no se tocan nunca al reimportar.
  - Si `HSY_IMPORTER` cambia el nombre de un producto existente, cambia también el slug (`wp_unique_post_slug`); la URL vieja la redirige con 301 `HSY_HOOK_REDIRECT` (404 + `_wp_old_slug` de un producto importado publicado). No quitar esa redirección: las URLs de producto pueden no tener base.
  - Joyas: `HSY_PARSER::jewelry_attributes()` es la **única** conversión Correa/Caja → Material/Color (la usan el importador y `HSY_CONTENT::variables()`). `HSY_CONTENT::fill($id, $config, true)` regenera (reescribe descripción y corta aunque existan).
  - `HSY_LAYOUT` es la **única** fuente del HTML de la descripción larga (estructura, tabla de especificaciones con filas/emojis fijos y estilos en línea, cierre). La IA solo devuelve textos en JSON (`intro`, `beneficios`, `caracteristicas`); no pedirle tablas ni títulos. Fila nueva en la tabla → `WATCH_ROWS`/`JEWELRY_ROWS` + `rows()`.
  - `HSY_STATS` calcula los contadores de la pestaña Productos y es la **única** que publica productos: solo importados, en borrador y con **contenido completo** (descripción + descripción corta + categorías: `HSY_CONTENT::is_pending` y `HSY_CATEGORIES::has_categories`), comprobado otra vez justo antes de publicar. El filtro «Con contenido faltante» del JS usa la misma regla.
  - `HSY_DESCRIBER` busca la descripción en Google vía Serper.dev (la Custom Search JSON API de Google no admite clientes nuevos y se apaga el 2027-01-01). Solo acepta páginas o snippets que contengan **la referencia exacta** y nunca reemplaza una descripción existente. No sortear bloqueos (403/captcha) de las páginas de terceros.
- Plugin genérico: **no** usar nombres ni textos de un sitio concreto (el contenido por defecto es solo un ejemplo editable).
- Rendimiento: CSS/JS del admin solo en la página del plugin; en el sitio, solo donde se usa.
- Toda operación relevante queda en el log: `HSY_LOG_SERVICE::add('tipo', [...])`.
- `libs/` es Composer (`npm run install`) y se versiona: el zip de `master` que instala `FWUUpdate` lo necesita. No editar a mano.

## Publicar
- Anotar los cambios en `CHANGELOG.md` bajo `## [Sin publicar]`.
- `npm run push-v -- patch|minor|major`.
