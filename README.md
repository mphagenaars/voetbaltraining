
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
- `ffmpeg` en `yt-dlp` (voor AI video-availability checks en frame-extractie)
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

Voor een volledige serverinstallatie (Apache + PHP + SQLite + `ffmpeg` + `yt-dlp` + permissies):

```bash
chmod +x install.sh
sudo ./install.sh
```

`install.sh` moet als root draaien en:

- installeert alle server dependencies;
- downloadt `yt-dlp` naar `/usr/local/bin/yt-dlp`;
- zet rechten op `root:root` met execute-bit (`755`);
- valideert dat `yt-dlp` ook uitvoerbaar is als `www-data`;
- wijzigt Apache-configuratie en bestandsrechten.

Snelle verificatie na installatie:

```bash
ls -l /usr/local/bin/yt-dlp
sudo -u www-data /usr/local/bin/yt-dlp --version
ffmpeg -version
```

## Updaten

```bash
chmod +x update.sh
sudo ./update.sh
```

`update.sh` voert `git pull`, database-initialisatie/migraties en permissieherstel uit.

## Productie deploy via GitHub (handmatig)

Er is een aparte workflow toegevoegd: `.github/workflows/deploy-production.yml`.

Belangrijk:
- Deze deploy draait alleen handmatig via `workflow_dispatch` (niet op elke push).
- Er wordt een allowlist-artifact gedeployed (alleen runtime-bestanden), dus geen `scripts/check_*`, `.github/` of `design/`.
- De deploy-job gebruikt `environment: productie`, zodat je in GitHub verplichte goedkeuring kunt afdwingen.

Eenmalig instellen in GitHub:
1. `Settings -> Environments -> productie` aanmaken.
2. Bij `production` minimaal 1 `Required reviewer` instellen.
3. In `production` environment secrets toevoegen:
   - `PROD_SSH_HOST`
   - `PROD_SSH_PORT` (optioneel, standaard 22)
   - `PROD_SSH_USER`
   - `PROD_APP_DIR` (doelmap op server)
   - `PROD_SSH_PRIVATE_KEY`
   - `PROD_SSH_KNOWN_HOSTS` (aanbevolen; zo niet, dan gebruikt workflow `ssh-keyscan`)

Gebruik:
1. Ga naar `Actions -> Deploy Production`.
2. Klik `Run workflow`.
3. Laat `dry_run=true` voor alleen artifact-check.
4. Zet `dry_run=false` voor echte deploy na review/approval.

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

Dit project valt onder de [Apache License 2.0](LICENSE).
