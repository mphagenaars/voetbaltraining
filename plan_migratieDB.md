# Plan migratie en herarchitectuur (herziene versie)

## 1. Doel

De app herbouwen op een duurzame, schaalbare stack, zodat deze klaar is voor een grotere test met meerdere gelijktijdige gebruikers en relatief eenvoudig opschaalbaar is als de app een succes wordt.

Doelstellingen:
- Schaalbare architectuur met Django + PostgreSQL + Celery.
- Toekomstbestendig datamodel met tenant-isolatie (club_id) en seizoenkoppeling.
- Async verwerking van zware taken (AI-calls, video processing, speech-to-text).
- Privacy by design conform AVG.

Context:
- De app wordt nu getest door 1 gebruiker met 1 team.
- Tijdsdruk is er niet; kwaliteit gaat boven snelheid.
- De bestaande PHP-app blijft gewoon draaien tijdens de transitie.
- Downtime van de nieuwe versie is geen probleem; de huidige versie staat live.
- Bestaande data migreren is wenselijk maar niet vereist — als het te veel werk is, beginnen we schoon.
- Eén ontwikkelaar (werkt met AI-assistenten), één beslisser.

## 2. Gekozen strategie

**Greenfield herbouw op Django + PostgreSQL.**

Aanpak:
- De app opnieuw bouwen in Django met PostgreSQL als database.
- Feature-voor-feature overbouwen en testen met testdata.
- De bestaande PHP/SQLite-versie blijft ongewijzigd live staan voor de huidige tester.
- Feature freeze op de oude versie gedurende de transitie.
- Wanneer de nieuwe versie feature-complete en getest is: beslissen of bestaande data wordt gemigreerd of dat we schoon beginnen.

Waarom Django + PostgreSQL:
- Django Admin bespaart ~1.500 regels handgebouwde admin-code (19 routes, AdminController + AiAdminController).
- Django ORM + automatische migrations vervangt de huidige PRAGMA-gebaseerde init_db.php.
- Celery + Redis geeft een job queue voor async AI-calls en video-processing (nu synchroon en blokkerend).
- yt-dlp is een native Python-library — veiliger en betrouwbaarder dan shell exec() calls.
- Python ML-ecosysteem beschikbaar als we later lokale modellen willen draaien (bijv. Whisper voor STT).
- Django en Python zijn uitstekend ondersteund door AI-assistenten (Claude Code, Codex).

Waarom niet de bestaande PHP-code porten:
- De huidige vanilla PHP stack mist framework-features (migrations, middleware, queues, DI, testing).
- Een port naar Laravel zou ook een near-complete rewrite zijn.
- Bij een rewrite is de taalverschildrempel laag met AI-assistenten.

## 3. Doelstack

| Laag | Technologie | Doel |
|---|---|---|
| Backend framework | Django 5.x | MVC, ORM, auth, admin, migrations |
| Database | PostgreSQL 16 | Data-opslag, JSONB voor tekeningen/tactieken, tenant-isolatie |
| Async task queue | Celery + Redis | AI-calls, video processing, STT, email |
| Video processing | yt-dlp (Python library) + ffmpeg | Frame-extractie, video-download |
| Speech-to-text | OpenRouter Whisper API (later optioneel lokaal Whisper) | Voice commands bij live wedstrijden |
| AI integratie | OpenRouter API via httpx | Chat, vision, exercise generation |
| Frontend | Vanilla JS + Konva.js (behouden) | Tekenboard, live match, AI chat |
| Development | Docker Compose | PostgreSQL + Redis + Django dev server |

### Wat er niet verandert
- **Frontend JavaScript**: De 148K regels vanilla JS (Konva.js, live match, AI chat, tactieken) blijven. De backend verandert; de frontend praat via dezelfde JSON-endpoints.
- **Externe services**: OpenRouter API, YouTube search, ffmpeg als binary.
- **Functionele scope**: Alle bestaande features worden 1-op-1 herbouwd. Geen nieuwe features tijdens de transitie.

## 4. Scope en afbakening

In scope:
- Django-project opzetten met PostgreSQL en Celery.
- Datamodel ontwerpen met tenant-isolatie en seizoenkoppeling.
- Alle bestaande features overbouwen in Django.
- Django Admin configureren als vervanging van het handgebouwde admin-panel.
- Frontend JS hergebruiken met minimale aanpassingen (endpoint URLs, CSRF-token mechanisme).
- Optioneel: data-migratie van SQLite naar PostgreSQL.
- AVG-maatregelen in ontwerp en autorisatie.

