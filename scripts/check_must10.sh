#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FLOW_FILE="$ROOT_DIR/scripts/quality/must10_core_flows.txt"
TOKENS_CSS="$ROOT_DIR/public/css/tb-tokens.css"
BASE_CSS="$ROOT_DIR/public/css/tb-base.css"
PRIMITIVES_CSS="$ROOT_DIR/public/css/tb-primitives.css"

fail() {
    echo "MUST-10 FAIL: $1" >&2
    exit 1
}

pass() {
    echo "MUST-10 OK: $1"
}

[[ -f "$FLOW_FILE" ]] || fail "config ontbreekt: $FLOW_FILE"
[[ -f "$TOKENS_CSS" ]] || fail "tokensbestand ontbreekt: $TOKENS_CSS"
[[ -f "$BASE_CSS" ]] || fail "base stylesheet ontbreekt: $BASE_CSS"
[[ -f "$PRIMITIVES_CSS" ]] || fail "primitives stylesheet ontbreekt: $PRIMITIVES_CSS"

checked_count=0
while IFS='|' read -r relative_path label; do
    relative_path="${relative_path#"${relative_path%%[![:space:]]*}"}"
    relative_path="${relative_path%"${relative_path##*[![:space:]]}"}"
    if [[ -z "$relative_path" || "$relative_path" == \#* ]]; then
        continue
    fi

    label="${label:-$relative_path}"
    view_file="$ROOT_DIR/$relative_path"
    [[ -f "$view_file" ]] || fail "view ontbreekt ($label): $relative_path"

    # Check 1 + 3: keyboard semantics afdwingen.
    # Niet-semantische onclick op container-elementen maakt keyboardgebruik onvoorspelbaar.
    non_semantic_clicks="$(
        rg -n -P '<(?:div|span|article|li|p|section|header|footer|main)\b[^>]*\bonclick=' "$view_file" || true
    )"
    if [[ -n "$non_semantic_clicks" ]]; then
        echo "$non_semantic_clicks" >&2
        fail "niet-semantische onclick gevonden in kernflow ($label)"
    fi

    non_native_role_button="$(
        rg -n -P '<(?:div|span|article|li|p|section|header|footer|main)\b[^>]*\brole=(["'"'"'])button\1' "$view_file" || true
    )"
    if [[ -n "$non_native_role_button" ]]; then
        echo "$non_native_role_button" >&2
        fail "niet-semantische role=button gevonden in kernflow ($label)"
    fi

    tabindex_blockers="$(
        rg -n -P '<(?:a|button|input|select|textarea|summary)\b[^>]*\btabindex=(["'"'"'])-1\1' "$view_file" || true
    )"
    if [[ -n "$tabindex_blockers" ]]; then
        echo "$tabindex_blockers" >&2
        fail "tabindex=-1 op interactieve controls in kernflow ($label)"
    fi

    # Check 4: icon-only controls moeten aria-label/aria-labelledby hebben.
    missing_icon_labels="$(
        rg -n -P -U '(?s)<(?:a|button)\b(?=[^>]*class=(["'"'"'])[^>"'"'"']*\b(?:tb-icon-button|btn-icon(?:-round|-square)?|tb-fab|fab)\b[^>"'"'"']*\1)(?![^>]*\baria-label=)(?![^>]*\baria-labelledby=)[^>]*>' "$view_file" || true
    )"
    if [[ -n "$missing_icon_labels" ]]; then
        echo "$missing_icon_labels" >&2
        fail "icon-only control zonder aria-label in kernflow ($label)"
    fi

    checked_count=$((checked_count + 1))
done < "$FLOW_FILE"

if (( checked_count == 0 )); then
    fail "geen kernflows gevonden in $FLOW_FILE"
fi

pass "check 1 + 3 + 4: keyboard-semantiek en icon-labels groen op $checked_count kernflows"

# Check 2: focus zichtbaar.
if ! rg -q ':focus-visible' "$BASE_CSS"; then
    fail "globale :focus-visible regel ontbreekt in tb-base.css"
fi

if ! rg -q 'outline:\s*2px\s+solid' "$BASE_CSS"; then
    fail "globale focus outline ontbreekt in tb-base.css"
fi

required_focus_selectors=(
    '.tb-button:focus-visible'
    '.tb-icon-button:focus-visible'
    '.tb-fab:focus-visible'
    '.btn-icon:focus-visible'
    '.btn-icon-round:focus-visible'
)
for selector in "${required_focus_selectors[@]}"; do
    if ! rg -F -q "$selector" "$PRIMITIVES_CSS"; then
        fail "focusregel ontbreekt in primitives: $selector"
    fi
done

pass "check 2: focus-visible regels aanwezig (base + primitives)"

