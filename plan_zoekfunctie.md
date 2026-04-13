# Plan zoekfunctie

## 1. Doel

We bouwen een nieuwe zoekfunctie voor oefenstof die:

- één vrije zoekvraag accepteert;
- interne oefeningen en YouTube-resultaten in één lijst toont;
- LLM-zoekintelligentie behoudt voor query generation, understanding en reranking;
- niet meer leunt op translatability, segmentkeuze of zware video-analyse als standaardroute;
- externe resultaten met minimale frictie omzet naar een concept-oefening in de eigen database.

Dit plan is expliciet opgezet voor de huidige PHP-codebase, maar met een service-architectuur die meebeweegt naar PostgreSQL.

## 2. Uitgangspunten

### Product

- De gebruiker denkt in zoekvragen, niet in interne of externe bronnen.
- De standaard UX is één zoekveld en één gerankte lijst.
- Bronherkomst is zichtbaar op kaartniveau, niet leidend in de schermstructuur.
- Interne resultaten moeten bruikbaar blijven als YouTube uitvalt.
- Externe resultaten moeten eenvoudig te importeren zijn als concept.

### Techniek

- We behouden de kracht van de bestaande LLM-zoekintelligentie.
- We gebruiken geen LLM als enige retrieval-mechaniek.
- Retrieval en ranking worden gescheiden.
- De nieuwe zoekroute wordt los gebouwd van de huidige AI-chat/video-flow.
- Database-specifieke zoekoptimalisatie blijft verwisselbaar, zodat de overgang van SQLite naar PostgreSQL geen herontwerp van de zoekarchitectuur vraagt.

## 3. Kernbeslissing: hybride zoekmodel

De nieuwe zoekfunctie krijgt twee LLM-momenten en twee deterministische retrieval-paden.

### Stap A: query understanding

Input:

> "JO9 passingoefening met veel herhalingen en weinig wachten"

Output:

- harde signalen:
  - leeftijdsgroep: `JO9`
  - spelersaantal: onbekend
  - thema: `passing`
  - constraints: `veel herhalingen`, `weinig wachten`
- retrieval hints:
  - synoniemen
  - alternatieve formuleringen
  - 1 tot 3 YouTube-queries

### Stap B: retrieval

Parallel:

- interne retrieval op bestaande oefeningen;
- externe retrieval op YouTube.

### Stap C: LLM-reranking

De LLM beoordeelt niet de hele wereld, maar alleen een beperkte kandidatenpool:

- welke resultaten passen echt bij de vraag;
- welke zijn praktisch bruikbaar;
- waarom past dit resultaat;
- welke interne resultaten verdienen voorrang;
- welke YouTube-resultaten zijn goede inspiratie of importkandidaten.

### Stap D: import

Een extern resultaat wordt met één actie omgezet naar een concept-oefening met prefilled velden.

## 4. Wat we behouden uit de bestaande AI-stack

Deze onderdelen zijn herbruikbaar:

- YouTube query generation uit `AiRetrievalService`
- YouTube metadata/detail-fetch en source cache
- delen van promptbouw en evidence thinking
- concept-prefill logica uit de huidige conceptmodus

Deze onderdelen zijn niet langer leidend:

- translatability als hoofdscore
- segmentkeuze als standaardroute
- zware video-analyse vóór de eerste resultaatweergave

## 5. Doelarchitectuur

### Nieuwe services

#### `QueryUnderstandingService`

Verantwoordelijk voor:

- interpreteren van de zoekvraag;
- combineren van rules + LLM;
- maken van een `query_profile`;
- genereren van 1 tot 3 YouTube-queries;
- genereren van interne queryvarianten en synoniemen.

Belangrijke regel:

- deterministic-first voor harde signalen;
- LLM voor semantische verrijking.

#### `InternalExerciseSearchService`

Verantwoordelijk voor:

- ophalen van interne kandidaten;
- tekstuele match;
- metadata match;
- harde fit-scoring;
- quality-scoring;
- fit reasons.

Belangrijke regel:

- implementeer dit achter een repository/adapter, zodat de zoek-implementatie later van SQLite naar PostgreSQL kan wisselen zonder de rest van de flow te herschrijven.

#### `YouTubeSourceSearchService`

Verantwoordelijk voor:

- uitvoeren van de YouTube-queries;
- dedupe op `video_id`;
- gebruik van bestaande source cache;
- basisverrijking van resultaten;
- optioneel genereren van korte AI-snippets of fit reasons.

Belangrijke regel:

