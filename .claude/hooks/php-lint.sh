#!/usr/bin/env bash
#
# PostToolUse (Edit|Write): php -l sobre el archivo PHP editado (fuera de libs/).
# Si no compila, devuelve el error a la IA para que lo corrija.

FILE="$(jq -r '.tool_input.file_path // empty')"

case "$FILE" in
    *.php) ;;
    *) exit 0 ;;
esac
case "$FILE" in
    */libs/*) exit 0 ;;
esac
[ -f "$FILE" ] || exit 0
command -v php >/dev/null 2>&1 || exit 0

if ! OUT="$(php -l "$FILE" 2>&1)"; then
    echo "$OUT" >&2
    exit 2
fi
exit 0
