# Implementatieplan spraakgestuurde live events

## 1. Doel en uitgangspunten

Dit plan beschrijft de implementatie van spraakgestuurde **wedstrijdevents** in de bestaande live-wedstrijdweergave.

Primaire doelen:
- Tijdens de wedstrijd sneller events registreren met minimale interactie.
- Betrouwbare registratie van wissels, doelpunten, kaarten, kansen en notities.
- Nauwkeurige berekening van speeltijd per speler en speeltijd per positie-slot.

Belangrijke keuzes:
- We bouwen voort op de bestaande live view (timer/periodes blijven leidend).
- Het LLM doet de volledige interpretatie (spraak naar gestructureerde events).
- PHP doet alleen validatie tegen de actuele wedstrijdstaat — geen regex-parsing of fuzzy matching in code.
- Spraakinvoer krijgt altijd een snelle handmatige fallback en `undo`.

## 2. Huidige situatie

### Wat al bestaat (Fase 0 + 1, afgerond 2026-03-27):
- Live timer + periodes (`start/stop` via whistle events).
- Live events toevoegen (goal, kaart, wissel, notitie) — handmatig.
- Team- en spelersdata in database.
- Gestructureerde wisselregistratie (`match_substitutions` tabel).
- Periode-startopstelling via autosnapshot bij timer-start (`match_period_lineups`).
- Actieve opstelling in live view (veld + bank).
- Minutencalculatie per speler en per slot.
- `MatchLiveStateService` + `MatchSubstitutionService` volledig operationeel.

### Wat gebouwd is in Fase 2 backend (afgerond 2026-04-03):
- `SttServiceInterface` + `OpenRouterSttService` — LLM-gebaseerde audio-interpretatie.
- `VoiceCommandValidator` — validatie van LLM-events tegen live state.
- DB tabellen: `player_name_aliases`, `match_voice_command_logs`.
- `supports_audio` kolom op `ai_models` voor configureerbare modelkeuze.
- `Game` model uitgebreid met voice log + alias methoden.
- Controller endpoints: `voice-command` + `voice-command/confirm`.
- Routes geregistreerd.
- Alle syntaxchecks en regressietests groen.

### Wat gebouwd is in Fase 2b frontend (afgerond 2026-04-03):
- Push-to-talk knop (🎤) in live view met `MediaRecorder` API (mp4/webm/opus).
- Server-side audioconversie via ffmpeg (webm → wav) voor OpenAI-compatibiliteit.
- Audio-upload naar `voice-command` endpoint via multipart FormData.
- Bevestigings-bottomsheet met event-cards (type-icoon, spelersnamen, confidence-%).
- Confirm/reject flow naar `voice-command/confirm` met live state-update.
- Voice overlay met opname-indicator (pulserend), verwerkingsspinner, cancel-optie.
- Toast-notificaties bij succes/fout.
- Bugfix: confirm endpoint gebruikt nu substitution-result state i.p.v. verse `getLiveState()` zodat wissels bij stilstaande klok correct visueel worden doorgevoerd.
- Handmatige modal vereenvoudigd: event-type dropdown (doelpunt/wissel/kaart/notitie) vervangt losse knoppen.

### Wat nu ontbreekt:
- Rapportage minuten/slot in wedstrijddetail.
- Fine-tuning thresholds op basis van echte testdata.
- Correctiemogelijkheid per event in bevestigingssheet (dropdown alternatieven).

## 3. Scope

### In scope:
- Alle event-types via spraak: wissels, doelpunten, kaarten, kansen, notities.
- Meerdere events in een opname (`Thomas voor Jayden en doelpunt van Sem`).
- Gestructureerde opslag en audit trail.
- Periode-opstelling vastleggen voor periode 1..4.
- Minuten- en positieberekening op basis van opstelling + wissels + periodestops.
- Handmatige fallback + undo + bevestiging bij twijfel.