- deze service gebruikt de bestaande retrieval-infrastructuur, maar niet de huidige chat/segment/translatability UX.

#### `SearchResultNormalizer`

Verantwoordelijk voor:

- interne en externe resultaten omzetten naar één UI-model.

#### `SearchRankingService`

Verantwoordelijk voor:

- samenvoegen van interne en externe kandidaten;
- deterministische basis-score;
- LLM-reranking van topkandidaten;
- toevoegen van `fit_reasons`;
- lichte bonus voor interne resultaten.

#### `ExerciseImportService`

Verantwoordelijk voor:

- omzetten van een extern resultaat naar een concept-oefening;
- duplicate prevention;
- prefill van velden;
- redirect naar het edit-scherm.

#### `ConceptExercisePrefillService`

Verantwoordelijk voor:

- AI-ondersteunde conceptprefill op basis van brondata;
- hergebruik van bestaande prompt/output-logica, maar los van chat.

## 6. Zoekflow

### 6.1 Eindsituatie

1. gebruiker opent zoekscherm;
2. gebruiker typt een vrije zoekvraag;
3. backend bouwt een `query_profile`;
4. interne en externe retrieval starten parallel;
5. resultaten worden genormaliseerd;
6. rankingservice bepaalt de eindvolgorde;
7. UI toont één lijst;
8. gebruiker kiest:
   - `Openen`
   - `Aan training toevoegen`
   - `Bekijk bron`
   - `Voeg toe als concept`

### 6.2 Fallbacks

- faalt de LLM bij query understanding:
  - dan gebruiken we regels + simpele fallback-query’s;
- faalt YouTube:
  - dan tonen we alleen interne resultaten;
- faalt LLM-reranking:
  - dan tonen we deterministisch gesorteerde kandidaten;
- faalt conceptprefill:
  - dan maken we nog steeds een concept met minimale brondata aan.

## 7. Datamodel

### 7.1 Bestaande tabel `exercises`

De bestaande `exercises`-tabel blijft de basis.

Aanbevolen uitbreidingen:

- `source_type`
- `source_provider`
- `source_external_id`
- `source_url`
- `import_status`
- `imported_from_search`

### 7.2 Richtlijn voor database-implementatie

Omdat PostgreSQL op korte termijn komt:

- voeg de functionele velden nu al toe als dat nodig is voor import en UI;
- bouw zoeklogica niet hard vast op SQLite-specifieke FTS;
- houd de interne zoekengine achter een abstraction layer;
- plan de definitieve zoekindex voor PostgreSQL.

### 7.3 PostgreSQL-richting

Na migratie:

- `tsvector` + GIN-indexen voor full-text search;
- `pg_trgm` voor fuzzy matching;
- heroverweeg `training_objective` en `football_action`:
  - `jsonb`
  - `text[]`
  - of normalisatie naar relationele koppeltabellen.

### 7.4 Dedupe

Voorkom dubbele import op:

- `source_provider`
- `source_external_id`

Gebruik hiervoor een unieke constraint of index.

## 8. API-ontwerp

### `POST /search/exercises`

Request:

```json
{
  "query": "JO9 passingoefening met veel herhalingen",
  "filters": {
    "source_mode": "all"
  }
}
```

Response:

```json
{
  "query_profile": {
    "age_group": "JO9",
    "players": null,
    "themes": ["passing"],
    "constraints": ["veel herhalingen"]
  },
  "results": [
    {
      "type": "internal",
      "id": 123,
      "title": "Pass & draai open",
      "snippet": "Korte passingoefening voor JO9...",
      "source_label": "Eigen bibliotheek",
      "tags": ["passing", "vrijlopen"],
      "fit_reasons": ["Past bij JO9", "Veel herhalingen"],
      "score": 0.89,
      "actions": ["open", "add_to_training"]
    },
    {
      "type": "youtube",
      "external_id": "abc123",
      "title": "U9 Passing Drill",
      "snippet": "YouTube-oefening met hoge intensiteit...",
      "source_label": "YouTube",
      "thumbnail_url": "...",
      "tags": ["passing"],
      "fit_reasons": ["Veel herhalingen", "Geschikt voor jeugd"],
      "score": 0.84,
      "actions": ["view_source", "import_as_concept"]
    }
  ]
}
```

### `POST /search/exercises/import`

Request:

```json
{
  "source_type": "youtube",
  "external_id": "abc123"
}
```

Response:

```json
{
  "exercise_id": 456,
  "status": "concept",
  "redirect_url": "/exercises/edit?id=456"
}
```

## 9. UI-ontwerp

