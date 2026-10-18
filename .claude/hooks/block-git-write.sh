#!/usr/bin/env bash
#
# PreToolUse (Bash): bloquea git add / commit / merge / push y los scripts npm
# que los ejecutan (push-v, push-tag), aunque vengan encadenados (&&, ;, |, subshell).
# Los commits los hace el desarrollador; la IA solo sugiere el mensaje.

CMD="$(jq -r '.tool_input.command // empty')"

# Solo detecta git en posición de comando: inicio, o tras ; & | ( ` $( , con sudo/env/command opcional.
if printf '%s' "$CMD" | grep -qE '(^|[;&|(`]|\$\()[[:space:]]*((sudo|command|env|xargs)[[:space:]]+)*git([[:space:]]+-[^[:space:]]+([[:space:]]+[^-[:space:]][^[:space:]]*)?)*[[:space:]]+(add|commit|merge|push)([^[:alnum:]_-]|$)'; then
    echo "Bloqueado por el proyecto: git add/commit/merge/push no está permitido para la IA. Sugiere el commit en texto (ver AGENTS.md §5)." >&2
    exit 2
fi

if printf '%s' "$CMD" | grep -qE 'npm[[:space:]]+run[[:space:]]+(push-v|push-tag)'; then
    echo "Bloqueado por el proyecto: npm run push-v / push-tag hace commit, tag y push. Solo lo ejecuta el desarrollador." >&2
    exit 2
fi

exit 0
