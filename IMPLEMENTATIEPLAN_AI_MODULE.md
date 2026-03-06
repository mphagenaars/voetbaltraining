# Implementatieplan AI Oefenstof Module (Premium + OpenRouter)

## 1. Doel en scope

Dit plan beschrijft de volledige implementatie van een AI-module waarmee trainers via chat nieuwe oefeningen kunnen ontwikkelen, inclusief:

- Gestructureerde oefeninhoud passend in het bestaande oefenformat.
- AI-gegenereerde Konva-tekeningen die achteraf bewerkbaar blijven in de editor.
- Premium feature-toggle vanuit admin.
- OpenRouter integratie met modelselectie.
- Admin-beheer van beschikbare modellen.
- Beveiligd API-keybeheer via admin.
- Chatopslag per gebruiker/team.

Gekozen uitgangspunten:

- PremiumScope: globaal aan/uit voor hele installatie.
- Provider: OpenRouter.
- Modelbeheer: handmatige whitelist + default model.
- API key opslag: versleuteld in database.
- Canvas-output: strikt volgens huidige editor-tools/assets.

---

## 2. Architectuur-overzicht

### 2.1 Bestaande flow (hergebruik)

- Oefeningen worden beheerd via `ExerciseController` en bestaande views.
- `drawing_data` wordt als Konva JSON opgeslagen en in editor/viewer opnieuw geladen.
- Admin heeft bestaande CRUD-patronen (options beheer) die als blauwdruk dienen.

### 2.2 Nieuwe modules

1. **AI Settings module (admin)**
   - Toggle: `ai_enabled`
   - Default model: `ai_default_model`
   - API key status en beheer

2. **AI Models module (admin)**
   - Whitelist van toegestane OpenRouter modellen
   - Enable/disable + sortering

3. **AI Chat module (exercise form)**
   - Chat met context over oefendoelen
   - Voorstel van oefenvelden
   - Voorstel van canvas-tekening

4. **AI Service layer (backend)**
   - OpenRouter client
   - Prompting/response parsing
   - Validatie en normalisatie

5. **Konva Sanitizer/Normalizer**
   - Garandeert dat AI-output bewerkbaar en laadbaar blijft

6. **Chat persistence layer**
   - Sessies en berichten per gebruiker/team

---

## 3. Datamodel en migraties

Voeg onderstaande tabellen/keys toe in `scripts/init_db.php` via idempotente migraties (zelfde stijl als bestaande ALTER-checks).

### 3.1 `app_settings` (nieuw)

Doel: centrale key/value opslag voor applicatie-instellingen.

Aanbevolen kolommen:

- `id` INTEGER PK
- `key` TEXT UNIQUE NOT NULL
- `value` TEXT NULL
- `updated_at` TEXT NOT NULL

Initiële keys:

- `ai_enabled` = `0`
- `ai_default_model` = `NULL`
- `openrouter_api_key_enc` = `NULL`
- `ai_strict_editable_mode` = `1`

### 3.2 `ai_models` (nieuw)

- `id` INTEGER PK
- `model_id` TEXT UNIQUE NOT NULL  (bv. `openai/gpt-4o-mini`)
- `label` TEXT NOT NULL
- `enabled` INTEGER NOT NULL DEFAULT 1
- `sort_order` INTEGER NOT NULL DEFAULT 0
- `created_at` TEXT NOT NULL
- `updated_at` TEXT NOT NULL

### 3.3 `ai_chat_sessions` (nieuw)

- `id` INTEGER PK
- `user_id` INTEGER NOT NULL
- `team_id` INTEGER NULL
- `exercise_id` INTEGER NULL
- `title` TEXT NULL
- `created_at` TEXT NOT NULL
- `updated_at` TEXT NOT NULL

### 3.4 `ai_chat_messages` (nieuw)

- `id` INTEGER PK
- `session_id` INTEGER NOT NULL
- `role` TEXT NOT NULL  (`system|user|assistant|tool`)
- `content` TEXT NOT NULL
- `model_id` TEXT NULL
- `metadata_json` TEXT NULL
- `created_at` TEXT NOT NULL

### 3.5 Indexen

- `ai_models(enabled, sort_order)`
- `ai_chat_sessions(user_id, team_id, updated_at)`
- `ai_chat_messages(session_id, created_at)`

---

## 4. Admin functionaliteit

### 4.1 Routes

Voeg admin routes toe in `src/routes.php` voor:

