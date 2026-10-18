# Host Seeystems

**Version:** 1.0.0

Plugin de WordPress que se conecta a la plataforma B2B de `host.seeystems.com` (Swiss Sport B2B), inicia sesión con usuario y contraseña y trae los productos del catálogo.

## Uso

1. Activa el plugin.
2. **Host Seeystems → Configuracion**: URL de la plataforma, ruta del catálogo, usuario y contraseña. Usa **Guardar y probar conexión** para validar el login.
3. **Host Seeystems → Productos**: botón **Traer productos**. La pestaña **HTML** muestra lo que devuelve cualquier ruta con la sesión iniciada (sirve para ajustar el parser).

## Estructura

| Ruta | Contenido |
|---|---|
| `index.php` | Header, constantes `HSEY_*`, updater (`FWUUpdate`) y logs (`FWUSystemLog`) |
| `src/data/` | Capa de datos sobre `wp_options` (`HSEY_USE_DATA_*`). La contraseña se guarda cifrada (AES-256 con las salts de `wp-config.php`) |
| `src/api/client.php` | `HSEY_CLIENT`: login con `csrf_token` + cookie de sesión, reutiliza la sesión en un transient y vuelve a loguear si expira |
| `src/api/parser.php` | `HSEY_PARSER`: convierte JSON o HTML del catálogo en productos y detecta la paginación |
| `src/api/products.php` | `HSEY_API_PRODUCTS::sync()`: recorre el catálogo y guarda los productos en `HSEY_PRODUCTS` |
| `src/page/` | Páginas del admin |
| `libs/` | `franciscoblancojn/wordpress_utils` (Composer, no editar) |

## Scripts

| Comando | Propósito |
|---|---|
| `npm run install` | `composer install` y renombra `vendor` → `libs` |
| `npm run push-v -- patch` | Sube versión, crea tag y release para el updater |
