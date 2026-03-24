# Implementatieplan STT voor gerichte wissels in Live View

## 1. Doel en uitgangspunten

Dit plan beschrijft de implementatie van spraakgestuurde, **gerichte wissels** in de bestaande live-wedstrijdweergave.

Primaire doelen:
- Tijdens de wedstrijd sneller wissels registreren met minimale interactie.
- Betrouwbare registratie van wie erin komt, wie eruit gaat, op welk moment, in welke periode en op welk positie-slot.
- Nauwkeurige berekening van speeltijd per speler en speeltijd per positie-slot.

Belangrijke keuzes:
- We bouwen voort op de bestaande live view (timer/periodes blijven leidend).
- We ondersteunen alleen gerichte wissels (`IN voor UIT`), geen "roteer reserves".
- Namen van spelers zijn bekend in de database en worden actief gebruikt voor herkenning en validatie.
- Spraakinvoer krijgt altijd een snelle handmatige fallback en `undo`.

## 2. Huidige situatie (samenvatting)

Wat al bestaat:
- Live timer + periodes (`start/stop` via whistle events).
- Live events toevoegen (goal, kaart, wissel, notitie).
- Team- en spelersdata in database.

Wat nu ontbreekt voor betrouwbare analyse:
- Wissels zijn nu grotendeels tekstueel, niet volledig gestructureerd.
- Geen robuuste, doorlopende "actieve opstelling" per periode.
- Geen automatische stint-/minutenberekening per speler en positie.
- Geen STT-keten met confidence, correctie en audit.

## 3. Scope MVP

In scope:
- Gerichte wissels via spraakcommando (`X voor Y`).
- Meerdere wissels in één opname ondersteunen (`A voor B, C voor D`).
- Gestructureerde opslag van wisseldata.
- Periode-opstelling vastleggen voor periode 1..4.
- Minuten- en positieberekening op basis van opstelling + wissels + periodestops.
- Handmatige fallback + undo + bevestiging bij twijfel.

Niet in scope (MVP):
- Vrije spraaknotities met open intent-detectie.
- Complexe wedstrijdanalyse/rapportage buiten minuten/positie.
- Automatische bulk-rotatie zonder expliciete in/uit-paren.

## 4. Architectuurkeuze STT

## 4.1 Gekozen route

Primair: OpenRouter-gebaseerde audioflow met audio-input op chat completions.

Waarom:
- Sluit direct aan op bestaande infrastructuur:
  - sleutelbeheer,
  - access checks,
  - usage/budget/rate-limits,
  - foutafhandeling.
- Minder nieuwe infrastructuur nodig.
- Sneller live te brengen.

## 4.2 Fallback-strategie

We ontwerpen STT achter een service-interface (`SttServiceInterface`).

- Implementatie A (standaard): `OpenRouterSttService`.
- Implementatie B (optioneel later): `OpenAiTranscriptionService` (dedicated transcriptions endpoint).

Zo kunnen we later wisselen zonder wijzigingen in domeinlogica.

## 4.3 Modellering

Aanbevolen start:
- Primair STT-model: `openai/gpt-audio-mini` (via OpenRouter).
- Optionele fallback bij lage confidence of herhaalde mismatch: `openai/gpt-audio` of ander nauwkeuriger audiomodel.

Belangrijk:
- Modelkeuze wordt configurabel gemaakt (admin/modeltabel), niet hardcoded.

## 5. Datamodel

## 5.1 Nieuwe tabellen

### `match_period_lineups`
Doel: vastleggen wie op welk slot start in een periode.

Voorstel kolommen:
- `id` INTEGER PK
- `match_id` INTEGER NOT NULL
- `period` INTEGER NOT NULL
- `slot_code` TEXT NOT NULL  (bijv. `GK`, `L`, `C`, `R`, `SPARE_SLOT_X` afhankelijk format)
- `player_id` INTEGER NOT NULL
- `created_by` INTEGER NULL
- `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP

Constraints/indexes:
- UNIQUE (`match_id`, `period`, `slot_code`)
- INDEX (`match_id`, `period`)

### `match_substitutions`
Doel: volledig gestructureerde wisselregistratie.

Voorstel kolommen:
- `id` INTEGER PK
- `match_id` INTEGER NOT NULL
- `period` INTEGER NOT NULL
- `clock_seconds` INTEGER NOT NULL  (officiële wedstrijdtijd in seconden sinds aftrap)
- `minute_display` INTEGER NOT NULL  (voor timeline UI)
- `slot_code` TEXT NOT NULL
- `player_out_id` INTEGER NOT NULL
- `player_in_id` INTEGER NOT NULL
- `source` TEXT NOT NULL DEFAULT `manual`  (`voice`/`manual`)
- `raw_transcript` TEXT NULL
- `transcript_confidence` REAL NULL
- `stt_model_id` TEXT NULL
- `created_by` INTEGER NULL
- `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP

