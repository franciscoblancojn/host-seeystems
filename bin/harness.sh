#!/bin/bash
#
# Harness de validación del proyecto Host Seeystems.
#
# Uso:
#   bin/harness.sh                 → Ejecuta todas las validaciones.
#   bin/harness.sh doc             → Lista y valida los docs en doc/.
#   bin/harness.sh doc <Nombre>    → Crea un doc en doc/ si no existe.
#
# Validaciones:
#   1. php -l en todo el plugin (excluye libs/).
#   2. Sintaxis PHP moderna prohibida (no ?->, match, readonly, arrow, named args).
#   3. JavaScript ES5 (sin arrow functions, let/const, template literals).
#   4. Prefijos CSS: hsey- (plugin) o fwue- (librería wordpress_utils).
#   5. Clases requeridas presentes (prefijo HSEY_).
#   6. require_once declarados en cargadores → archivo existe.
#   7. Referencias a documentación → archivo existe en doc/.
#   8. Plataforma de solo lectura: único POST = login, sin rutas que modifican datos.
#   9. Configuración de IA: git add/commit/merge/push denegados.
#
# NO ejecutar cambios de git. Solo validaciones de lectura + creación de docs.

set -u

PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
DOC_DIR="$PLUGIN_DIR/doc"
LIB_DIR="$PLUGIN_DIR/libs"
FAILURES=0
WARNINGS=0

fail() {
    printf '  [FAIL] %s\n' "$*"
    FAILURES=$((FAILURES + 1))
}

warn() {
    printf '  [WARN] %s\n' "$*"
    WARNINGS=$((WARNINGS + 1))
}

ok() {
    printf '  [ OK ] %s\n' "$*"
}

# ---------------------------------------------------------------
# Helpers de detección de sintaxis moderna (PHP 7.1+ / 8.x)
# ---------------------------------------------------------------
has_modern_php_syntax() {
    local file="$1"

    # Nullsafe operator ?-> (PHP 8.0)
    if grep -qE '\?->' "$file"; then
        echo 'operador nullsafe ?-> (PHP 8.0)'
        return 0
    fi
    # Named arguments (PHP 8.0)
    if grep -qE ':[[:space:]]*[a-zA-Z_][a-zA-Z0-9_]*\(\)' "$file"; then
        # Solo aplica cuando hay `name: value` style argument invocation; para
        # evitar falsos positivos se requiere paréntesis después de la clave.
        if grep -qE '[a-zA-Z_][a-zA-Z0-9_]*:[[:space:]]*\$?[a-zA-Z0-9_]+\(' "$file"; then
            echo 'posibles named arguments'
            return 0
        fi
    fi
    # match expression (PHP 8.0)
    if grep -qE '\bmatch[[:space:]]*\(' "$file"; then
        echo 'expresión match (PHP 8.0)'
        return 0
    fi
    # readonly properties (PHP 8.1)
    if grep -qE '\b(public|private|protected)[[:space:]]+readonly\b|\breadonly[[:space:]]+(class[[:space:]]+[A-Za-z_]|[?\\A-Za-z_]+[[:space:]]+\$)' "$file"; then
        echo 'propiedad readonly (PHP 8.1)'
        return 0
    fi
    # Arrow functions fn() (PHP 7.4)
    if grep -qE '\bfn[[:space:]]*\([^)]*\)[[:space:]]*=>' "$file"; then
        echo 'arrow function fn() (PHP 7.4)'
        return 0
    fi
    # Typed properties (PHP 7.4)
    if grep -qE '(public|private|protected)[[:space:]]+([a-zA-Z_][a-zA-Z0-9_]*|\\?[A-Z][a-zA-Z0-9_]*)+\$' "$file"; then
        echo 'propiedad tipada (PHP 7.4)'
        return 0
    fi
    # Union types (PHP 8.0)
    if grep -qE '\|([a-z_][a-z0-9_]*)[[:space:]]*\$\{(.*)' "$file"; then
        echo 'union type'
        return 0
    fi

    return 1
}