### Zoekscherm

- één zoekveld;
- optionele filterchips;
- één lijst met resultaten;
- loading state;
- empty state;
- foutmelding voor externe bron zonder blokkade van interne resultaten.

### Resultaatkaart intern

- titel
- korte beschrijving
- relevante tags
- `waarom dit past`
- badge `Eigen bibliotheek`
- acties:
  - `Openen`
  - `Aan training toevoegen`

### Resultaatkaart extern

- thumbnail
- titel
- kanaal
- korte samenvatting
- `waarom dit past`
- badge `YouTube`
- acties:
  - `Bekijk bron`
  - `Voeg toe als concept`

### Importscherm

Na import:

- direct naar bestaand exercise edit-scherm;
- broninformatie bovenaan zichtbaar;
- label `Concept uit YouTube`;
- velden grotendeels vooraf ingevuld.

## 10. Rankingmodel

### 10.1 Basisprincipe

Ranking is gelaagd:

1. deterministische retrieval-score
2. quality/hard-fit correcties
3. LLM-reranking op topkandidaten
4. lichte bonus voor interne resultaten

### 10.2 Interne score

Voorstel:

```text
score_internal =
  hard_fit * 0.35 +
  semantic_fit * 0.25 +
  text_fit * 0.20 +
  quality_score * 0.20
```

### 10.3 Externe score

Voorstel:

```text
score_external =
  semantic_fit * 0.35 +
  text_fit * 0.20 +
  source_quality * 0.20 +
  importability_score * 0.25
```

### 10.4 Opmerking

`usage_score` schuiven we door naar een latere fase. De huidige codebase heeft wel signalen via `activity_logs` en `training_exercises`, maar die zijn nu nog te dun om fase 1 op te baseren.

## 11. Hergebruik uit huidige codebase

### Direct hergebruiken

- `AiRetrievalService` voor query generation en source fetch
- `YouTubeSearchClient` voor YouTube API
- `ai_source_cache` voor caching
- delen van `AiPromptBuilder`
- delen van conceptprefill/output parsing
- bestaand exercise edit-scherm

### Alleen als inspiratie, niet als basis

- `AiWorkflowService` search/video-choice flow
- huidige AI-chat UX
- segmentkeuze
- translatability review
- frame extraction als verplichte tussenstap

## 12. Implementatiefases

## Fase 0: afbakening en feature flag

Doel:

- nieuwe flow kunnen bouwen zonder de oude flow te breken.

Taken:

- voeg app setting toe voor feature flag;
- bepaal of de nieuwe zoek-UI onder `/exercises` of een nieuwe route start;
- leg API-contract en result model vast;
- leg servicegrenzen vast.

Deliverable:

- technische basisbesluiten zijn genomen;
- nieuwe flow kan achter feature flag worden ontwikkeld.

Checklist:

- [ ] feature flag gedefinieerd
- [ ] resultaatmodel vastgelegd
- [ ] API-contract vastgelegd
- [ ] scope van fase 1 goedgekeurd

## Fase 1: query understanding

Doel:

- één consistente vertaling van vrije tekst naar een zoekprofiel.

Taken:

- `QueryUnderstandingService` bouwen;
- deterministic parsers voor:
  - leeftijdsgroep
  - spelersaantal
  - oefenvorm
  - eenvoudige constraints
- LLM-laag toevoegen voor:
  - synoniemen
  - impliciete bedoeling
  - queryvarianten
  - YouTube-query generation
- fallbackgedrag uitwerken.

Deliverable:

- backend geeft betrouwbaar `query_profile` terug voor vrije zoekvragen.

Checklist:

- [ ] harde signalen worden zonder LLM herkend
- [ ] LLM verrijkt query met semantische hints
- [ ] maximaal 1 tot 3 YouTube-queries worden gegenereerd
- [ ] fallback zonder LLM is aanwezig
- [ ] tests voor voorbeeldqueries aanwezig

## Fase 2: interne retrieval MVP

Doel:

- interne resultaten niet meer primair op datum sorteren.

Taken:

- `InternalExerciseSearchService` bouwen;
- repository/adapter introduceren voor database-afhankelijke zoekimplementatie;
- zoekvelden opnemen:
  - title
  - description
  - variation
  - coach_instructions
  - source
  - team_task
  - training_objective
  - football_action
- hard-fit scoring toevoegen;
- quality-score toevoegen;
- fit reasons genereren.

Deliverable:

- interne zoekresultaten zijn inhoudelijk relevanter dan de huidige lijst.

Checklist:

