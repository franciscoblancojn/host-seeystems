# Host Seeystems — Reglas para IAs

Este archivo contiene las reglas, validaciones y convenciones que toda IA debe seguir al programar en este proyecto.

---

## 1. Estándares de Código

### PHP
- **WordPress Coding Standards**: Sigue los estándares de codificación de WordPress para PHP.
- **PHP 7.0+**: No uses sintaxis moderna de PHP (nullsafe `?->`, named arguments, match, readonly properties, etc). El operador `??` (null coalescing) está permitido.
- **Nombrado**: Las clases usan prefijo `HSEY_` (ej: `HSEY_AI`, `HSEY_CONTENT`). Métodos y propiedades en `camelCase` o `UPPER_SNAKE` para constantes.
- **Sanitización**: Toda salida de datos debe escaparse. Usa `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()` según contexto.
- **Nonces**: Todo formulario y AJAX debe verificar nonce con `wp_verify_nonce()` o `check_ajax_referer()`.
- **Capabilities**: Toda operación admin debe verificar `current_user_can()`.

### JavaScript
- **ES5**: El plugin soporta WordPress 5.0+, usa ES5 (no arrow functions, no let/const, no template literals).
- **jQuery**: Usa `jQuery(function($){ ... })` para DOM ready.
- **AJAX**: Toda llamada AJAX debe usar `jQuery.ajax()` con action registrada via `wp_ajax_*` y nonce.
- **Nombrado**: Funciones en `snake_case` con prefijo `hsey_` (ej: `hsey_save_custom_field`).

### CSS
- **Prefijo**: Todas las clases CSS deben llevar prefijo `hsey-`.
- **Especificidad**: Evita `!important`. Usa clases con la especificidad adecuada.

---

## 2. Arquitectura del Plugin

### Sistema de Archivos
- `index.php` → Plugin header y constantes globales. No agregues lógica aquí.
- `src/_.php` → Cargador maestro. Todo nuevo módulo debe ser require desde aquí.
- `src/api/` → Cliente HTTP de la plataforma (`HSEY_CLIENT`), parser (`HSEY_PARSER`) y sincronización de productos.
- `src/data/` → Capa de datos (wp_options CRUD).
- `src/page/` → Páginas admin.

### Constantes
Usa las constantes definidas en `index.php`:
- `HSEY_KEY` para prefijos de opciones
- `HSEY_CONFIG` configuración (URL, usuario, contraseña cifrada)
- `HSEY_PRODUCTS` productos sincronizados
- `HSEY_SESSION` transient con las cookies de sesión
- Nunca hardcodees strings como `'HSEY'` o `'HSEY_CONFIG'`

### wp_options
Toda opción global debe usar `HSEY_USE_DATA_BASE` o una subclase. No uses `add_option()`/`update_option()` directamente fuera de la capa data.

---

## 3. Validaciones de Seguridad

1. **Nunca** hardcodees credenciales. La contraseña se guarda cifrada vía `HSEY_USE_DATA_CONFIG::setPassword()` y nunca se imprime en el admin.
2. **Siempre** sanitiza input: `$_POST`, `$_GET`, `$_REQUEST` deben pasar por `sanitize_text_field()`, `intval()`, etc.
3. **Siempre** valida nonces en formularios y AJAX.
4. **Siempre** valida capabilities: `current_user_can('manage_options')`.
5. **Nunca** confíes en el HTML de la plataforma: escápalo siempre al mostrarlo.

---

## 4. Convenciones del Proyecto

### AJAX Endpoints
- Action registrada: `wp_ajax_{action}` donde action usa prefijo `hsey_`.
- Nonce action: `hsey_nonce`.
- Los handlers deben estar en `src/api/` o en el archivo correspondiente.
- Respuesta siempre en JSON: `wp_send_json_success($data)` o `wp_send_json_error($message)`.

### Hooks
- Acciones: `add_action('hook', 'callback', priority)`.
- Filtros: `add_filter('hook', 'callback', priority, args)`.
- No registres hooks en el scope global. Deben estar dentro de funciones o métodos.

### Logging
- Usa siempre `FWUSystemLog::add(HSEY_KEY, $message)` para errores de conexión.
- No uses `error_log()`, `var_dump()`, `print_r()` en producción.

---

## 5. Git Workflow

1. **Commits**: No hacer commits actumaticamente, solo dar sugerencias de commits.

---

## 6. Lo que NO debes hacer

- ✗ NO modifiques `index.php` (plugin header).
- ✗ NO elimines el prefijo `HSEY_` de ninguna clase/función.
- ✗ NO agregues dependencias npm/composer sin autorización explícita.
- ✗ NO edites archivos en `libs/` (vendor de Composer).
- ✗ NO uses sintaxis moderna de PHP (>=7.0) — el plugin requiere PHP 7.0+ (usa `??` pero no `?->`, named arguments, match, readonly properties).
- ✗ NO hardcodees URLs o paths — usa `HSEY_URL`, `HSEY_DIR`.
- ✗ NO añadas archivos nuevos sin require desde `src/_.php` o desde subcarpetas `src/*/_.php`.