has_modern_js_syntax() {
    local file="$1"

    # Arrow functions => (ES6)
    if grep -qE '\=\>' "$file"; then
        echo 'arrow function o =>'
        return 0
    fi
    # Template literals con backtick
    if grep -q '`' "$file"; then
        echo 'template literal (backtick)'
        return 0
    fi
    # let/const (ES6)
    if grep -qE '\b(let|const)[[:space:]]+[a-zA-Z_$]' "$file"; then
        echo 'let/const (ES6)'
        return 0
    fi

    return 1
}

# ---------------------------------------------------------------
# Comando: crear un doc en doc/
# ---------------------------------------------------------------
create_doc() {
    local name="${1:-}"
    if [ -z "$name" ]; then
        printf 'Uso: bin/harness.sh doc <Nombre>\n'
        printf '     bin/harness.sh doc\n'
        return 1
    fi

    if [ "${name##*.}" != "md" ]; then
        name="$name.md"
    fi

    local file="$DOC_DIR/$name"
    if [ -f "$file" ]; then
        printf 'Ya existe: %s\n' "$file"
        return 0
    fi

    local title="${name%.md}"
    if ! echo "$title" | grep -q '^DOC-'; then
        title="DOC-$title"
    fi

    mkdir -p "$DOC_DIR"
    cat > "$file" <<EOF
# $title.md

> Documentación de Host Seeystems. Se sirve de la carpeta $DOC_DIR.

---

## Descripción

_Escribir aquí._

---

## Uso

_Escribir aquí._
EOF

    printf 'Creado: %s\n' "$file"
    return 0
}

# ---------------------------------------------------------------
# Comando: listar/validar docs en doc/
# ---------------------------------------------------------------
list_docs() {
    printf '\nDocumentación en %s:\n' "$DOC_DIR"

    if [ ! -d "$DOC_DIR" ]; then
        warn "falta la carpeta $DOC_DIR"
        return 1
    fi

    local docs
    docs="$(find "$DOC_DIR" -name '*.md' -type f | sort)"

    if [ -z "$docs" ]; then
        warn "no hay archivos .md en $DOC_DIR"
        return 1
    fi

    printf '%s\n' "$docs" | while read -r d; do
        ok "${d#$PLUGIN_DIR/}"
    done

    return 0
}

# ---------------------------------------------------------------
# Validación de referencias a documentación
# ---------------------------------------------------------------
validate_doc_refs() {
    printf '\n---- Validando referencias a documentación (%s) ----\n' "$DOC_DIR"

    if [ ! -d "$DOC_DIR" ]; then
        fail "no existe la carpeta de documentación: $DOC_DIR"
        return
    fi

    local refs
    refs="$(grep -rhoE 'DOC-[A-Za-z0-9_.-]+\.md' \
        "$PLUGIN_DIR/AGENTS.md" "$PLUGIN_DIR/CONTEXT.md" "$PLUGIN_DIR/HOOKS.md" \
        "$PLUGIN_DIR/README.md" "$PLUGIN_DIR/CHANGELOG.md" "$PLUGIN_DIR/.rules" "$PLUGIN_DIR/.opencode/agents" 2>/dev/null \
        | sort -u)"

    if [ -z "$refs" ]; then
        warn 'no se encontraron referencias a DOC-*.md'
        return
    fi

    while read -r ref; do
        if [ -f "$DOC_DIR/$ref" ]; then
            ok "$ref -> existe en doc/"
        else
            fail "$ref referenciado pero no existe en doc/$ref"
        fi
    done <<< "$refs"
}

# ---------------------------------------------------------------
# Validaciones estándar del proyecto
# ---------------------------------------------------------------
run_lint() {
    printf '\n---- php -l (excluyendo %s) ----\n' "$LIB_DIR"

    local files
    files="$(find "$PLUGIN_DIR/src" "$PLUGIN_DIR/index.php" "$PLUGIN_DIR/composer.json" -name '*.php' -type f 2>/dev/null | sort)"

    if [ -z "$files" ]; then
        fail 'no se encontraron archivos PHP'
        return
    fi

    while read -r f; do
        if command -v php >/dev/null 2>&1; then
            if ! php -l "$f" >/dev/null 2>&1; then
                php -l "$f"
                fail "$(basename "$f") no compila"
            fi
        else
            warn 'php no está disponible: se omite php -l'
            break
        fi
    done <<< "$files"
}