Buiten scope:
- Wijzigingen aan de bestaande PHP/SQLite-versie (feature freeze).
- Frontend framework-switch (vanilla JS blijft).
- Nieuwe features die niet in de huidige app zitten.
- Multi-region architectuur.

## 5. Datamodel

### Players en Users: gescheiden entiteiten

Players en users zijn fundamenteel verschillende groepen:
- **Users** = volwassenen (ouders, trainers, coaches, vrijwilligers). Eigen login, eigen account.
- **Players** = kinderen in de teams. Geen login, persoonsgegevens beheerd door trainers.

Verschillende privacy-regimes (minderjarigen), verschillende lifecycles, verschillende autorisatieregels. Ze blijven in gescheiden Django-models.

### Kernentiteiten

Bestaand (behouden als Django models, uitbreiden met club_id):
- `User` (Django auth user, uitgebreid met profiel: is_coach, is_trainer, ai_access)
- `Player` (spelers in teams — kinderen, geen login)
- `Team`, `Match`, `MatchEvent`, `MatchPlayer`, `MatchPeriodLineup`, `MatchSubstitution`
- `Exercise`, `ExerciseOption`, `ExerciseComment`, `ExerciseReaction`, `ExerciseTag`, `Tag`
- `Training`, `TrainingExercise`
- `MatchTactic`, `FormationTemplate`
- `ActivityLog`, `AppSetting`
- AI-models: `AiModel`, `AiModelPricing`, `AiChatSession`, `AiChatMessage`, `AiUsageEvent`, `AiQualityEvent`, `AiSourceCache`
- `MatchVoiceCommandLog`, `PlayerNameAlias`

Nieuw:
- `Club` (tenant-entiteit, basis voor isolatie)
- `Season` (uitbreiden: koppeling met clubs)
- `TeamSeason` (koppeling team + seizoen, vervangt losse season-kolom op teams)

### Ontwerpprincipes

- Tenant-isolatie op `club_id` in alle clubgebonden tabellen.
- `TeamSeason` als koppeling: een team bestaat per seizoen binnen een club.
- Gevoelige player-gegevens (geboortedatum, contactgegevens ouders) identificeren en beperkt toegankelijk maken.
- Django `JSONField` (PostgreSQL JSONB) voor drawing_data, positions, metadata_json, etc.
- Bestaande tabelstructuur zoveel mogelijk behouden; alleen uitbreiden waar nodig voor multi-tenancy.

## 6. Architectuurbeslissingen

### Django Admin vervangt handgebouwd admin-panel

Huidige PHP-app: AdminController (652 regels) + AiAdminController (883 regels) + 19 routes.

Django Admin aanpak:
- Registreer alle models in admin.py met juiste configuratie.
- Custom admin actions waar nodig (bijv. toggle AI-access, reset wachtwoord).
- Admin filters op club, seizoen, team.
- Inline editing voor gerelateerde objecten (bijv. match events binnen een match).
- Geschatte omvang: ~100-200 regels admin.py configuratie.

### Async verwerking met Celery

Huidige PHP-app: alle AI-calls, video-processing en STT zijn synchroon (30-180 sec blokkerend per request).

Django + Celery aanpak:
- AI chat completions → Celery task. Frontend pollt op status of gebruikt Server-Sent Events.
- Video frame extraction (yt-dlp + ffmpeg) → Celery task.
- Speech-to-text (voice commands) → Celery task.
- Email verzenden → Celery task.
- Redis als message broker.
- Flower of Django admin voor task monitoring.

### yt-dlp als Python library

Huidige PHP-app: yt-dlp als shell binary via exec(). Shell-injection risico, beperkte error handling.

Django aanpak:
- `import yt_dlp` als Python-library.
- Native Python error handling (exceptions i.p.v. exit codes).
- Toegang tot interne API's (progress callbacks, format selection).
- Geen shell-injection risico.

### CSRF-token aanpassing frontend

Huidige PHP-app: eigen CSRF-class, token via hidden form field en X-CSRF-Token header.

Django aanpak:
- Django's ingebouwde CSRF-middleware.
- Token beschikbaar via `csrftoken` cookie (standaard Django-patroon).
- Frontend JS aanpassen: lees token uit cookie i.p.v. meta-tag/hidden field.
- Minimale wijziging in de fetch()-calls.

## 7. Roadmap

### Fase 1 — Project setup en fundament

