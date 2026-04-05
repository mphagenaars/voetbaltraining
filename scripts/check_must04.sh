#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BASELINE_FILE="$ROOT_DIR/scripts/quality/must04_baseline.env"

fail() {
    echo "MUST-04 FAIL: $1" >&2
    exit 1
}

pass() {
    echo "MUST-04 OK: $1"
}

if [[ ! -f "$BASELINE_FILE" ]]; then
    fail "Baseline file ontbreekt: $BASELINE_FILE"
fi

# shellcheck disable=SC1090
source "$BASELINE_FILE"

: "${INLINE_STYLE_ATTR_MAX:?INLINE_STYLE_ATTR_MAX ontbreekt in baseline}"
: "${INLINE_STYLE_BLOCK_MAX:?INLINE_STYLE_BLOCK_MAX ontbreekt in baseline}"

VIEW_DIRS=()
for dir in "$ROOT_DIR/src/views" "$ROOT_DIR/views"; do
    if [[ -d "$dir" ]]; then
        VIEW_DIRS+=("$dir")
    fi
done

if [[ ${#VIEW_DIRS[@]} -eq 0 ]]; then
    fail "Geen view-directories gevonden voor inline-style checks."
fi

count_occurrences() {
    local pattern="$1"
    shift
    local matches
    matches="$(rg -o "$pattern" "$@" -g '*.php' 2>/dev/null || true)"
    if [[ -z "$matches" ]]; then
        echo 0
    else
        printf '%s\n' "$matches" | wc -l | tr -d ' '
    fi
}

STYLE_ATTR_DQ_COUNT="$(count_occurrences 'style="' "${VIEW_DIRS[@]}")"
STYLE_ATTR_SQ_COUNT="$(count_occurrences "style='" "${VIEW_DIRS[@]}")"
INLINE_STYLE_ATTR_COUNT="$((STYLE_ATTR_DQ_COUNT + STYLE_ATTR_SQ_COUNT))"
INLINE_STYLE_BLOCK_COUNT="$(count_occurrences '<style\b' "${VIEW_DIRS[@]}")"

if (( INLINE_STYLE_ATTR_COUNT > INLINE_STYLE_ATTR_MAX )); then
    fail "inline style-attributes gestegen: $INLINE_STYLE_ATTR_COUNT > baseline $INLINE_STYLE_ATTR_MAX"
fi

if (( INLINE_STYLE_BLOCK_COUNT > INLINE_STYLE_BLOCK_MAX )); then
    fail "inline <style>-blokken gestegen: $INLINE_STYLE_BLOCK_COUNT > baseline $INLINE_STYLE_BLOCK_MAX"
fi

pass "inline style baseline bewaakt (attr=$INLINE_STYLE_ATTR_COUNT/$INLINE_STYLE_ATTR_MAX, style-tags=$INLINE_STYLE_BLOCK_COUNT/$INLINE_STYLE_BLOCK_MAX)"

DESIGN_REF_HITS="$(
    rg -n --hidden --glob '!design/**' '/design/' \
        "$ROOT_DIR/src" \
        "$ROOT_DIR/public" \
        "$ROOT_DIR/views" \
        "$ROOT_DIR/scripts" \
        "$ROOT_DIR/.github" 2>/dev/null || true
)"

if [[ -n "$DESIGN_REF_HITS" ]]; then
    echo "$DESIGN_REF_HITS" >&2
    fail "verwijzingen naar /design/* gevonden buiten /design."
fi

pass "geen runtime/build verwijzingen naar /design/* buiten de designmap"

HEADER_FILE="$ROOT_DIR/src/views/layout/header.php"
[[ -f "$HEADER_FILE" ]] || fail "Header file ontbreekt: $HEADER_FILE"

line_no_for() {
    local pattern="$1"
    rg -n "$pattern" "$HEADER_FILE" | head -n 1 | cut -d':' -f1
}

LINE_TOKENS="$(line_no_for 'tb-tokens\.css')"
LINE_FONTS="$(line_no_for 'tb-fonts\.css')"
LINE_BASE="$(line_no_for 'tb-base\.css')"
LINE_PRIMITIVES="$(line_no_for 'tb-primitives\.css')"
LINE_STYLE="$(line_no_for 'style\.css')"

if [[ -z "$LINE_TOKENS" || -z "$LINE_FONTS" || -z "$LINE_BASE" || -z "$LINE_PRIMITIVES" || -z "$LINE_STYLE" ]]; then
    fail "foundation stylesheet-load incompleet in header.php"
fi

if ! (( LINE_TOKENS < LINE_FONTS && LINE_FONTS < LINE_BASE && LINE_BASE < LINE_PRIMITIVES && LINE_PRIMITIVES < LINE_STYLE )); then
    fail "foundation stylesheet-volgorde onjuist in header.php"
fi

pass "foundation stylesheet-volgorde correct (tokens -> fonts -> base -> primitives -> style)"

REQUIRED_FILES=(
    "$ROOT_DIR/public/css/tb-tokens.css"
    "$ROOT_DIR/public/css/tb-fonts.css"
    "$ROOT_DIR/public/css/tb-base.css"
    "$ROOT_DIR/public/css/tb-primitives.css"
)

for required_file in "${REQUIRED_FILES[@]}"; do
    [[ -f "$required_file" ]] || fail "verplicht foundationbestand ontbreekt: $required_file"
done

for primitive in tb-button tb-icon-button tb-fab tb-chip tb-segmented; do
    if ! rg -q "\\.${primitive}" "$ROOT_DIR/public/css/tb-primitives.css"; then
        fail "primitive ontbreekt in tb-primitives.css: .$primitive"
    fi
done

pass "runtime foundationbestanden en verplichte primitives aanwezig"

echo "MUST-04 gate succesvol."
