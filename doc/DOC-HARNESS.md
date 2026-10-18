# DOC-HARNESS.md

> Validaciones del proyecto y configuración de agentes de IA (opencode y Claude Code).

---

## `bin/harness.sh`

| Comando | Propósito |
|---|---|
| `npm run check` / `bash bin/harness.sh` | Ejecuta todas las validaciones (exit 1 si hay FAILURES) |
| `bash bin/harness.sh doc` | Lista los docs de `doc/` |
| `bash bin/harness.sh doc <Nombre>` | Crea `doc/DOC-<Nombre>.md` desde plantilla |

### Validaciones

1. `php -l` de `index.php` y `src/` (excluye `libs/`).
2. Sintaxis moderna de PHP prohibida (`?->`, `match`, `readonly`, `fn`, typed properties, named args).
3. JavaScript ES5 en `.js` y aviso de JS moderno embebido en `src/page`.
4. Prefijos CSS `hsey-` / `fwue-` en archivos `.css`.
5. Clases requeridas: `HSEY_CLIENT`, `HSEY_PARSER`, `HSEY_API_PRODUCTS`, `HSEY_USE_DATA_BASE`, `HSEY_USE_DATA_CONFIG`, `HSEY_USE_DATA_PRODUCTS`.
6. Cada `require_once HSEY_DIR . '...'` de los cargadores `src/**/_.php` apunta a un archivo existente.
7. `composer validate`.
8. **Plataforma de solo lectura**: un único POST en `src/` (login en `src/api/client.php`) y ninguna referencia a `cart`, `orders`, `auth/logout`, `auth/cambiarPassword`, `auth/recover`.
9. **Configuración de IA**: `opencode.json` y `.claude/settings.json` deniegan `git add/commit/merge/push` y el hook de bloqueo es ejecutable.
10. Toda referencia `DOC-*.md` en `AGENTS.md`, `CONTEXT.md`, `HOOKS.md`, `README.md`, `CHANGELOG.md`, `.rules/` y `.opencode/agents/` existe en `doc/`.

Al agregar una clase central, sumarla a `required` en `run_required_classes`.

---

## Git para IAs

Las IAs **no** pueden ejecutar `git add`, `git commit`, `git merge`, `git push`, `npm run push-v` ni `npm run push-tag`. Pueden usar `git status`, `git diff`, `git log`, `git show`. Los commits se sugieren en texto.

| Herramienta | Dónde se bloquea |
|---|---|
| opencode | `opencode.json` → `permission.bash` y permisos de los agentes `hsey-dev` / `hsey-reviewer` |
| Claude Code | `.claude/settings.json` → `permissions.deny` + hook `PreToolUse` `.claude/hooks/block-git-write.sh` |

El hook de Claude Code detecta `git` en posición de comando aunque venga encadenado (`cd x && git commit`, `git -C . push`, `$(git add .)`), y no bloquea texto como `echo "git add"`.

---

## Claude Code (`.claude/`)

| Archivo | Propósito |
|---|---|
| `CLAUDE.md` (raíz) | Importa `AGENTS.md` y `CONTEXT.md` |
| `.claude/settings.json` | Permisos (allow lectura git / harness, deny git escritura, `.env`, `libs/`) y hooks |
| `.claude/hooks/block-git-write.sh` | `PreToolUse` Bash: bloquea git add/commit/merge/push y push-v/push-tag (exit 2) |
| `.claude/hooks/php-lint.sh` | `PostToolUse` Edit/Write: `php -l` del archivo PHP editado; si falla devuelve el error |
| `.claude/skills/*` | Enlaces a `.rules/*` (misma fuente de skills que opencode) |
| `.claude/agents/hsey-reviewer.md` | Subagente revisor de solo lectura |

## opencode

| Archivo | Propósito |
|---|---|
| `opencode.json` | Instrucciones (`AGENTS.md`, `CONTEXT.md`), skills en `.rules`, permisos |
| `.opencode/agents/hsey-dev.md` | Agente de desarrollo |
| `.opencode/agents/hsey-reviewer.md` | Agente revisor (sin edición) |

## Skills (`.rules/`)

| Skill | Cuándo |
|---|---|
| `hsey-plugin` | Cualquier cambio en cliente, parser, sincronización, datos o páginas admin del plugin |
| `php-wordpress` | Cualquier código PHP de WordPress |