Constraints/indexes:
- CHECK `player_out_id <> player_in_id`
- INDEX (`match_id`, `clock_seconds`, `created_at`)
- INDEX (`match_id`, `period`)

### `player_name_aliases`
Doel: team-specifieke naamsynoniemen voor betere herkenning.

Voorstel kolommen:
- `id` INTEGER PK
- `team_id` INTEGER NOT NULL
- `player_id` INTEGER NOT NULL
- `alias` TEXT NOT NULL
- `normalized_alias` TEXT NOT NULL
- `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP

Constraints/indexes:
- UNIQUE (`team_id`, `player_id`, `normalized_alias`)
- INDEX (`team_id`, `normalized_alias`)

### `match_voice_command_logs`
Doel: audit + kwaliteitsverbetering.

Voorstel kolommen:
- `id` INTEGER PK
- `match_id` INTEGER NOT NULL
- `user_id` INTEGER NOT NULL
- `period` INTEGER NULL
- `clock_seconds` INTEGER NULL
- `audio_duration_ms` INTEGER NULL
- `stt_model_id` TEXT NULL
- `raw_transcript` TEXT NULL
- `normalized_transcript` TEXT NULL
- `parsed_json` TEXT NULL
- `status` TEXT NOT NULL (`accepted`, `needs_confirmation`, `rejected`, `error`)
- `error_code` TEXT NULL
- `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP

Indexes:
- INDEX (`match_id`, `created_at`)
- INDEX (`status`, `created_at`)

## 5.2 Bestaande tabelgebruik

`match_events` blijft bestaan voor timeline-compatibiliteit.
Bij elke geslaagde wissel kan optioneel ook een `sub` event toegevoegd worden als weergave-event.

## 6. API-ontwerp (server endpoints)

## 6.1 Live opstelling en wissels

### `POST /matches/live/save-period-lineup`
Slaat periode-startopstelling op.

Request (JSON):
- `match_id`
- `period`
- `slots`: array `{ slot_code, player_id }`

Response:
- `success`
- `period`
- `lineup`

### `POST /matches/live/substitute`
Voert handmatige gerichte wissel uit.

Request:
- `match_id`
- `player_out_id`
- `player_in_id`
- `slot_code` (optioneel; server kan afleiden via actieve opstelling)
- `source` = `manual`

Response:
- `success`
- `substitution`
- `active_lineup`
- `events`

### `POST /matches/live/substitute/undo`
Draait laatste wissel terug (alleen meest recente nog niet "vergrendelde" actie).

Response:
- `success`
- `undone_substitution_id`
- `active_lineup`

## 6.2 Voice

### `POST /matches/live/voice-command`
Transcribeert en parseert audio, zonder direct commit bij twijfel.

Request (multipart):
- `match_id`
- `audio_file` (webm/wav/m4a)
- `client_started_at_ms` (optioneel)
- `client_duration_ms` (optioneel)

Response:
- `success`
- `transcript`
- `commands`: array met voorgestelde wissels
- `confidence`
- `requires_confirmation` boolean
- `reason` (bij twijfel)

### `POST /matches/live/voice-command/confirm`
Bevestigt (eventueel gecorrigeerde) commando’s en committed wissels in één transactie.

Request:
- `match_id`
- `commands`: array `{ player_out_id, player_in_id }`
- `voice_log_id`

Response:
- `success`
- `applied_substitutions`
- `active_lineup`
- `events`

## 7. Service-laag

## 7.1 Nieuwe services

### `MatchLiveStateService`
Verantwoordelijk voor:
- actieve opstelling opbouwen per periode,
- slot van `player_out` bepalen,
- validaties tegen actuele staat,
- undo-logica.

### `MatchSubstitutionService`
Verantwoordelijk voor:
- transacties voor wissels,
- insert in `match_substitutions`,
- optionele sync naar `match_events`,
- teruggeven van bijgewerkte live state.