- AI instellingen pagina (GET)
- Toggle opslaan (POST)
- Default model opslaan (POST)
- API key instellen/vervangen (POST)
- API key verwijderen (POST)
- Modellen beheren (create/update/delete/reorder/enable-disable)

Alle routes achter admin-check.

### 4.2 Controller-acties

Breid `AdminController` uit met:

- `manageAiSettings()`
- `updateAiEnabled()`
- `updateAiDefaultModel()`
- `saveOpenRouterApiKey()`
- `deleteOpenRouterApiKey()`
- `createAiModel()`
- `updateAiModel()`
- `deleteAiModel()`
- `reorderAiModels()`

Regels:

- Verplicht `requireAdmin()`.
- Voor elke mutatie `Csrf::verifyToken()`.
- Strict inputvalidatie en nette foutmeldingen.

### 4.3 Admin UI

Nieuwe admin-view “AI Module” met secties:

1. **Premium toggle**
   - Schakelaar AI module aan/uit.

2. **Modelbeheer**
   - Tabel met `model_id`, `label`, `enabled`, `sort_order`.
   - Default-model selector (alleen enabled modellen).

3. **API keybeheer**
   - Huidige status: “ingesteld / niet ingesteld”.
   - Masked preview (bv. `sk-or-****abcd`).
   - Acties: instellen/vervangen/verwijderen.

---

## 5. OpenRouter integratie

### 5.1 Service-laag

Maak een AI service (bijv. `src/services/Ai/OpenRouterClient.php`) met:

- HTTP requests naar OpenRouter Chat Completions endpoint.
- Headers:
  - `Authorization: Bearer <decrypted_api_key>`
  - `Content-Type: application/json`
- Timeout en foutafhandeling.
- Response mapping naar intern DTO/array contract.

### 5.2 Modelkeuze-runtime

- Inkomend model-id alleen accepteren als het enabled is in `ai_models`.
- Anders fallback naar `ai_default_model`.
- Als geen geldig model beschikbaar: duidelijke foutmelding richting UI.

### 5.3 Prompting

Gebruik gestructureerde prompts met expliciet outputcontract voor:

- Oefenvelden
- Objectives/actions waarden
- Canvas-DSL (niet direct vrije Konva JSON)

---

## 6. API key security design

### 6.1 Opslag

- Sleutel wordt **versleuteld** opgeslagen in `app_settings.openrouter_api_key_enc`.
- Nooit plaintext opslaan.

### 6.2 Encryptie

- Gebruik libsodium (voorkeur) of OpenSSL met authenticated encryption.
- Encryptiesleutel uit serverconfig (niet in database).
- Bij ontbrekende encryptiesleutel:
  - admin krijgt waarschuwing,
  - AI-calls worden geweigerd.

### 6.3 Logging en output

- API key nooit loggen.
- API key nooit terugsturen naar frontend.
- Fouten maskeren (geen secrets in exceptionmessages).

---

## 7. AI chat in exercise form

### 7.1 UI/UX

Voeg in oefenformulier een chatpaneel toe met:

- Vrije chatinput + context (doelen/team/leeftijd/niveau).
- Knoppen:
  - “Genereer voorstel”
  - “Pas tekstvelden toe”
  - “Pas tekening toe”
  - “Alles toepassen”

### 7.2 Premium-gating

- Als `ai_enabled=0`:
  - chatpaneel niet zichtbaar of disabled state met melding.
  - backend endpoints blokkeren hard.

### 7.3 Chatopslag

- Per gebruiker/team sessies opslaan.
- Bij team-switch alleen relevante sessies tonen.
- Retentiebeleid (bijv. max aantal sessies of ouderdom) toevoegen.

---

## 8. Oefen-output validatie

AI-output voor oefeninformatie moet worden genormaliseerd naar bestaand format:

- `title`
- `description`
- `variation`
- `coach_instructions`
- `source`
- `team_task`
- `objectives[]`
- `actions[]`
- `players_min/max`
- `duration_min/max`
- `field_type`

Validatieregels:

- Objectives/actions alleen waarden uit admin-opties of whitelist.
- Numerieke ranges clampen op veilige grenzen.
- `field_type` altijd naar toegestane enum normaliseren.
- Bij deels ongeldige output: herstelbare velden toepassen + waarschuwing tonen.

---

## 9. Editable-by-Design Konva specificatie

De AI levert een beperkt tekencontract (DSL), backend vertaalt dit naar geldig Konva layer JSON.

### 9.1 Toegestane objecttypes