- [ ] interne retrieval is losgekoppeld van `Exercise::search()`
- [ ] datum is niet meer de primaire sortering
- [ ] metadata en tekst tellen beide mee
- [ ] fit reasons worden teruggegeven
- [ ] tests dekken ranking- en filtercases

## Fase 3: YouTube retrieval

Doel:

- externe bron als eerste inspiratie- en importkanaal toevoegen.

Taken:

- `YouTubeSourceSearchService` bouwen;
- bestaande query generation hergebruiken;
- bestaande cache hergebruiken;
- zoekresultaten dedupen;
- basisverrijking toevoegen:
  - kanaal
  - thumbnail
  - snippet
  - duration
- optioneel AI-samenvatting en fit reasons toevoegen.

Deliverable:

- stabiele YouTube-resultaten per zoekvraag.

Checklist:

- [ ] query generation gebruikt LLM
- [ ] dedupe op `video_id` werkt
- [ ] cache wordt benut
- [ ] externe fouten blokkeren de flow niet
- [ ] resultaten hebben basis metadata

## Fase 4: normalisatie en gecombineerde ranking

Doel:

- intern en extern in één lijst tonen.

Taken:

- `SearchResultNormalizer` bouwen;
- `SearchRankingService` bouwen;
- deterministische basis-score definiëren;
- LLM-reranking op topkandidaten toevoegen;
- lichte interne bonus instellen;
- `fit_reasons` genereren of verrijken.

Deliverable:

- één bruikbare, gecombineerde resultatenlijst.

Checklist:

- [ ] intern en extern delen hetzelfde UI-model
- [ ] ranking werkt zonder LLM als fallback
- [ ] LLM-reranking werkt op beperkte topkandidaten
- [ ] interne bonus is klein en niet dominant
- [ ] top 10 kan intern en extern gemengd bevatten

## Fase 5: endpoint en UI

Doel:

- de nieuwe zoekervaring zichtbaar maken in de app.

Taken:

- nieuwe controller bouwen, bijvoorbeeld `ExerciseSearchController`;
- routes toevoegen voor search endpoint en import endpoint;
- nieuwe zoek-UI op exercise-pagina bouwen;
- result cards bouwen;
- loading, errors en empty states bouwen;
- koppeling met training-flow behouden.

Deliverable:

- werkende zoekpagina in de app.

Checklist:

- [ ] nieuwe endpoint reageert met JSON
- [ ] zoek-UI toont één lijst
- [ ] bronbadge is zichtbaar
- [ ] bronafhankelijke acties werken
- [ ] externe fouten tonen alleen een melding, geen harde blokkade

## Fase 6: importflow

Doel:

- YouTube-resultaat omzetten naar concept-oefening.

Taken:

- `ExerciseImportService` bouwen;
- nieuwe velden in `exercises` benutten;
- duplicate prevention inbouwen;
- `ConceptExercisePrefillService` bouwen;
- bestaand exercise form hergebruiken als edit-bestemming;
- broninformatie visueel duidelijk maken.

Deliverable:

- gebruiker kan een extern resultaat als concept toevoegen en direct verder bewerken.

Checklist:

- [ ] duplicate prevention werkt
- [ ] concept krijgt importstatus
- [ ] bron-URL en externe id worden opgeslagen
- [ ] edit redirect werkt
- [ ] prefill vult minimaal 80 procent bruikbare basisinformatie in

## Fase 7: tuning en opschonen

Doel:

- kwaliteit, performance en beheerbaarheid verbeteren.

Taken:

- synonymenlijst uitbreiden;
- usage-signals toevoegen;
- ranking finetunen;
- cachestrategie verbeteren;
- oude flow achter feature flag zetten of afschalen;
- ongebruikte UX-paden opschonen.

Deliverable:

- zoekfunctie is stabieler, sneller en beter te onderhouden.

Checklist:

- [ ] usage-signals toegevoegd
- [ ] synonymenlijst uitgebreid
- [ ] cache-policy aangescherpt
- [ ] oude flow staat achter flag
- [ ] overbodige video-first logica is uit hoofdpad gehaald

## 13. Bestandsimpact

Waarschijnlijke nieuwe bestanden:

- `src/services/QueryUnderstandingService.php`
- `src/services/InternalExerciseSearchService.php`
- `src/services/InternalExerciseSearchRepository.php`
- `src/services/YouTubeSourceSearchService.php`
- `src/services/SearchResultNormalizer.php`
- `src/services/SearchRankingService.php`
- `src/services/ExerciseImportService.php`
- `src/services/ConceptExercisePrefillService.php`
- `src/controllers/ExerciseSearchController.php`

