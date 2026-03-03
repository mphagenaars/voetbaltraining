# Voetbaltraining

Self-hosted webapplicatie voor voetbaltrainers om oefenstof, trainingen, teams, spelers en wedstrijden te beheren.  
De applicatie gebruikt vanilla PHP (MVC) met SQLite.

## Functionaliteit

- Oefeningen aanmaken/bewerken met tekenveld (Konva.js), bronvermelding en coachinstructies.
- Oefeningen filteren op teamtaak, trainingsdoel en voetbalactie.
- Reacties en opmerkingen op oefeningen.
- Trainingen plannen per team en datum, met gekoppelde oefeningen, duur en doel per oefening.
- Wedstrijden plannen, opstelling opslaan, live wedstrijdmodus gebruiken (timer + events), score/evaluatie bijhouden en rapportages bekijken.
- Team- en spelersbeheer (incl. rugnummer/positie en afwezigen/keepers bij wedstrijden).
- Rollen per team (`coach`, `trainer`, `speler`) en teamselectie in sessiecontext.
- Accountbeheer (profiel, wachtwoord, zichtbaarheid van teams op dashboard).
- Admin-functionaliteit voor gebruikers, teams, clubs, seizoenen, oefenopties en systeemactiviteit.

## Vereisten

- PHP 8.1+ (CI draait op PHP 8.2)
- PHP extensies: `pdo`, `sqlite3`, `mbstring`  
- SQLite (bestand in `data/database.sqlite`)
- Voor productie: Apache met `mod_rewrite`

## Snelstart (Development)

1. Clone de repository:
   ```bash
   git clone https://github.com/mphagenaars/voetbaltraining.git
   cd voetbaltraining
   ```
2. Initialiseer de database:
   ```bash
   php scripts/init_db.php
   ```
3. Maak een admin aan:
   ```bash
   php scripts/create_admin.php
   ```
4. Start de lokale server:
   ```bash
   php -S localhost:8000 -t public
   ```
5. Open de app op `http://localhost:8000`.

## Installatie (Ubuntu/Debian)

Voor een volledige serverinstallatie (Apache + PHP + SQLite + permissies):

```bash
chmod +x install.sh
sudo ./install.sh
```

`install.sh` moet als root draaien en wijzigt Apache-configuratie en bestandsrechten.

## Updaten

```bash
chmod +x update.sh
sudo ./update.sh
```

`update.sh` voert `git pull`, database-initialisatie/migraties en permissieherstel uit.

## Handige scripts

- `php scripts/init_db.php` - database aanmaken en schema-migraties uitvoeren.
- `php scripts/create_admin.php` - eerste admin-gebruiker aanmaken.
- `php scripts/set_admin.php` - adminrechten geven aan bestaande gebruiker.
- `php scripts/regression_tests.php` - regressietests draaien.

## Tests

Draai lokaal:

```bash
php scripts/regression_tests.php
```

GitHub Actions draait dezelfde regressietests op pushes/PR's via `.github/workflows/regression-tests.yml`.

## Projectstructuur

- `public/` - webroot (`index.php`, CSS, JS, uploads, assets).
- `src/` - applicatiecode.
- `src/controllers/` - requestafhandeling.
- `src/models/` - datalaag.
- `src/views/` - templates.
- `scripts/` - CLI scripts voor setup, beheer en tests.
- `data/` - SQLite databasebestand.

## Beveiliging

- CSRF-tokens op formulieren.
- Output escaping met `e()` helper.
- PDO prepared statements.
- Wachtwoord hashing met `password_hash`.
- Remember-me tokens met selector/validator patroon (hashed validator in DB).

## Licentie

Er is momenteel geen `LICENSE`-bestand aanwezig in deze repository.