### Niet in scope (MVP):
- Automatische bulk-rotatie zonder expliciete instructie.
- Complexe wedstrijdanalyse/rapportage buiten minuten/positie.
- Aliasbeheer-UI voor teambeheerders (voorlopig log-driven).

## 4. Architectuur

### 4.1 Kernprincipe: LLM interpreteert, PHP valideert

```
Audio  -->  LLM (interpretatie + naammatching + JSON)  -->  PHP validator (integriteitscheck)
```

Het LLM krijgt volledige spelerscontext (veld, bank, aliassen met IDs) en retourneert gestructureerde events. PHP controleert alleen of de referenties kloppen tegen de actuele staat.

Waarom:
- Het LLM is van nature beter in taalinterpretatie, variatie-handling en fuzzy matching dan handgeschreven regex/Levenshtein.
- De coach kan elke natuurlijke formulering gebruiken, niet alleen "X voor Y".
- Uitbreiden naar nieuwe event-types kost alleen prompt-uitbreiding + een validator-regel — geen nieuwe parsing-code.

### 4.2 Gekozen route

OpenRouter-gebaseerde audioflow via chat completions met `input_audio` content block.

Hergebruikt bestaande infrastructuur:
- `AiAccessService` voor toegangschecks/rate-limit/budget.
- `AiUsageService` voor provider usage logging.
- `AiPricingEngine` voor kostenberekening.
- `OpenRouterClient` voor transport.

### 4.3 Fallback-strategie

Service-interface `SttServiceInterface` met methode `interpretAudio()`.

- Implementatie A (standaard): `OpenRouterSttService`.
- Implementatie B (optioneel later): `OpenAiTranscriptionService`.

### 4.4 Modellering

- Primair model: `openai/gpt-4o-mini-audio-preview` (via OpenRouter).
- Fallback bij lage confidence: configureerbaar nauwkeuriger model.
- Modelkeuze via `ai_models` tabel (`supports_audio = 1`), niet hardcoded.

## 5. Datamodel

### 5.1 Tabellen (alle aangemaakt in `scripts/init_db.php`)

#### `match_period_lineups` (Fase 1 - operationeel)
Wie op welk slot start in een periode.

#### `match_substitutions` (Fase 1 - operationeel)
Gestructureerde wisselregistratie met source (`manual`/`voice`), transcript en confidence.

#### `player_name_aliases` (Fase 2 - aangemaakt)
Team-specifieke naamsynoniemen. Worden als context meegegeven aan het LLM.

#### `match_voice_command_logs` (Fase 2 - aangemaakt)
Audit trail: raw transcript, LLM-response, parsed events, status, error codes.

### 5.2 Bestaande tabelgebruik

`match_events` blijft bestaan voor timeline-compatibiliteit.
- Wissels: gecommit via `MatchSubstitutionService` (schrijft naar `match_substitutions` + `match_events`).
- Overige events (goal, kaart, kans, notitie): gecommit via `Game::addEvent()`.

## 6. API-ontwerp (server endpoints)

### 6.1 Live opstelling en wissels (Fase 1 - operationeel)

- Autosnapshot bij `POST /matches/timer-action` met `action = start`.
- `POST /matches/live/substitute` — handmatige wissel.
- `POST /matches/live/substitute/undo` — laatste wissel terugdraaien.

### 6.2 Voice events (Fase 2 - backend operationeel)

#### `POST /matches/live/voice-command`
Interpreteert audio en retourneert gestructureerde events.

Request (multipart):
- `match_id`
- `audio_file` (webm/wav/m4a, max 10 MB)
- `csrf_token`
- `client_duration_ms` (optioneel)

Response:
- `success`
- `voice_log_id`
- `transcript` (wat de coach zei)
- `events`: array met gestructureerde events (type, player IDs, confidence, validatie-status)
- `requires_confirmation` boolean
- `reason` (bij twijfel)

#### `POST /matches/live/voice-command/confirm`
Bevestigt (eventueel gecorrigeerde) events en committed ze.

