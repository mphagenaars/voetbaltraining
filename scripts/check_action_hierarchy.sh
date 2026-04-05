#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OVERVIEW_LIST_FILE="$ROOT_DIR/scripts/quality/action_hierarchy_overviews.txt"

fail() {
    echo "ACTION-HIERARCHY FAIL: $1" >&2
    exit 1
}

pass() {
    echo "ACTION-HIERARCHY OK: $1"
}

[[ -f "$OVERVIEW_LIST_FILE" ]] || fail "config ontbreekt: $OVERVIEW_LIST_FILE"

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

    # Enforce 1 dominant create entrypoint as FAB on overview pages.
    if ! rg -q 'class="[^"]*\btb-fab\b[^"]*"' "$view_file"; then
        fail "geen tb-fab gevonden in overview ($label): $relative_path"
    fi

    checked_count=$((checked_count + 1))
done < "$OVERVIEW_LIST_FILE"

if (( checked_count == 0 )); then
    fail "geen overview-entries gevonden in $OVERVIEW_LIST_FILE"
fi

ADMIN_USERS_VIEW="$ROOT_DIR/src/views/admin/users.php"
if [[ -f "$ADMIN_USERS_VIEW" ]]; then
    if rg -P -U -q '(?s)<button[^>]*class="[^"]*\btb-button\b[^"]*"[^>]*>\s*(?:<svg.*?</svg>\s*)*Nieuwe gebruiker\s*</button>' "$ADMIN_USERS_VIEW"; then
        fail "admin/users bevat een grote tb-button voor 'Nieuwe gebruiker'; gebruik een FAB."
    fi
fi

pass "FAB create-entrypoint afgedwongen op $checked_count overview-schermen"