run_php_syntax() {
    printf '\n---- Sintaxis PHP moderna (plugin PHP 7.1+) ----\n'

    local files
    files="$(find "$PLUGIN_DIR/src" -name '*.php' -type f 2>/dev/null | grep -v "^$PLUGIN_DIR/libs/" | sort)"

    if [ -z "$files" ]; then
        warn 'no se encontraron archivos PHP'
        return
    fi

    while read -r f; do
        local why
        if why="$(has_modern_php_syntax "$f")"; then
            fail "$(basename "$f"): $why"
        fi
    done <<< "$files"
}

run_js_syntax() {
    printf '\n---- JavaScript ES5 ----\n'

    local files
    files="$(find "$PLUGIN_DIR/src" -name '*.js' -type f 2>/dev/null | grep -v "^$PLUGIN_DIR/libs/" | sort)"

    if [ "$files" != "" ]; then
        while read -r f; do
            local why
            if why="$(has_modern_js_syntax "$f")"; then
                fail "$(basename "$f"): $why"
            fi
        done <<< "$files"
    fi

    # JS embebido en archivos PHP de páginas/secciones
    files="$(find "$PLUGIN_DIR/src/page" -name '*.php' -type f 2>/dev/null | sort)"
    while read -r f; do
        local why
        if why="$(has_modern_js_syntax "$f")"; then
            warn "$(basename "$f"): posible JS moderno en PHP ($why)"
        fi
    done <<< "$files"
}

run_css_prefixes() {
    printf '\n---- Prefijos CSS (hsey- / fwue-) ----\n'

    local files
    files="$(find "$PLUGIN_DIR/src" -name '*.css' -type f 2>/dev/null | sort)"

    if [ -z "$files" ]; then
        ok 'sin archivos CSS (estilos inline en secciones)'
        return
    fi

    while read -r f; do
        local bad
        bad="$(grep -nE '\.(btn|input|modal|tooltip|collapse|table|form|panel|title|body|wrap|content|field|label|row|box|text|block|button|icon)([^a-z-]|$)' "$f" | grep -vE '\.hsey-|\.fwue-|/\*|@import' | head -5)"
        if [ -n "$bad" ]; then
            fail "$(basename "$f") tiene selectores sin prefijo hsey-/fwue-"
            printf '%s\n' "$bad" | while read -r b; do warn "    $b"; done
        fi
    done <<< "$files"
}

run_required_classes() {
    printf '\n---- Clases requeridas (prefijo HSEY_) ----\n'

    local required=(
        'HSEY_CLIENT'
        'HSEY_PARSER'
        'HSEY_API_PRODUCTS'
        'HSEY_USE_DATA_BASE'
        'HSEY_USE_DATA_CONFIG'
        'HSEY_USE_DATA_PRODUCTS'
    )

    local cls
    for cls in "${required[@]}"; do
        if grep -rq "class $cls" "$PLUGIN_DIR/src"; then
            ok "class $cls"
        else
            fail "falta la clase $cls"
        fi
    done
}