Request (JSON):
- `match_id`
- `voice_log_id`
- `events`: array met bevestigde events
- `csrf_token`

Response:
- `success`
- `applied_events`
- `active_lineup`, `bench`, `period`, `clock_seconds`, `minutes_summary`, `events`
- Optioneel: `warning` + `failed_event` bij gedeeltelijk succes.

### 6.3 Ondersteunde event-types

| Type | Velden | Commit-actie |
|---|---|---|
| `substitution` | `player_in_id`, `player_out_id` | `MatchSubstitutionService` |
| `goal` | `player_id`, optioneel `assist_player_id` | `Game::addEvent()` |
| `card` | `player_id`, `card_type` (yellow/red) | `Game::addEvent()` |
| `chance` | `player_id`, optioneel `detail` | `Game::addEvent()` |
| `note` | `text` | `Game::addEvent()` |

Uitbreiden: voeg type toe aan LLM-prompt, validator-regel, en dispatch in controller.

## 7. Service-laag

### 7.1 Operationele services

- **`MatchLiveStateService`** — actieve opstelling, slot-resolutie, minuten berekening.
- **`MatchSubstitutionService`** — wisselverwerking, undo, transactiebeheer.
- **`OpenRouterSttService`** — audio naar LLM sturen met spelerscontext, gestructureerde events terugkrijgen.
- **`VoiceCommandValidator`** — valideert LLM-events tegen live state (veld/bank/match).

### 7.2 Architectuur VoiceCommandValidator

Per event-type een validatieregel:
- `substitution`: player_out op veld? player_in op bank? niet dezelfde speler?
- `goal`, `card`, `chance`: player in wedstrijd?
- `note`: tekst niet leeg?

Confidencebeleid:
- `>= 0.90`: auto-accept (zichtbaar in UI, direct bevestigbaar).
- `0.75 - 0.89`: bevestiging vereist.
- `< 0.75`: afwijzen met reden.

## 8. Naamsherkenning

### 8.1 LLM-gestuurd (niet meer PHP-gestuurd)

Het LLM krijgt als context:
- Volledige spelerslijst veld + bank (met IDs, rugnummers, slot codes).
- Aliassen uit `player_name_aliases`.
- Instructie om IDs direct te matchen.

Het LLM doet intern: fuzzy matching, fonetische herkenning, contextbegrip, bijnamen. De PHP-laag doet hier niets meer aan.

### 8.2 Aliassen

`player_name_aliases` tabel wordt meegegeven als context aan het LLM. Bij bevestiging/correctie: log in `match_voice_command_logs`, optioneel voorstel om alias toe te voegen.

## 9. Betrouwbaarheid en transactieveiligheid

### 9.1 Server is leidend voor tijd
`clock_seconds` wordt server-side bepaald op basis van timer state.

### 9.2 Transactiebeheer
Elke wissel is individueel atomair via `MatchSubstitutionService` (eigen transactie). Bij meerdere events: sequentieel verwerkt. Bij falen van event N blijven events 1..N-1 gecommit — coach kan undo gebruiken.

### 9.3 Concurrency
`MatchSubstitutionService` gebruikt `BEGIN IMMEDIATE` in SQLite.

## 10. UI/UX in Live View

### 10.1 Bestaande onderdelen (Fase 1 - operationeel)
- Actieve opstelling (slots + huidige speler per slot).
- Banklijst.
- Handmatige wisselflow (tik UIT op veld, tik IN op bank, bevestig).
- Undo laatste wissel.

### 10.2 Nieuwe onderdelen (Fase 2 frontend - nog te bouwen)
- **Push-to-talk knop** met `MediaRecorder` API (webm/opus).
- **Bevestigingssheet** voor herkende events:
  - Per event: type-icoon, spelersnamen, confidence-indicator.
  - Correctiemogelijkheid per event (dropdown met alternatieven).
  - Bevestig-all / verwerp knop.
- **Visuele feedback**: opname-indicator, verwerking-spinner, resultaat-toast.

