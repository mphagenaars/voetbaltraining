#!/bin/bash

# Voetbaltraining Installer
# Dit script installeert de volledige applicatie inclusief server dependencies.
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
echo "🚀  Start Voetbaltraining Installatie"
echo "    Project map: $PROJECT_DIR"
echo "=========================================="

# 2. System Updates & Dependencies
echo ""
echo "📦  [1/7] Installeren van benodigde pakketten..."
apt-get update -q
apt-get install -y -q git apache2 php php-sqlite3 php-pdo php-xml php-mbstring libapache2-mod-php unzip sqlite3

# 3. Apache Configuratie
echo ""
echo "🔧  [2/7] Apache configureren..."

# Enable mod_rewrite
a2enmod rewrite

# Maak een nieuwe vhost config aan
CONF_FILE="/etc/apache2/sites-available/voetbaltraining.conf"
echo "    - Configureren van $CONF_FILE"

cat > $CONF_FILE <<EOF
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot $PROJECT_DIR/public

    <Directory $PROJECT_DIR/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

# Schakel default site uit en nieuwe site in
if [ -f /etc/apache2/sites-enabled/000-default.conf ]; then
    a2dissite 000-default.conf > /dev/null
fi
a2ensite voetbaltraining.conf > /dev/null

# 4. Mappen Structuur
echo ""
echo "📂  [3/7] Mappen aanmaken..."
mkdir -p data
mkdir -p public/uploads
echo "    - Map 'data' gecontroleerd."
echo "    - Map 'public/uploads' gecontroleerd."

# 5. Database Initialisatie
echo ""
echo "🗄️  [4/7] Database initialiseren..."
if [ -f "scripts/init_db.php" ]; then
    php scripts/init_db.php
else
    echo "❌  Fout: scripts/init_db.php niet gevonden!"
    exit 1
fi

# 6. Rechten Instellen
echo ""
echo "🔐  [5/7] Rechten instellen..."

# Huidige eigenaar van de bestanden
OWNER=$(stat -c '%U' "$PROJECT_DIR")

# 1. Zorg dat www-data door de mappenstructuur heen kan (nodig als project in /home/user staat)
# We geven 'others' execute rechten (+x) op alle bovenliggende mappen
CURRENT_PATH="$PROJECT_DIR"
while [ "$CURRENT_PATH" != "/" ]; do
    chmod o+x "$CURRENT_PATH" 2>/dev/null || true
    CURRENT_PATH=$(dirname "$CURRENT_PATH")
done
echo "    - Toegang tot bovenliggende mappen gecontroleerd."

# 2. Zet eigenaarschap en permissies voor het project
# We maken www-data eigenaar van de groep, en geven de groep lees/schrijf rechten waar nodig.
# Bestanden: Owner=$OWNER, Group=$WEB_USER
chown -R "$OWNER:$WEB_USER" "$PROJECT_DIR"

# Standaard permissies: Owner=RWX, Group=R-X (lezen/execute), Others=---
# Mappen
find "$PROJECT_DIR" -type d -exec chmod 750 {} +
# Bestanden
find "$PROJECT_DIR" -type f -exec chmod 640 {} +

# 3. Specifieke schrijfmappen (data en uploads)
# Deze moeten schrijfbaar zijn voor de groep (www-data)
chmod -R 770 "$PROJECT_DIR/data"
chmod -R 770 "$PROJECT_DIR/public/uploads"

# Database file specifiek (indien aanwezig)
if [ -f "$PROJECT_DIR/data/database.sqlite" ]; then
    chmod 660 "$PROJECT_DIR/data/database.sqlite"
fi

# Zorg dat de map zelf ook schrijfbaar is voor SQLite (voor lock files etc)
chmod 770 "$PROJECT_DIR/data"

echo "    - Bestandsrechten ingesteld (Owner: $OWNER, Group: $WEB_USER)."

# 7. Security Hardening
echo ""
echo "🛡️  [6/7] Security Hardening..."

# Verberg PHP errors in productie (display_errors = Off)
# We proberen dit in de php.ini van Apache te zetten
if [ -d "/etc/php" ]; then
    # Vind de php.ini files voor apache2 (bijv /etc/php/8.1/apache2/php.ini)
    find /etc/php -name php.ini -path "*/apache2/*" -exec sed -i 's/display_errors = On/display_errors = Off/g' {} +
    echo "    - PHP display_errors uitgezet (in php.ini)."
else
    echo "    ! Kon php.ini niet vinden. Controleer 'display_errors' handmatig."
fi

# Enable SSL module (voorbereiding voor HTTPS)
a2enmod ssl > /dev/null
echo "    - Apache SSL module ingeschakeld."

# 8. Service Herstarten
echo ""
echo "🔄  [7/7] Apache herstarten..."
systemctl restart apache2

# 9. Admin Gebruiker Aanmaken (Optioneel)
echo ""
echo "👤  [Optioneel] Admin gebruiker aanmaken"
read -p "    Wil je nu een admin account aanmaken? (j/n) " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Jj]$ ]]; then
    echo "    Starten van create_admin.php als www-data..."
    sudo -u www-data php scripts/create_admin.php
fi

echo ""
echo "=========================================="
echo "✅  Installatie Voltooid!"
echo "=========================================="
echo "Je applicatie is nu bereikbaar."
echo ""
echo "⚠️  BELANGRIJK: Je verbinding is nog HTTP (onveilig)."
echo "    Zorg z.s.m. voor een SSL certificaat (bijv. met Certbot)."
echo "=========================================="
