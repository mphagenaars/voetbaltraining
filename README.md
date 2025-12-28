# Voetbaltraining

Een self-hosted webapplicatie voor voetbaltrainers om trainingen, oefeningen en teamopstellingen te beheren. Deze applicatie is gebouwd met vanilla PHP en maakt gebruik van een SQLite database.

## Kenmerken

- **Oefeningen Beheer**: Creëer en beheer voetbaloefeningen. Voeg beschrijvingen en tekeningen toe.
- **Trainingsplanner**: Stel complete trainingen samen door oefeningen te combineren.
- **Team & Speler Beheer**: Beheer je teams en spelerslijsten.
- **Opstellingen**: Maak tactische opstellingen en deel deze met je team.
- **Tekentool**: Geïntegreerde tekentool (gebaseerd op Konva.js) om oefeningen en tactieken visueel uit te werken.

## Vereisten

- PHP 8.1 of hoger
- SQLite extensie voor PHP

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

- `public/`: De web root. Bevat `index.php` (entry point), CSS, JS en uploads.
- `src/`: Broncode van de applicatie.
    - `controllers/`: Afhandeling van verzoeken.
    - `models/`: Database interacties en logica.
    - `views/`: PHP templates voor de HTML weergave.
    - `Database.php`: Database connectie setup.
- `data/`: Bevat de SQLite database (`database.sqlite`).
- `scripts/`: Hulpscripts voor installatie en onderhoud.

## Technologie

- **Backend**: PHP (Custom MVC structuur, geen framework)
- **Database**: SQLite
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Libraries**: Konva.js (voor tekeningen)

## Licentie

[MIT](LICENSE)