Waarschijnlijke aanpassingen:

- `src/routes.php`
- `src/controllers/ExerciseController.php`
- `src/models/Exercise.php`
- `src/models/AppSetting.php`
- `src/services/AiRetrievalService.php`
- `src/services/AiPromptBuilder.php`
- `src/views/exercises/index.php`
- `src/views/exercises/form.php`
- `public/css/style.css`
- nieuw frontend script voor search-UI
- `scripts/init_db.php`
- `scripts/regression_tests.php`

## 14. Risico's en mitigatie

### Risico: LLM maakt de zoekfunctie te traag

Mitigatie:

- LLM alleen voor query understanding en reranking;
- beperkte kandidaatset;
- deterministische fallback;
- caching op queryvarianten waar zinvol.

### Risico: slechte zoekinput geeft slechte YouTube-resultaten

Mitigatie:

- LLM-query generation vóór YouTube-search;
- meerdere queryvarianten;
- heuristische parsing van harde constraints;
- reranking achteraf.

### Risico: database-implementatie moet straks herschreven worden voor PostgreSQL

Mitigatie:

- repository/adapter rond interne retrieval;
- zoekcontract scheiden van SQL-implementatie;
- PostgreSQL-optimalisatie pas afronden na migratie.

### Risico: oude AI-videoflow blijft doorwerken in de nieuwe UX

Mitigatie:

- aparte controller en services voor search;
- geen directe afhankelijkheid van chat-sessies, segmentatie of translatability;
- alleen gerichte hergebruik van bestaande infrastructuur.

## 15. Acceptatiecriteria

### Zoekervaring

- gebruiker kan één vrije zoekvraag invoeren;
- systeem toont één gecombineerde gerankte lijst;
- interne en externe resultaten kunnen samen in de top 10 voorkomen;
- elk resultaat toont bron en `waarom dit past`.

### Interne zoekfunctie

- resultaten worden niet meer primair op datum gesorteerd;
- metadata en vrije tekst tellen beide mee;
- ranking voelt duidelijk relevanter dan de huidige zoekfunctie.

### Externe YouTube-zoekfunctie

- maximaal 1 tot 3 query’s per gebruikersvraag;
- resultaten worden gededuped;
- resultaten bevatten titel, thumbnail, bron en samenvatting;
- externe zoekfouten blokkeren interne resultaten niet.

### Import

- gebruiker kan een extern resultaat omzetten naar een concept-oefening;
- duplicate import wordt voorkomen;
- concept-oefening is vooraf ingevuld;
- gebruiker landt direct in het edit-scherm.

### Stabiliteit

- fallback zonder LLM blijft bruikbaar;
- fallback zonder YouTube blijft bruikbaar;
- caching vermindert onnodige API-calls;
- de nieuwe flow staat los van verplichte video-segmentatie.

## 16. Aanbevolen implementatievolgorde

Definitieve volgorde:

1. feature flag + API-contract + result model
2. `QueryUnderstandingService`
3. `InternalExerciseSearchService` MVP
4. `YouTubeSourceSearchService`
5. `SearchResultNormalizer`
6. `SearchRankingService`
7. `POST /search/exercises`
8. nieuwe zoek-UI
9. `ExerciseImportService`
10. `ConceptExercisePrefillService`
11. `POST /search/exercises/import`
12. tuning, caching, usage-signals
13. oude flow afschalen

## 17. Beslissingen die nog expliciet genomen moeten worden

- Start de nieuwe UX onder `/exercises` of op een aparte route?
- Doen we fase 2 intern eerst simpel in SQLite en pas echt sterk na PostgreSQL?
- Willen we conceptimport direct synchronisch of deels async?
- Hoeveel LLM-kandidaten mogen we reranken per zoekactie?
- Welke minimale bronkwaliteit is vereist voor `Voeg toe als concept`?

## 18. Conclusie

We behouden de bestaande investering in LLM-zoekintelligentie, maar zetten die gerichter in:

- vóór retrieval voor slim querybegrip en query generation;
- ná retrieval voor reranking en uitleg;
- niet meer als hoofdmechaniek voor video-vertaalbaarheid of segmentkeuze.

Zo bouwen we een zoekfunctie die:

- slimmer is dan pure keyword search;
- betrouwbaarder is dan een volledig LLM-gedreven flow;
- productmatig beter aansluit op wat de gebruiker wil;
- technisch beter meebeweegt naar PostgreSQL.
