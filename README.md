# Voetbaltraining

Een self-hosted webapplicatie voor voetbaltrainers om trainingen, oefeningen, wedstrijden en teamopstellingen te beheren. Deze applicatie is gebouwd met vanilla PHP (MVC architectuur) en maakt gebruik van een SQLite database.

## Kenmerken

- **Oefeningen Beheer**: Creëer en beheer voetbaloefeningen. Voeg beschrijvingen, tags en tekeningen toe.
- **Trainingsplanner**: Stel complete trainingen samen door oefeningen te combineren.
- **Wedstrijdbeheer**: Plan wedstrijden, houd scores bij, noteer gebeurtenissen (doelpunten, kaarten) en voeg evaluaties toe.
- **Team & Speler Beheer**: Beheer meerdere teams en spelerslijsten.
- **Opstellingen**: Maak tactische opstellingen en koppel deze aan wedstrijden.
- **Tekentool**: Geïntegreerde tekentool (gebaseerd op Konva.js) om oefeningen en tactieken visueel uit te werken.
- **Admin Dashboard**: Beheer gebruikers en teams (voor beheerders).
- **Accountbeheer**: Profielinstellingen en wachtwoordbeheer.

## Vereisten

- PHP 8.1 of hoger
- SQLite extensie voor PHP
- PDO extensie voor PHP

## Installatie & Gebruik

### Automatische Installatie (Ubuntu/Debian)

Gebruik het meegeleverde script om alle benodigdheden (Apache, PHP, SQLite) te installeren en de applicatie te configureren.

1. **Clone de repository**
   ```bash
   git clone https://github.com/mphagenaars/voetbaltraining.git
   cd voetbaltraining
   ```

2. **Start de installatie**
   ```bash
   chmod +x install.sh
   sudo ./install.sh
   ```

3. **Open de applicatie**
   De applicatie draait nu op poort 80 (Apache). Ga in je browser naar `http://localhost` (of het IP-adres van de server).

### Updates Installeren

Wanneer er nieuwe features beschikbaar zijn, kun je de applicatie eenvoudig bijwerken zonder dataverlies:

```bash
chmod +x update.sh
sudo ./update.sh
```
Dit script haalt de laatste code op, werkt de database bij (indien nodig) en herstelt de bestandsrechten.

### Handmatige Installatie (Development)

1. **Clone de repository**
   ```bash
   git clone https://github.com/mphagenaars/voetbaltraining.git
   cd voetbaltraining
   ```

2. **Initialiseer de database**
   Dit script maakt de `data/database.sqlite` aan en zet de tabellen op.
   ```bash
   php scripts/init_db.php
   ```

3. **Maak een admin account aan**
   Gebruik het hulpscript om een eerste gebruiker aan te maken.
   ```bash
   php scripts/create_admin.php
   ```

4. **Start de server**
   Je kunt de ingebouwde PHP server gebruiken voor ontwikkeling.
   ```bash
   php -S localhost:8000 -t public
   ```

5. **Open de applicatie**
   Ga in je browser naar `http://localhost:8000`.

## Projectstructuur

De applicatie volgt een strikte MVC (Model-View-Controller) structuur:

- `public/`: De web root. Bevat `index.php` (Front Controller & Router), CSS, JS en uploads.
- `src/`: Broncode van de applicatie.
    - `controllers/`: Afhandeling van verzoeken en business logic.
    - `models/`: Database interacties (CRUD).
    - `views/`: PHP templates voor de HTML weergave.
    - `Database.php`: Singleton database connectie wrapper.
    - `Session.php`: Helper voor veilig sessiebeheer en flash messages.
    - `Validator.php`: Helper voor input validatie.
    - `Csrf.php`: Beveiliging tegen Cross-Site Request Forgery.
    - `View.php`: Template rendering engine.
- `data/`: Bevat de SQLite database (`database.sqlite`).
- `scripts/`: Hulpscripts voor installatie en onderhoud.

## Technologie & Beveiliging

- **Backend**: PHP 8.1+ (Custom MVC, geen framework)
- **Database**: SQLite
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Libraries**: Konva.js (voor tekeningen)
- **Beveiliging**:
    - CSRF protectie op alle formulieren.
    - XSS preventie via automatische output escaping in Views.
    - Veilig sessiebeheer via `Session` class.
    - Wachtwoord hashing met `password_hash` (Bcrypt).
    - Prepared statements (PDO) tegen SQL injectie.

## Licentie

[MIT](LICENSE)