Acties:
1. Django-project aanmaken met standaard structuur.
2. Docker Compose opzetten: PostgreSQL + Redis + Django dev server.
3. Django models definiëren voor kernentiteiten (Club, User, Team, Player, Season, TeamSeason).
4. Django migrations draaien — schema staat.
5. Django Admin configureren voor kernentiteiten.
6. Basisauth: login, logout, registratie, remember-me via Django auth.
7. CSRF-middleware en security headers configureren.
8. Basis URL-routing opzetten.
9. Template-structuur (of JSON-responses) voor views.

Deliverables:
- Draaiend Django-project met PostgreSQL in Docker.
- Kernmodels met werkende admin.
- Login/logout werkt.

### Fase 2 — Feature-voor-feature overbouwen

Volgorde (kern eerst, zwaarste integraties laatst):

| # | Feature | Huidige omvang (PHP) | Django-aanpak | Opmerkingen |
|---|---|---|---|---|
| 1 | Clubs en teams + team_seasons | TeamController + models | Django models + views | Nieuw: Club en TeamSeason model |
| 2 | Spelers | PlayerController + model | Django models + views | CRUD, koppeling aan team |
| 3 | Oefeningen + tags | ExerciseController (469r) + models | Django models + views + admin | Inclusief commentaar, reacties |
| 4 | Trainingen | TrainingController (195r) + model | Django models + views | CRUD, oefeningen koppelen |
| 5 | Wedstrijden (basis) | GameController (deel, ~800r) | Django models + views | CRUD, lineup, events, periodes |
| 6 | Tactieken + formaties | TacticsController (180r) + models | Django models + views | Konva.js JSON opslaan/laden |
| 7 | Account/profiel | AccountController (146r) | Django views | Profiel, wachtwoord, teamzichtbaarheid |
| 8 | Admin-panel | AdminController (652r) + AiAdminController (883r) | **Django Admin** (~150r config) | Grootste besparing |
| 9 | Live match | GameController (deel, ~960r) | Django views + **Celery** voor STT | Voice commands, substituties, timer |
| 10 | AI-module | AiController (4.311r) + 10+ services | Django views + **Celery** tasks | Chat, vision, usage, pricing |

Per feature:
- Django models + migrations.
- Views (server-rendered templates of JSON-endpoints, afhankelijk van feature).
- Frontend JS hergebruiken — alleen endpoint URLs en CSRF-token aanpassen.
- Tenant-isolatie (club_id) controleren.
- Handmatig testen met testdata.

### Fase 3 — Async integratie (Celery)

Acties:
1. Celery + Redis configureren in het Django-project.
2. AI chat completions omzetten naar Celery tasks.
3. Video frame extraction (yt-dlp) omzetten naar Celery tasks.
4. Speech-to-text omzetten naar Celery task.
5. Frontend aanpassen voor async flow: task starten → polling op status → resultaat ophalen.

Deliverable:
- Alle zware operaties draaien async via Celery.
- Frontend toont "bezig..." status en haalt resultaat op als het klaar is.

### Fase 4 — Validatie en besluit data-migratie

Acties:
1. Volledige test van alle features op de Django-versie.
2. Beoordelen of data-migratie de moeite waard is of dat schoon beginnen beter is.
3. Als migratie gewenst: Python ETL-script schrijven (SQLite → PostgreSQL).
4. Als schoon beginnen: testdata opruimen, productie-ready maken.

Beslismoment:
- Migreren of schoon beginnen? Besluit op basis van hoeveel data er is en hoeveel werk de migratie kost.

### Fase 5 — Livegang

Acties:
1. Nieuwe Django-versie deployen.
2. Als data gemigreerd: validatie draaien.
3. Tester laten werken op nieuwe versie.
4. Oude PHP/SQLite-versie als backup bewaren (niet verwijderen).

Rollback:
- Als de nieuwe versie niet werkt: tester terug naar de oude PHP/SQLite-versie. Geen tijdsdruk.

## 8. Data-migratie (optioneel, besluit in Fase 4)

Als we besluiten te migreren:

### Aanpak
1. SQLite-database kopiëren als snapshot.
2. Pre-migratie kwaliteitscheck:
   - Orphan records detecteren (FK-inconsistenties door historisch ontbreken van PRAGMA foreign_keys).
   - NULL-waarden inventariseren in velden die in het nieuwe model verplicht zijn.
   - JSON-kolommen valideren (parseerbaar?).
   - Encoding controleren (niet-UTF-8 content?).
