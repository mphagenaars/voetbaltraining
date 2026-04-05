#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SCREENS_FILE="$ROOT_DIR/scripts/quality/must11_screens.txt"
BASELINE_DIR="$ROOT_DIR/scripts/quality/visual_baseline"
MANIFEST_FILE="$BASELINE_DIR/manifest.json"

DESKTOP_DIR="$BASELINE_DIR/desktop"
MOBILE_DIR="$BASELINE_DIR/mobile"
DESKTOP_WIDTH=1440
MOBILE_WIDTH=390

fail() {
    echo "MUST-11 FAIL: $1" >&2
    exit 1
}

pass() {
    echo "MUST-11 OK: $1"
}

[[ -f "$SCREENS_FILE" ]] || fail "screenconfig ontbreekt: $SCREENS_FILE"
[[ -f "$MANIFEST_FILE" ]] || fail "manifest ontbreekt: $MANIFEST_FILE"
[[ -d "$DESKTOP_DIR" ]] || fail "desktop-baselinemap ontbreekt: $DESKTOP_DIR"
[[ -d "$MOBILE_DIR" ]] || fail "mobile-baselinemap ontbreekt: $MOBILE_DIR"

if ! rg -q '"generated_at"\s*:' "$MANIFEST_FILE"; then
    fail "manifest mist generated_at"
fi

if ! rg -q '"name"\s*:\s*"desktop"' "$MANIFEST_FILE"; then
    fail "manifest mist desktop viewport"
fi

if ! rg -q '"name"\s*:\s*"mobile"' "$MANIFEST_FILE"; then
    fail "manifest mist mobile viewport"
fi

image_width() {
    local image_file="$1"
    php -r '
        $size = @getimagesize($argv[1]);
        if (!is_array($size) || !isset($size[0])) {
            fwrite(STDERR, "invalid image\n");
            exit(2);
        }
        echo (int)$size[0];
    ' "$image_file"
}

image_height() {
    local image_file="$1"
    php -r '
        $size = @getimagesize($argv[1]);
        if (!is_array($size) || !isset($size[1])) {
            fwrite(STDERR, "invalid image\n");
            exit(2);
        }
        echo (int)$size[1];
    ' "$image_file"
}

screen_count=0
while IFS='|' read -r slug route auth; do
    slug="${slug#"${slug%%[![:space:]]*}"}"
    slug="${slug%"${slug##*[![:space:]]}"}"

    if [[ -z "$slug" || "$slug" == \#* ]]; then
        continue
    fi

    desktop_png="$DESKTOP_DIR/$slug.png"
    mobile_png="$MOBILE_DIR/$slug.png"

    [[ -s "$desktop_png" ]] || fail "desktop baseline ontbreekt: $desktop_png"
    [[ -s "$mobile_png" ]] || fail "mobile baseline ontbreekt: $mobile_png"

    d_width="$(image_width "$desktop_png")"
    m_width="$(image_width "$mobile_png")"
    d_height="$(image_height "$desktop_png")"
    m_height="$(image_height "$mobile_png")"

    if (( d_width != DESKTOP_WIDTH )); then
        fail "desktop baseline heeft onjuiste breedte voor $slug: $d_width (verwacht $DESKTOP_WIDTH)"
    fi
    if (( m_width != MOBILE_WIDTH )); then
        fail "mobile baseline heeft onjuiste breedte voor $slug: $m_width (verwacht $MOBILE_WIDTH)"
    fi
    if (( d_height < 100 )); then
        fail "desktop baseline lijkt ongeldig voor $slug (hoogte $d_height)"
    fi
    if (( m_height < 100 )); then
        fail "mobile baseline lijkt ongeldig voor $slug (hoogte $m_height)"
    fi

    if ! rg -q "\"slug\"\s*:\s*\"$slug\"" "$MANIFEST_FILE"; then
        fail "manifest mist screen slug: $slug"
    fi

    screen_count=$((screen_count + 1))
done < "$SCREENS_FILE"

if (( screen_count == 0 )); then
    fail "geen schermen gevonden in $SCREENS_FILE"
fi

pass "baseline screenshots aanwezig voor $screen_count must-schermen op desktop + mobile"
echo "MUST-11 gate succesvol."
