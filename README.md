# Host Seeystems

**Version:** 1.0.0

Plugin de WordPress que se conecta a la plataforma B2B **Swiss Sport B2B** (`https://host.seeystems.com/~swissstg`) en **modo solo lectura**: inicia sesión con usuario y contraseña y trae los productos del catálogo.

> El plugin nunca crea ni modifica datos en la plataforma: el único POST es el login. Ver `doc/DOC-PLATAFORMA.md`.

## Requisitos

- WordPress 5.0+
- PHP 7.1+ con `openssl` y `dom`

## Uso

1. Activa el plugin.
2. **Host Seeystems → Configuracion**: URL de la plataforma, ruta del catálogo (`/catalog`), usuario y contraseña. **Guardar y probar conexión** valida el login.
3. **Host Seeystems → Productos**: **Traer productos** recorre todo el catálogo (~1.100 productos, ~30 s) y los lista.
4. Tab **HTML**: muestra lo que devuelve cualquier ruta (GET) con la sesión iniciada.
5. Logs en **Ajustes → HSEY_LOG**.

## Estructura

| Ruta | Contenido |
|---|---|
| `index.php` | Header, constantes `HSEY_*`, updater (`FWUUpdate`) y logs (`FWUSystemLog`) |
| `src/data/` | Capa de datos sobre `wp_options` (`HSEY_USE_DATA_*`); contraseña cifrada |
| `src/api/client.php` | `HSEY_CLIENT`: login con `csrf_token` + cookie, sesión reutilizable |
| `src/api/parser.php` | `HSEY_PARSER`: JSON de cada `.product-card` + paginación |
| `src/api/products.php` | `HSEY_API_PRODUCTS::sync()`: recorre el catálogo y guarda en `HSEY_PRODUCTS` |
| `src/page/` | Páginas admin construidas con `wordpress_utils` |
| `libs/` | `franciscoblancojn/wordpress_utils` (Composer, no editar) |
| `bin/harness.sh` | Validaciones del proyecto |

## Scripts

| Comando | Propósito |
|---|---|
| `npm run install` | `composer install` y renombra `vendor` → `libs` |
| `npm run update` | Reinstala `libs/` desde cero |
| `npm run check` | Ejecuta el harness de validación |
| `npm run doc` / `npm run doc -- <Nombre>` | Lista docs / crea `doc/DOC-<Nombre>.md` |
| `npm run push-v -- patch\|minor\|major` | Sube versión, commit, tag y push (release para el updater). **Solo el desarrollador** |

## Documentación

| Archivo | Contenido |
|---|---|
| `AGENTS.md` | Reglas para IAs (obligatorias) |
| `CONTEXT.md` | Arquitectura, clases, opciones y flujos |
| `HOOKS.md` | Hooks, formularios y peticiones a la plataforma |
| `CHANGELOG.md` | Historial de cambios |
| `doc/DOC-PLATAFORMA.md` | Login, catálogo, campos de producto y regla de solo lectura |
| `doc/DOC-LIBS.md` | Uso de `wordpress_utils` (UI, updater, logs) |
| `doc/DOC-HARNESS.md` | Harness y configuración de agentes de IA |

## Desarrollo con IA

- **opencode**: `opencode.json`, agentes `hsey-dev` / `hsey-reviewer`, skills en `.rules/`.
- **Claude Code**: `CLAUDE.md`, `.claude/settings.json`, hooks en `.claude/hooks/`, skills en `.claude/skills/`, subagente `hsey-reviewer`.
- Las IAs **no** pueden ejecutar `git add`, `git commit`, `git merge` ni `git push`: solo sugieren el commit.

## Credenciales de prueba

`.env` (`SITE`, `USER`, `PASSWORD`) solo para explorar la plataforma en desarrollo. Está en `.gitignore`; no subirlo al servidor (la carpeta del plugin es pública).