### `SttServiceInterface`
Contract:
- `transcribeCommand(AudioBlob, Context): SttResult`

Implementaties:
- `OpenRouterSttService` (MVP)
- `OpenAiTranscriptionService` (optioneel later)

### `VoiceCommandParser`
Verantwoordelijk voor:
- transcript normaliseren,
- patroonextractie (`X voor Y`),
- naamresolutie met alias/fuzzy/fonetisch,
- confidenceberekening,
- output als gestructureerde commando’s.

## 8. Naamsherkenning (optimalisatie met spelersdatabase)

Dit is cruciaal voor betrouwbaarheid.

## 8.1 Contextbeperking

Per commando:
- `UIT` kandidaten = spelers op het veld.
- `IN` kandidaten = spelers op de bank.

Hiermee reduceren we foutkansen drastisch.

## 8.2 Naamnormalisatie

Voor vergelijkingen:
- lowercase,
- diacritics verwijderen,
- interpunctie/hyphen normaliseren,
- dubbele spaties reduceren.

## 8.3 Alias- en fuzzy matching

Scoringcomponenten:
- exacte alias-hit,
- prefix-hit,
- Levenshtein/similarity,
- fonetische vergelijkbaarheid,
- contextbonus (veld/bank).

## 8.4 Confidencebeleid

Voorstel thresholds:
- `>= 0.90`: auto-accept (nog steeds zichtbaar in UI).
- `0.75 - 0.89`: bevestiging vereist.
- `< 0.75`: afwijzen met handmatige correctieflow.

## 8.5 Zelflerend gedrag

Bij bevestiging/correctie:
- log in `match_voice_command_logs`.
- optioneel voorstel om alias permanent toe te voegen.

## 9. Betrouwbaarheid en transactieveiligheid

## 9.1 Server is leidend voor tijd

- `clock_seconds` wordt server-side bepaald op basis van timer state.
- Niet vertrouwen op client-minuten voor officiële berekeningen.

## 9.2 Atomaire commit

Bij meerdere wissels in één voice-commando:
- alles binnen één DB-transactie,
- bij fout rollback van gehele batch.

## 9.3 Idempotency

Voeg `idempotency_key` toe bij commit-calls om dubbele verwerking door netwerk-retries te voorkomen.

## 9.4 Concurrency

Zet lock op matchstate tijdens commit (`BEGIN IMMEDIATE` in SQLite).

## 10. UI/UX in Live View

## 10.1 Nieuwe onderdelen

- Actieve opstelling (slots + huidige speler per slot).
- Banklijst.
- Push-to-talk knop.
- Bevestigingssheet voor herkende wissels.
- `Undo laatste wissel`.

## 10.2 Bedienflow

Spraakflow:
1. Coach drukt en spreekt.
2. Transcript + voorgestelde paren verschijnen.
3. Bij hoge confidence: 1-tap bevestigen (of auto met undo-optie).
4. Bij twijfel: snelle correctie per paar.

Handmatige fallback:
1. Tik speler `UIT` op veld.
2. Tik speler `IN` op bank.
3. Bevestig.

## 11. Minuten- en positiecalculatie

## 11.1 Berekeningsprincipe

Input:
- periode-startopstellingen,
- wissels met `clock_seconds`,
- periodegrenzen (whistles).

Output per speler:
- `total_seconds_played`,
- `seconds_per_slot` map,
- afgeleide minuten.

## 11.2 Algoritme (globaal)

Per periode:
1. Initialiseer actieve slots op basis van `match_period_lineups`.
2. Zet voor elke actieve speler een open stint (`start = period_start_seconds`).
3. Verwerk wissels chronologisch:
   - sluit stint van `out` op wisseltijd,
   - open stint van `in` op hetzelfde slot.
4. Sluit alle open stints op periode-einde.
5. Tel alles op over 4 periodes.

## 11.3 Presentatie

- Wedstrijddetail: tabel met totaalminuten + minuten per slot.
- Later uitbreidbaar naar teamrapportages.

## 12. Integratie met bestaande AI/OpenRouter-backbone

Hergebruik:
- `AiAccessService` voor toegangschecks/rate-limit/budget.
- `AiUsageService` voor provider usage logging.
- `OpenRouterClient` uitbreiden met audio-content builder.