### 10.3 Bedienflow

Spraakflow:
1. Coach drukt push-to-talk knop in en spreekt.
2. Audio wordt verstuurd naar `voice-command` endpoint.
3. Herkende events verschijnen in bevestigingssheet.
4. Bij hoge confidence: 1-tap bevestigen.
5. Bij twijfel: snelle correctie per event, dan bevestigen.

Handmatige fallback (bestaand):
1. Tik speler `UIT` op veld → tik speler `IN` op bank → bevestig.

## 11. Minuten- en positiecalculatie (Fase 1 - operationeel)

Algoritme per periode:
1. Initialiseer actieve slots op basis van `match_period_lineups`.
2. Open stint per actieve speler op periode-start.
3. Verwerk wissels chronologisch (sluit stint out, open stint in).
4. Sluit alle open stints op periode-einde.
5. Tel op over alle periodes.

Output per speler: `total_seconds_played`, `seconds_per_slot` map, afgeleide minuten.

## 12. Beveiliging en privacy

- Alleen geauthenticeerde teamleden voor live-endpoints.
- CSRF op alle muterende endpoints.
- Audio wordt in-memory verwerkt (base64), niet opgeslagen op disk.
- Geen langdurige opslag van ruwe audio; alleen transcript/audit metadata in logs.
- Audiobestand max 10 MB, alleen toegestane MIME types.

## 13. Testplan

### 13.1 Unit tests
- `VoiceCommandValidator`: validatie per event-type, edge cases (speler niet op veld, dubbele wissel).
- Confidence thresholds en requires_confirmation logica.

### 13.2 Integratietests
- Volledige flow: voice-command → confirm → events in database → actieve opstelling bijgewerkt.
- Multi-event confirm (wissel + doelpunt in een commando).
- Undo na voice-bevestigde wissel.
- Foutafhandeling: ongeldig audio, STT-fout, validatiefout.

### 13.3 Handmatige veldtest
Met echte teamnamen en achtergrondgeluid:
- herkenningspercentage per event-type,
- confirm-ratio,
- correctieratio,
- tijd per event-registratie.

## 14. KPI's en acceptatiecriteria

MVP is geslaagd als:
- >= 90% van events binnen 5 seconden kan worden vastgelegd (spraak of fallback).
- 0 stille foutcommits bij lage confidence.
- Minutenberekening komt overeen met handmatige referentie op testwedstrijd.
- Undo herstelt zowel opstelling als minutenresultaat correct.

Operationele KPI's (eerste 4 weken):
- `% auto-accepted`
- `% needs_confirmation`
- `% corrected_after_recognition`
- `% failed_voice_commands`
- Verdeling per event-type.

## 15. Faseringsplan

### Fase 0 - Voorbereiding
- DB migraties ontwerpen.
- Service interfaces neerzetten.
- **Status (2026-03-27): afgerond.**

### Fase 1 - Gestructureerde wissels zonder spraak
- Tabellen `match_period_lineups`, `match_substitutions`.
- Endpoints `substitute` + `undo`.
- Autosnapshot bij timer-start.
- Actieve opstelling in live view.
- Minutencalculatie.
- **Status (2026-03-27): afgerond, regressietests groen.**

### Fase 2 - Spraakgestuurde events

#### 2a - Backend (afgerond)
- `SttServiceInterface` + `OpenRouterSttService` (LLM-interpretatie, niet alleen transcriptie).
- `VoiceCommandValidator` (PHP-validatie tegen live state).
- DB tabellen: `player_name_aliases`, `match_voice_command_logs`.
- `supports_audio` kolom op `ai_models`.
- `Game` model: voice log + alias methoden.
- Routes + controller endpoints: `voice-command`, `voice-command/confirm`.
- Confirm-endpoint verwerkt alle event-types (substitution, goal, card, chance, note).
- **Status (2026-04-03): afgerond, regressietests groen.**

