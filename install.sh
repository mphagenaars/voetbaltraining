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
apt-get install -y -q git apache2 php php-sqlite3 php-pdo libapache2-mod-php unzip

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

# Eigenaarschap naar www-data (Apache user)
chown -R $WEB_USER:$WEB_USER "$PROJECT_DIR/data"
chown -R $WEB_USER:$WEB_USER "$PROJECT_DIR/public/uploads"

# Schrijfrechten voor groep
chmod -R 775 "$PROJECT_DIR/data"
chmod -R 775 "$PROJECT_DIR/public/uploads"

# Specifiek voor de database file als die al bestaat
if [ -f "$PROJECT_DIR/data/database.sqlite" ]; then
    chown $WEB_USER:$WEB_USER "$PROJECT_DIR/data/database.sqlite"
    chmod 664 "$PROJECT_DIR/data/database.sqlite"
fi

# Zorg dat de map zelf ook schrijfbaar is voor SQLite (voor lock files etc)
chown $WEB_USER:$WEB_USER "$PROJECT_DIR/data"

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

echo ""
echo "=========================================="
echo "✅  Installatie Voltooid!"
echo "=========================================="
echo "Je applicatie is nu bereikbaar."
echo ""
echo "⚠️  BELANGRIJK: Je verbinding is nog HTTP (onveilig)."
echo "    Zorg z.s.m. voor een SSL certificaat (bijv. met Certbot)."
echo ""
echo "👉  Maak direct een admin gebruiker aan:"
echo "    php scripts/create_admin.php"
echo "=========================================="
