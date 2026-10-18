# Changelog

> Todas las versiones del plugin Host Seeystems.
> Generado a partir de `git log`. Aún no hay tags: todo pertenece a la primera release `1.0.0`.

---

## [1.0.0] — 2026-10-17 (sin publicar)

### Harness y documentación (pendiente de commit)

- Harness de validación `bin/harness.sh` con `npm run check` y `npm run doc`: php -l, sintaxis PHP moderna, JS ES5, prefijos CSS, clases requeridas, cargadores, composer, **regla de solo lectura de la plataforma** y **configuración de IA**
- Fix (heredado del harness de Generate Page AI): los `fail` dentro de `printf | while` no sumaban FAILURES; falso positivo de `readonly` con el atributo HTML; `printf` con formato iniciado en `-`
- Configuración de IA que bloquea `git add`, `git commit`, `git merge`, `git push`, `npm run push-v` y `npm run push-tag`:
  - opencode: `opencode.json`, agentes `.opencode/agents/hsey-dev.md` y `hsey-reviewer.md`
  - Claude Code: `CLAUDE.md`, `.claude/settings.json` (permisos + hooks), `.claude/hooks/block-git-write.sh`, `.claude/hooks/php-lint.sh`, subagente `hsey-reviewer`
- Skills `.rules/hsey-plugin` y `.rules/php-wordpress` (enlazadas en `.claude/skills/`)
- Documentación: `CONTEXT.md`, `HOOKS.md`, `doc/DOC-PLATAFORMA.md`, `doc/DOC-LIBS.md`, `doc/DOC-HARNESS.md`
- `AGENTS.md` actualizado: regla principal de solo lectura, PHP 7.1+ (por `libs/`), uso obligatorio de `wordpress_utils`, flujo git y checklist de cierre
- `.gitignore`: `.claude/settings.local.json`

### `628047a` get products

- Parser reescrito: lee el JSON de `onclick="openProductModal({...})"` de cada `.product-card` (id, ref, nombre, marca, precios, specs, stock, imgs)
- Paginación siguiendo el enlace "Siguiente" de la plataforma (sin `#catalogTop`)
- `set_time_limit(300)` en la sincronización (~23 páginas, ~1.100 productos, ~30 s)
- Fix: `catalog_path` vacío ahora usa `/catalog` por defecto
- Tabla de productos con miniatura de la primera imagen (`imgs[0]`)
- `.gitignore`: `.env` y `node_modules/`

### `003b9b4` token

- Token de GitHub para el auto-updater (`FWUUpdate`)

### `9f6fbcc` init

- Estructura base sobre `generate-page-ai`: `libs/` (`franciscoblancojn/wordpress_utils` con autoloader `HSEY`), `package.json`, `scripts/bump-version.sh`
- Configuración de URL, ruta del catálogo, usuario y contraseña (cifrada AES-256 con las salts de WordPress)
- `HSEY_CLIENT`: login con `csrf_token` + cookie de sesión reutilizable (transient 20 min) y re-login automático
- Sincronización y listado de productos del catálogo
- Tab HTML para inspeccionar rutas autenticadas (GET)
- `FWUSystemLog` y `FWUUpdate`
