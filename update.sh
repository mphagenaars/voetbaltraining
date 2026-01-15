#!/bin/bash

# Voetbaltraining Updater
# Dit script update de applicatie code en database schema.
# Draai dit script als ROOT (sudo).

set -e

# 1. Check voor root rechten
if [ "$EUID" -ne 0 ]; then
  echo "❌  Dit script moet als root uitgevoerd worden (gebruik sudo)."
  exit 1
fi

PROJECT_DIR=$(pwd)
WEB_USER="www-data"

echo "=========================================="
echo "🚀  Start Voetbaltraining Update"
echo "    Project map: $PROJECT_DIR"
echo "=========================================="

# 2. Git Pull
echo ""
echo "⬇️  [1/3] Code ophalen (git pull)..."
git pull origin main

# 3. Database Migraties
echo ""
echo "🗄️  [2/3] Database bijwerken..."
if [ -f "scripts/init_db.php" ]; then
    php scripts/init_db.php
else
    echo "⚠️  Waarschuwing: scripts/init_db.php niet gevonden."
fi

# 3b. Schema fix voor exercises (nullable team_id)
if [ -f "scripts/fix_exercises_schema.php" ]; then
    php scripts/fix_exercises_schema.php
fi

# 4. Rechten Herstellen
echo ""
echo "🔐  [3/3] Rechten herstellen..."

# Huidige eigenaar van de bestanden
OWNER=$(stat -c '%U' "$PROJECT_DIR")

# Zorg dat www-data door de mappenstructuur heen kan
CURRENT_PATH="$PROJECT_DIR"
while [ "$CURRENT_PATH" != "/" ]; do
    chmod o+x "$CURRENT_PATH" 2>/dev/null || true
    CURRENT_PATH=$(dirname "$CURRENT_PATH")
done

# Zet eigenaarschap en permissies
chown -R "$OWNER:$WEB_USER" "$PROJECT_DIR"

# Standaard permissies
find "$PROJECT_DIR" -type d -exec chmod 755 {} +
find "$PROJECT_DIR" -type f -exec chmod 644 {} +

# Scripts uitvoerbaar maken
chmod +x "$PROJECT_DIR/update.sh"
chmod +x "$PROJECT_DIR/install.sh"

# Specifieke schrijfmappen
chmod -R 775 "$PROJECT_DIR/data"
chmod -R 775 "$PROJECT_DIR/public/uploads"

# Database file specifiek
if [ -f "$PROJECT_DIR/data/database.sqlite" ]; then
    chmod 664 "$PROJECT_DIR/data/database.sqlite"
fi
chmod 775 "$PROJECT_DIR/data"

echo "    - Rechten hersteld."

echo ""
echo "=========================================="
echo "✅  Update Voltooid!"
echo "=========================================="