| Type | Konva Node | Verplicht | Optioneel | Regels |
|---|---|---|---|---|
| speler/pion/bal | Image | x,y,width,height,draggable,imageSrc | rotation,scaleX,scaleY,opacity | imageSrc moet whitelisted asset zijn |
| lijn | Line | points,stroke,strokeWidth,draggable | lineCap,lineJoin,dash,opacity | min 2 puntenparen |
| pijl | Arrow | points,stroke,strokeWidth,pointerLength,pointerWidth,draggable | dash,opacity | min 2 puntenparen |
| zone | Rect | x,y,width,height,stroke,strokeWidth,draggable | fill,opacity,cornerRadius | width/height > 0 |
| tekstlabel* | Text | x,y,text,fontSize,draggable | fill,align,width,opacity | max lengte; alleen als tool actief is |

### 9.2 Globale sanitizer-regels

- Root moet `Layer` zijn.
- Onbekende node-types verwijderen.
- Alleen toegestane attrs per type behouden.
- Coördinaten/afmetingen clampen binnen veldgrenzen.
- Ongeldige nodes droppen zonder crash.
- JSON moet laadbaar zijn in editor én viewer.

### 9.3 Roundtrip eis

Moet slagen: **AI tekening laden → objecten handmatig bewerken → opslaan → opnieuw laden** zonder dat geldige objecten verdwijnen of corrupteren.

---

## 10. Endpointontwerp (MVP)

Aanbevolen interne endpoints:

- `POST /ai/chat/message`
  - input: session_id, message, optional model_id, optional context
  - output: assistant message + optional structured suggestions

- `POST /ai/chat/apply-text`
  - input: suggestion_id of structured payload
  - output: genormaliseerde oefenvelden

- `POST /ai/chat/apply-drawing`
  - input: drawing DSL of voorstel-id
  - output: sanitized `drawing_data`

- `GET /ai/chat/sessions`
- `GET /ai/chat/session/{id}`

Backend guardrails voor alle AI endpoints:

- Auth vereist
- Premium toggle vereist
- Geldige API key vereist
- Geldig model vereist

---

## 11. Validatie, testen en acceptatiecriteria

### 11.1 Functionele tests

- Admin kan toggle, modelbeheer en keybeheer uitvoeren.
- Exercise form toont/verbergt AI correct.
- AI vult oefenvelden conform format.
- AI tekening blijft bewerkbaar in editor.

### 11.2 Negatieve tests

- Toggle uit: alle AI endpoints geblokkeerd.
- Geen key: duidelijke fout, geen providercall.
- Ongeldig model: fallback of validatiefout.
- Foute canvas nodes: sanitizer verwijdert veilig.

### 11.3 Security tests

- CSRF op alle admin POST routes.
- Geen secret leakage in logs/responses.
- Alleen admin heeft toegang tot AI-config.

### 11.4 Regressie

- Bestaande oefenflow create/edit/view werkt onveranderd zonder AI.
- Regressietests uitbreiden voor nieuwe tabellen en settings.

---

## 12. Faseplanning

### Fase 1: Fundament

- DB migraties (settings, modellen, chat tabellen)
- Admin AI settings + modelbeheer + keybeheer

### Fase 2: Provider en chat

- OpenRouter client
- AI endpoints
- Chat UI in oefenformulier

### Fase 3: Kwaliteit en veiligheid

- Output normalizer/sanitizer
- Konva editable-by-design contract
- Extra validatie en foutafhandeling

### Fase 4: Hardening

- Testdekking
- Monitoring/logging zonder secrets
- Documentatie in README

---

## 13. Definition of Done

Deze feature is “done” wanneer:

1. Admin AI module globaal kan aan/uit zetten.
2. Admin OpenRouter API key veilig kan beheren (versleuteld opgeslagen).
3. Admin whitelistmodellen en defaultmodel kan beheren.
4. Trainer via chat oefenvoorstellen kan genereren en toepassen.
5. AI-gegenereerde tekeningen in Konva bewerkbaar blijven.
6. Chatgeschiedenis per gebruiker/team beschikbaar is.
7. Security-eisen (admin-only, CSRF, secret handling) aantoonbaar gehaald zijn.
8. Regressie op bestaande oefenfunctionaliteit groen blijft.

---

## 14. Open punten voor latere iteratie (niet-MVP)

- Automatische OpenRouter model sync (in plaats van handmatig).
- Per team premium i.p.v. globale toggle.
- Prompt presets per leeftijdsniveau/trainingsdoel.
- AI kwaliteitsfeedbackloop (coach rating op suggesties).
- Export/import van AI sessies.