3. Python ETL-script schrijven:
   - Data uit SQLite lezen (Python sqlite3 module).
   - Transformeren naar Django models (club_id toevoegen, TeamSeason aanmaken).
   - Laden via Django ORM (of raw SQL voor bulk performance).
   - Script moet idempotent zijn (meerdere keren draaibaar zonder duplicaten).
4. Django management command: `python manage.py migrate_from_sqlite <path-to-sqlite>`.
5. Validatie:
   - `count(*)` per tabel: 0 verschil of expliciet verklaard.
   - Wachtwoord-hashes: bcrypt hashes zijn framework-agnostisch, moeten 1-op-1 werken.
   - AI usage totalen (SUM tokens, costs): max 0.01% afwijking.
   - Wedstrijdscores en events: 100% identiek.
   - Alle foreign keys valideren, 0 orphans.

### Als migratie te complex is
- Schoon beginnen op Django + PostgreSQL.
- Oude SQLite-database archiveren.
- Tester begint opnieuw met verse data.

## 9. Privacy en security

Verplicht in implementatie:
- Tenant-isolatie op `club_id` in alle clubgebonden models en querysets.
- Django's ingebouwde auth + permissions systeem gebruiken (groups, permissions).
- Overweeg PostgreSQL Row-Level Security (RLS) als extra beveiligingslaag.
- Gevoelige player-gegevens (geboortedatum, contactgegevens ouders) identificeren en beperkt toegankelijk maken.
- Autorisatie: admin, coach, trainer — op club- en teamniveau.
- Django CSRF-middleware (ingebouwd).
- Django security middleware (clickjacking, XSS, HSTS — ingebouwd).
- DPIA overwegen vanwege minderjarigen.

Later (bij groei):
- Audit logging op gevoelige persoonsgegevens.
- Formele bewaartermijnen en AVG-processen (inzage, correctie, verwijdering).
- TLS en versleutelde backups.
- Database-niveau rolscheiding (readonly vs. readwrite).

## 10. Kwaliteitscriteria voor livegang

De nieuwe versie is klaar voor livegang als:
1. Alle features uit de huidige app werken in de Django-versie.
2. Alle routes zijn getest zonder errors.
3. Tenant-isolatie (club_id) werkt: gebruiker A ziet geen data van club B.
4. Auth werkt: login, logout, remember-me, rolgebaseerde toegang.
5. Async taken werken: AI-chat, video-processing en voice commands via Celery.
6. Django Admin werkt voor alle beheer-taken.
7. Frontend JS werkt ongewijzigd (of met minimale endpoint-aanpassingen).
8. Als data gemigreerd: validatie op counts, checksums en steekproeven geslaagd.

## 11. Uitvoeringstracker

| ID | Fase | Taak | Status |
|---|---|---|---|
| T1 | 1 | Django-project aanmaken + structuur | Open |
| T2 | 1 | Docker Compose: PostgreSQL + Redis | Open |
| T3 | 1 | Kernmodels definiëren (Club, User, Team, Player, Season, TeamSeason) | Open |
| T4 | 1 | Django Admin voor kernmodels | Open |
| T5 | 1 | Auth: login, logout, registratie | Open |
| T6 | 1 | URL-routing + CSRF + security middleware | Open |
| T7 | 2 | Clubs, teams, team_seasons overbouwen | Open |
| T8 | 2 | Spelers overbouwen | Open |
| T9 | 2 | Oefeningen + tags overbouwen | Open |
| T10 | 2 | Trainingen overbouwen | Open |
| T11 | 2 | Wedstrijden (basis) overbouwen | Open |
| T12 | 2 | Tactieken + formaties overbouwen | Open |
| T13 | 2 | Account/profiel overbouwen | Open |
| T14 | 2 | Admin-panel via Django Admin | Open |
| T15 | 2 | Live match overbouwen | Open |
| T16 | 2 | AI-module overbouwen | Open |
| T17 | 3 | Celery + Redis configureren | Open |
| T18 | 3 | AI-calls → Celery tasks | Open |
| T19 | 3 | Video processing → Celery tasks | Open |
| T20 | 3 | STT/voice commands → Celery tasks | Open |
| T21 | 3 | Frontend async flow (polling/SSE) | Open |
| T22 | 4 | Volledige feature-test | Open |
| T23 | 4 | Besluit: data migreren of schoon beginnen | Open |
| T24 | 4 | ETL-script + validatie (indien migratie) | Open |
| T25 | 5 | Livegang nieuwe versie | Open |

Statuswaarden: `Open` · `In uitvoering` · `Geblokkeerd` · `Klaar`