# Check 5: touch target minimaal 44x44 op primitives.
touch_target_px="$(rg -o -P --no-line-number -- '--tb-touch-target:\s*\K[0-9]+' "$TOKENS_CSS" | head -n 1 || true)"
button_height_px="$(rg -o -P --no-line-number -- '--tb-button-height-md:\s*\K[0-9]+' "$TOKENS_CSS" | head -n 1 || true)"

[[ -n "$touch_target_px" ]] || fail "--tb-touch-target niet gevonden in tb-tokens.css"
[[ -n "$button_height_px" ]] || fail "--tb-button-height-md niet gevonden in tb-tokens.css"

if (( touch_target_px < 44 )); then
    fail "--tb-touch-target is te klein: ${touch_target_px}px (< 44px)"
fi

if (( button_height_px < 44 )); then
    fail "--tb-button-height-md is te klein: ${button_height_px}px (< 44px)"
fi

if ! rg -q 'width:\s*var\(--tb-touch-target\)' "$PRIMITIVES_CSS"; then
    fail "icon button width gebruikt --tb-touch-target niet"
fi

if ! rg -q 'height:\s*var\(--tb-touch-target\)' "$PRIMITIVES_CSS"; then
    fail "icon button height gebruikt --tb-touch-target niet"
fi

pass "check 5: touch targets voldoen (tokens + primitives)"

# Check 6: contrast van kernkleuren (WCAG AA, ratio >= 4.5 voor normale tekst).
contrast_report="$(
    php -r '
$path = $argv[1];
$css = @file_get_contents($path);
if (!is_string($css) || $css === "") {
    fwrite(STDERR, "contrast: kon tokensbestand niet lezen\n");
    exit(2);
}

preg_match_all("/--([a-z0-9-]+):\\s*(#[0-9a-fA-F]{6})\\s*;/", $css, $matches, PREG_SET_ORDER);
$tokens = [];
foreach ($matches as $match) {
    $tokens[$match[1]] = strtolower($match[2]);
}

$pairs = [
    ["tb-color-text", "tb-color-bg", 4.5, "Body tekst op achtergrond"],
    ["tb-color-text", "tb-color-surface", 4.5, "Body tekst op kaart"],
    ["tb-color-text-muted", "tb-color-bg", 4.5, "Muted tekst op achtergrond"],
    ["tb-color-accent", "#ffffff", 4.5, "Primary knoptekst (wit) op accent"],
    ["tb-color-accent-hover", "#ffffff", 4.5, "Primary knoptekst (wit) op accent hover"],
    ["tb-color-danger", "#ffffff", 4.5, "Danger knoptekst (wit)"],
];

$toLum = static function (string $hex): float {
    $hex = ltrim($hex, "#");
    $r = hexdec(substr($hex, 0, 2)) / 255;
    $g = hexdec(substr($hex, 2, 2)) / 255;
    $b = hexdec(substr($hex, 4, 2)) / 255;
    $conv = static function (float $c): float {
        return $c <= 0.03928 ? ($c / 12.92) : pow(($c + 0.055) / 1.055, 2.4);
    };
    return 0.2126 * $conv($r) + 0.7152 * $conv($g) + 0.0722 * $conv($b);
};

$contrast = static function (string $a, string $b) use ($toLum): float {
    $l1 = $toLum($a);
    $l2 = $toLum($b);
    if ($l1 < $l2) {
        [$l1, $l2] = [$l2, $l1];
    }
    return ($l1 + 0.05) / ($l2 + 0.05);
};

$failed = false;
foreach ($pairs as [$fgKey, $bgKey, $minRatio, $label]) {
    $fg = str_starts_with($fgKey, "#") ? strtolower($fgKey) : ($tokens[$fgKey] ?? null);
    $bg = str_starts_with($bgKey, "#") ? strtolower($bgKey) : ($tokens[$bgKey] ?? null);
    if (!is_string($fg) || !is_string($bg)) {
        fwrite(STDERR, "contrast: token ontbreekt voor {$label} ({$fgKey} op {$bgKey})\n");
        exit(2);
    }

    $ratio = $contrast($fg, $bg);
    $ratioRounded = number_format($ratio, 2, ".", "");
    if ($ratio + 1e-9 < $minRatio) {
        $failed = true;
        fwrite(STDERR, "contrast FAIL: {$label} = {$ratioRounded} (< {$minRatio})\n");
    } else {
        echo "contrast OK: {$label} = {$ratioRounded}\n";
    }
}

exit($failed ? 1 : 0);
' "$TOKENS_CSS"
)" || {
    echo "$contrast_report" >&2
    fail "check 6: contrast niet geslaagd"
}

echo "$contrast_report"
pass "check 6: contrastratio voldoet voor kerntekst en primaire acties"

echo "MUST-10 gate succesvol."
