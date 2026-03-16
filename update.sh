#!/bin/bash

# Voetbaltraining Updater
# Dit script update de applicatie code en database schema.
# Draai dit script als ROOT (sudo).

set -euo pipefail

# 1. Check voor root rechten
if [ "$EUID" -ne 0 ]; then
  echo "❌  Dit script moet als root uitgevoerd worden (gebruik sudo)."
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$SCRIPT_DIR"
WEB_USER="www-data"
DB_FILE="$PROJECT_DIR/data/database.sqlite"
BACKUP_DIR="$PROJECT_DIR/data/backups"
TARGET_BRANCH="${1:-main}"

echo "=========================================="
echo "🚀  Start Voetbaltraining Update"
echo "    Project map: $PROJECT_DIR"
echo "=========================================="

# 2. Preflight checks
echo ""
echo "🔍  [1/5] Preflight checks..."

if ! command -v git >/dev/null 2>&1; then
    echo "❌  git is niet geïnstalleerd."
    exit 1
fi

if ! command -v php >/dev/null 2>&1; then
    echo "❌  php is niet geïnstalleerd."
    exit 1
fi

if ! id "$WEB_USER" >/dev/null 2>&1; then
    echo "❌  Web user '$WEB_USER' bestaat niet op dit systeem."
    exit 1
fi

mkdir -p "$PROJECT_DIR/data"
mkdir -p "$PROJECT_DIR/public/uploads"

OWNER=$(stat -c '%U' "$PROJECT_DIR")

if [ "$OWNER" != "root" ] && ! command -v sudo >/dev/null 2>&1; then
    echo "❌  'sudo' is nodig om git uit te voeren als eigenaar '$OWNER'."
    exit 1
fi

run_git() {
    if [ "$OWNER" = "root" ]; then
        git -C "$PROJECT_DIR" "$@"
    else
        sudo -u "$OWNER" -H git -C "$PROJECT_DIR" "$@"
    fi
}

if ! run_git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo "❌  $PROJECT_DIR is geen geldige git repository."
    exit 1
fi

# 3. Git Pull
echo ""
echo "⬇️  [2/5] Code ophalen (git pull)..."

if [ -n "$(run_git status --porcelain --untracked-files=no)" ]; then
    echo "❌  Er zijn lokale, getrackte wijzigingen. Commit/stash die eerst voor een veilige update."
    exit 1
fi

run_git fetch origin "$TARGET_BRANCH"
run_git pull --ff-only origin "$TARGET_BRANCH"

# 4. Database backup (voor migratie)
echo ""
echo "💾  [3/5] Database back-up maken..."
if [ -f "$DB_FILE" ]; then
    mkdir -p "$BACKUP_DIR"
    TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
    DB_BACKUP_FILE="$BACKUP_DIR/database_${TIMESTAMP}.sqlite"

    if command -v sqlite3 >/dev/null 2>&1; then
        sqlite3 "$DB_FILE" ".backup '$DB_BACKUP_FILE'"
    else
        cp -a "$DB_FILE" "$DB_BACKUP_FILE"
    fi
    echo "    - Backup gemaakt: $DB_BACKUP_FILE"
else
    echo "    - Geen bestaande database gevonden, backup overgeslagen."
fi

# 5. Database Migraties
echo ""
echo "🗄️  [4/5] Database bijwerken..."
if [ -f "$PROJECT_DIR/scripts/init_db.php" ]; then
    php "$PROJECT_DIR/scripts/init_db.php"
else
    echo "⚠️  Waarschuwing: scripts/init_db.php niet gevonden."
    exit 1
fi

# Zorg dat runtime config bestaat voor encryptie (nodig voor AI module)
CONFIG_FILE="$PROJECT_DIR/data/config.php"
if [ ! -f "$CONFIG_FILE" ]; then
    echo "    - data/config.php ontbreekt, aanmaken..."
    ENCRYPTION_KEY=$(php -r "if (!function_exists('sodium_crypto_secretbox_keygen')) { fwrite(STDERR, 'Sodium-extensie ontbreekt.\n'); exit(1); } echo 'base64:' . base64_encode(sodium_crypto_secretbox_keygen());")
    cat > "$CONFIG_FILE" <<EOF
<?php
return [
    'encryption_key' => '$ENCRYPTION_KEY',
];
EOF
fi

# 6. Rechten Herstellen
echo ""
echo "🔐  [5/5] Rechten herstellen..."

# Zorg dat www-data door de mappenstructuur heen kan
CURRENT_PATH="$PROJECT_DIR"
while [ "$CURRENT_PATH" != "/" ]; do
    chmod o+x "$CURRENT_PATH" 2>/dev/null || true
    CURRENT_PATH=$(dirname "$CURRENT_PATH")
done

# Zet eigenaarschap en permissies
chown -R "$OWNER:$WEB_USER" "$PROJECT_DIR"

# Standaard permissies
find "$PROJECT_DIR" -type d -exec chmod 750 {} +
find "$PROJECT_DIR" -type f -exec chmod 640 {} +

# Scripts uitvoerbaar maken
chmod +x "$PROJECT_DIR/update.sh"
chmod +x "$PROJECT_DIR/install.sh"
if [ -f "$PROJECT_DIR/git_update.sh" ]; then
    chmod +x "$PROJECT_DIR/git_update.sh"
fi

# Specifieke schrijfmappen
chmod -R 770 "$PROJECT_DIR/data"
chmod -R 770 "$PROJECT_DIR/public/uploads"

# Database file specifiek
if [ -f "$DB_FILE" ]; then
    chmod 660 "$DB_FILE"
fi
if [ -f "$PROJECT_DIR/data/config.php" ]; then
    chmod 640 "$PROJECT_DIR/data/config.php"
fi
chmod 770 "$PROJECT_DIR/data"

echo "    - Rechten hersteld."

echo ""
echo "=========================================="
echo "✅  Update Voltooid!"
echo "=========================================="