Aanbevolen kleine uitbreidingen:
- Mogelijkheid om `provider` in usage op `openrouter_stt` te zetten.
- STT-specifieke `error_code` conventies (`stt_no_speech`, `stt_ambiguous_name`, etc.).

## 13. Beveiliging en privacy

- Alleen geauthenticeerde teamleden voor live-endpoints.
- CSRF op alle muterende endpoints.
- Audio tijdelijk opslaan (of direct in-memory verwerken) en daarna verwijderen.
- Geen langdurige opslag van ruwe audio in MVP; alleen transcript/audit metadata.

## 14. Testplan

## 14.1 Unit tests

- Parser: `X voor Y`, meerdere paren, ruiswoorden.
- Naamresolutie met aliases/typos.
- Confidenceberekening en thresholdgedrag.
- Live-state validaties (IN op bank, OUT op veld).

## 14.2 Integratietests

- Volledige flow voice -> confirm -> substitutions -> actieve opstelling.
- Undo na enkelvoudige en batchwissel.
- Minutenberekening scenario: 4x10 minuten, elke 5 minuten 3 wissels.

## 14.3 Handmatige veldtest

Met echte teamnamen en achtergrondgeluid:
- herkenningspercentage,
- confirm-ratio,
- correctieratio,
- tijd per wisselactie.

## 15. KPI's en acceptatiecriteria

MVP is geslaagd als:
- >= 90% van wissels binnen 5 seconden kan worden vastgelegd (spraak of fallback).
- 0 stille foutcommits bij lage confidence.
- Minutenberekening komt overeen met handmatige referentie op testwedstrijd.
- Undo herstelt zowel opstelling als minutenresultaat correct.

Operationele KPI's (eerste 4 weken):
- `% auto-accepted`.
- `% needs_confirmation`.
- `% corrected_after_recognition`.
- `% failed_voice_commands`.

## 16. Faseringsplan

## Fase 0 - Voorbereiding (0.5 dag)
- DB migraties ontwerpen.
- Service interfaces neerzetten.
- Feature flags toevoegen (`live_voice_enabled`).

## Fase 1 - Gestructureerde wissels zonder spraak (1-2 dagen)
- Nieuwe tabellen en endpoint `substitute` + `undo`.
- Actieve opstelling in live view.
- Minutencalculatie basis.

## Fase 2 - Spraak in MVP-vorm (1-2 dagen)
- Push-to-talk frontend.
- `voice-command` endpoint.
- Parser + alias/fuzzy + confirmflow.
- Logging in `match_voice_command_logs`.

## Fase 3 - Verfijning betrouwbaarheid (1 dag)
- Fallback naar nauwkeuriger audiomodel bij twijfel.
- Fine-tuning thresholds op basis van testdata.
- UX optimalisaties voor snelle correctie.

## Fase 4 - Rapportage en afronding (0.5-1 dag)
- Minuten/slotweergave in wedstrijddetail.
- Regressietests + documentatie update.

## 17. Concrete implementatielocaties

- DB migraties: `scripts/init_db.php`
- Routes: `src/routes.php`
- Controller-endpoints: `src/controllers/GameController.php`
- Domein/modelservices: `src/models/Game.php` + nieuwe services in `src/services/`
- Live view UI: `src/views/matches/live.php`
- Frontend live logic: `public/js/live-match.js`

## 18. Open punten (beslissing nodig)

1. Willen we in MVP direct multi-wissel in één commando ondersteunen, of eerst enkelvoudig?
2. Welke confidence-thresholds accepteren we initieel in productie?
3. Bewaren we ruwe transcripten standaard, of alleen bij fouten/confirmaties?
4. Willen we teambeheerders aliasbeheer in UI, of voorlopig alleen automatisch/log-driven?

## 19. Samenvatting

Dit plan levert een robuuste STT-wisselfunctionaliteit op de bestaande live view, met nadruk op betrouwbaarheid:
- gerichte wissels,
- contextgestuurde naamsherkenning op basis van bestaande spelersdatabase,
- transactieveilige opslag,
- accurate minuten- en positieanalyse,
- snelle correctie en undo.

De gekozen architectuur hergebruikt maximaal jullie bestaande OpenRouter/AI-backbone en houdt een expliciet pad open om later van STT-provider of endpoint te wisselen zonder domein-impact.