run_requires() {
    printf '\n---- require_once en cargadores (src/**/_.php) ----\n'

    local loaders
    loaders="$(find "$PLUGIN_DIR/src" -name '_.php' -type f 2>/dev/null | sort)"

    if [ -z "$loaders" ]; then
        warn 'no se encontraron cargadores _.php'
        return
    fi

    while read -r loader; do
        local targets
        targets="$(grep -oE "require_once[[:space:]]+HSEY_DIR[[:space:]]*.[[:space:]]*'[^']+'" "$loader" | sed -E "s/.*'([^']+)'.*/\1/")"

        if [ -z "$targets" ]; then
            warn "$(basename "$(dirname "$loader")"): sin require_once con HSEY_DIR"
            continue
        fi

        while read -r t; do
            if [ -f "$PLUGIN_DIR/$t" ]; then
                ok "${t#$PLUGIN_DIR/}"
            else
                fail "${loader#$PLUGIN_DIR/}: objetivo inexistente ${t#$PLUGIN_DIR/}"
            fi
        done <<< "$targets"
    done <<< "$loaders"
}

run_composer() {
    printf '\n---- composer validate ----\n'

    if [ -f "$PLUGIN_DIR/composer.json" ] && command -v composer >/dev/null 2>&1; then
        composer validate --no-check-publish "$PLUGIN_DIR/composer.json" >/dev/null 2>&1 && \
            ok 'composer.json válido' || \
            fail 'composer.json inválido'
    else
        warn 'composer no disponible: se omite composer validate'
    fi
}

run_readonly_platform() {
    printf '\n---- Plataforma de solo lectura ----\n'

    # El único POST permitido es el login en HSEY_CLIENT::login()
    local posts count
    posts="$(grep -rnE "'POST'|\"POST\"|wp_remote_post|CURLOPT_POST" "$PLUGIN_DIR/src" 2>/dev/null)"
    count="$(printf '%s' "$posts" | grep -c .)"
    if [ "$count" -eq 1 ] && printf '%s' "$posts" | grep -q 'src/api/client.php'; then
        ok 'único POST: login en src/api/client.php'
    else
        fail "se esperaba un único POST (login) y se encontraron $count"
        printf '%s\n' "$posts" | while read -r l; do [ -n "$l" ] && warn "    ${l#$PLUGIN_DIR/}"; done
    fi

    # Rutas de la plataforma que crean o modifican datos
    local bad
    bad="$(grep -rnE "['\"/](cart|orders|auth/logout|auth/cambiarPassword|auth/recover)(['\"/?]|$)" "$PLUGIN_DIR/src" 2>/dev/null)"
    if [ -n "$bad" ]; then
        fail 'el código referencia rutas de la plataforma que modifican datos'
        printf '%s\n' "$bad" | while read -r l; do warn "    ${l#$PLUGIN_DIR/}"; done
    else
        ok 'sin rutas cart / orders / logout / cambiarPassword / recover'
    fi
}

run_ai_config() {
    printf '\n---- Configuración de IA (git add/commit/merge/push denegados) ----\n'

    local f cmd
    for f in opencode.json .claude/settings.json; do
        if [ ! -f "$PLUGIN_DIR/$f" ]; then
            fail "falta $f"
            continue
        fi
        for cmd in add commit merge push; do
            if grep -qE "\"git $cmd[^\"]*\"[[:space:]]*:[[:space:]]*\"deny\"|\"Bash\(git $cmd" "$PLUGIN_DIR/$f"; then
                ok "$f: git $cmd denegado"
            else
                fail "$f: git $cmd no está denegado"
            fi
        done
    done

    if [ -x "$PLUGIN_DIR/.claude/hooks/block-git-write.sh" ]; then
        ok '.claude/hooks/block-git-write.sh ejecutable'
    else
        fail 'falta o no es ejecutable .claude/hooks/block-git-write.sh'
    fi
}

# ---------------------------------------------------------------
# Modo de ejecución
# ---------------------------------------------------------------
case "${1:-}" in
    doc)
        if [ $# -ge 2 ]; then
            create_doc "$2"
        else
            list_docs
        fi
        ;;
    *)
        run_lint
        run_php_syntax
        run_js_syntax
        run_css_prefixes
        run_required_classes
        run_requires
        run_composer
        run_readonly_platform
        run_ai_config
        validate_doc_refs
        ;;
esac

printf '\n-----------------------------\n'
printf '  FAILURES: %d | WARNINGS: %d\n' "$FAILURES" "$WARNINGS"
printf '%s\n' '-----------------------------'

if [ "$FAILURES" -gt 0 ]; then
    exit 1
fi
exit 0