#### 2b - Frontend (afgerond)
- Push-to-talk knop met `MediaRecorder` API (mp4 voorkeur, webm fallback).
- Server-side ffmpeg-conversie (webm → wav) voor provider-compatibiliteit.
- Audio-upload naar `voice-command` endpoint (multipart FormData).
- Bevestigings-bottomsheet met event-cards en confidence-indicator.
- Confirm/reject flow met live state-update (inclusief next-period fix).
- Visuele feedback: recording overlay, verwerkingsspinner, toast-notificaties.
- Handmatige modal: event-type dropdown vervangt losse actieknoppen.
- **Status (2026-04-03): afgerond, regressietests groen.**

### Fase 3 - Verfijning betrouwbaarheid
- Feature flag `live_voice_enabled` in app settings.
- **Status (2026-04-03): feature flag afgerond.**
- Fine-tuning confidence thresholds op basis van echte testdata.
- Optionele fallback naar nauwkeuriger audiomodel bij twijfel.
- UX optimalisaties voor snelle correctie.

### Fase 4 - Rapportage en afronding
- Minuten/slot weergave in wedstrijddetail.
- Regressietests uitbreiden met voice-specifieke scenario's.
- Documentatie update.

## 16. Concrete implementatielocaties

| Onderdeel | Locatie | Status |
|---|---|---|
| DB migraties | `scripts/init_db.php` | Gereed |
| Routes | `src/routes.php` | Gereed |
| Controller endpoints | `src/controllers/GameController.php` | Gereed |
| STT service | `src/services/OpenRouterSttService.php` | Gereed |
| STT interface | `src/services/SttServiceInterface.php` | Gereed |
| Event validator | `src/services/VoiceCommandValidator.php` | Gereed |
| Voice log + alias model | `src/models/Game.php` | Gereed |
| Live state service | `src/services/MatchLiveStateService.php` | Gereed |
| Substitution service | `src/services/MatchSubstitutionService.php` | Gereed |
| Live view UI | `src/views/matches/live.php` | Gereed |
| Frontend live logic | `public/js/live-match.js` | Gereed |
| Voice UI CSS | `public/css/style.css` | Gereed |
| Audio conversie | `src/services/OpenRouterSttService.php` (ffmpeg) | Gereed |
| Feature flag setting | `src/controllers/AiAdminController.php` | Gereed |
| Feature flag UI | `src/views/admin/ai_settings.php` | Gereed |
| Feature flag route | `src/routes.php` (`/admin/ai/live-voice`) | Gereed |

## 17. Open punten

1. ~~Multi-event in een commando~~ → Ja, het LLM retourneert meerdere events per audio. Besloten.
2. Welke confidence-thresholds in productie? → Start met 0.90/0.75, fine-tune na veldtest (Fase 3).
3. ~~Ruwe transcripten bewaren?~~ → Ja, altijd. Staat in `match_voice_command_logs`. Besloten.
4. Aliasbeheer-UI voor teambeheerders? → Niet in MVP, voorlopig log-driven. Besloten.
5. ~~Audioformaat browser → provider~~ → mp4 voorkeur, webm fallback met server-side ffmpeg-conversie naar wav. Besloten.
6. ~~Audiomodel~~ → `openai/gpt-audio-mini` via OpenRouter. Moet handmatig in DB staan (`supports_audio = 1`). Besloten.

## 18. Eerstvolgende stap

**Fase 3 (vervolg): Verfijning betrouwbaarheid op basis van veldtestdata.**

Concrete taken:
1. Fine-tune confidence thresholds (0.90/0.75) op basis van echte veldtestdata.
2. Correctiemogelijkheid per event in bevestigingssheet (dropdown met alternatieven).
3. Optionele fallback naar nauwkeuriger audiomodel bij lage confidence.
4. UX optimalisaties: snellere correctie, betere foutmeldingen.

Daarna Fase 4:
5. Minuten/slot weergave in wedstrijddetail.
6. Voice-specifieke regressietests.
7. Documentatie update.
