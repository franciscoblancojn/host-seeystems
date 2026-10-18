---
name: php-wordpress
description: >-
  Use when writing or editing PHP code for WordPress. Covers WordPress Coding
  Standards, security best practices, WordPress APIs, and PHP compatibility
  requirements for this plugin (PHP 7.1+).
---

# WordPress PHP — Estándares y Buenas Prácticas

---

## PHP Compatibility (≥7.1)

El plugin requiere PHP 7.1+ (`libs/` usa `: void`). En el código del plugin NO puedes usar:

### Prohibido
```php
// ✗ NO: Nullsafe operator (PHP 8.0)
$value = $object?->method();

// ✗ NO: Named arguments (PHP 8.0)
sendMail(to: "user@example.com");

// ✗ NO: Match expression (PHP 8.0)
match($x) { 1 => 'a', 2 => 'b' };

// ✗ NO: Readonly properties (PHP 8.1)
readonly string $name;

// ✗ NO: First-class callable (PHP 8.1)
$fn = strlen(...);

// ✗ NO: Array unpacking with string keys (PHP 8.1)
$array = [...$array1, ...$array2];

// ✗ NO: Enums (PHP 8.1)
enum Status { case Active; }

// ✗ NO: Fibers (PHP 8.1)
// ✗ NO: never return type (PHP 8.1)

// ✗ NO: Type declarations (PHP 5.6 soporta arrays y callable solamente)
function foo(int $x): string {}  // Nope
function foo(array $data) {}     // OK
function foo(callable $cb) {}    // OK
function foo(): array {}         // OK

// ✗ NO: Union types (PHP 8.0)
function foo(int|string $x) {}

// ✗ NO: Constructor property promotion (PHP 8.0)
function __construct(public int $id) {}

// ✗ NO: Attributes (PHP 8.0)
#[Attribute]

// ✗ NO: Spread operator in arrays (PHP 7.4)
$arr = [$a, ...$b];

// ✗ NO: Arrow functions (PHP 7.4)
$fn = fn($x) => $x * 2;

// ✗ NO: Typed properties (PHP 7.4)
public int $count;
```

### Permitido
```php
// ✓ OK: Type declarations for arrays
function getData(array $filter): array {}

// ✓ OK: callable type
function handle(callable $callback) {}

// ✓ OK: Ternary and null coalescing
$value = $x ?: 'default';
$value = $x ?? 'default';

// ✓ OK: Anonymous functions (PHP 5.3+)
$fn = function($x) { return $x * 2; };

// ✓ OK: Closures
// ✓ OK: Namespaces
// ✓ OK: Traits (PHP 5.4)
// ✓ OK: Short array syntax (PHP 5.4)
$arr = [1, 2, 3];
// ✓ OK: Finally (PHP 5.5)
// ✓ OK: foreach with list() (PHP 5.5)
// ✓ OK: Variadic functions (PHP 5.6)
function foo(...$args) {}
```

---

## WordPress Coding Standards

### Nombrado
- **Clases**: `HSEY_` prefix, `UPPER_SNAKE` (e.g., `HSEY_CLIENT`, `HSEY_PARSER`).
- **Funciones**: `hsey_` prefix, `snake_case`.
- **Constantes**: `HSEY_UPPER_SNAKE`.
- **Hooks (actions/filters)**: Prefijo `hsey_`.
- **Variables**: `$snake_case`.

### Hooks de WordPress
- Usa `add_action()` y `add_filter()`.
- No registres hooks en scope global — usa funciones o métodos estáticos.
- Priority por defecto: 10.
- Para filtros con múltiples args, declara el número de parámetros.

### Sanitización y Escapado
```php
// Input sanitization
$clean = sanitize_text_field($_POST['field']);
$id = intval($_POST['id']);
$url = esc_url_raw($_POST['url']);
$html = wp_kses_post($_POST['content']);
$slug = sanitize_title($_POST['slug']);

// Output escaping
echo esc_html($title);
echo esc_attr($value);
echo esc_url($url);
echo esc_textarea($content);
```

### Nonces
```php
// Generar
wp_nonce_field('hsey_action', 'hsey_nonce');

// Verificar admin
if (!wp_verify_nonce($_POST['hsey_nonce'], 'hsey_action')) {
    wp_die('Security check');
}

// Verificar AJAX
check_ajax_referer('hsey_nonce', 'nonce');
```

### Capabilities
```php
// Admin pages
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized');
}

// Post operations
if (!current_user_can('edit_posts')) {
    wp_die('Unauthorized');
}
```

---

## WordPress APIs

### Database
- Usa `$wpdb->prepare()` para queries SQL.
- Usa `get_option()`/`update_option()` para opciones (a través de `HSEY_USE_DATA_BASE` en este plugin).
- Usa `get_post_meta()`/`update_post_meta()` para meta de posts.

### HTTP API
- Usa `wp_remote_get()`/`wp_remote_post()` en vez de `curl_*` directo.
- `HSEY_CLIENT` usa `wp_remote_request()` con `redirection => 0` y maneja cookies a mano.

### AJAX
```php
add_action('wp_ajax_hsey_action', 'hsey_handle_ajax');
function hsey_handle_ajax() {
    check_ajax_referer('hsey_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    // ... process
    wp_send_json_success($data);
}
```

---

## Seguridad

1. **Input**: Nunca confíes en $_GET, $_POST, $_REQUEST, $_SERVER.
2. **Output**: Siempre escapa antes de imprimir en HTML.
3. **SQL**: Siempre usa `$wpdb->prepare()`.
4. **Nonces**: Siempre verifica en formularios y AJAX.
5. **Capabilities**: Siempre verifica permisos en admin.
6. **Files**: Validar tipo y tamaño antes de subir archivos.
7. **CSRF**: Nonces protegen contra Cross-Site Request Forgery.
