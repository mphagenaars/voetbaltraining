<?php
declare(strict_types=1);

class AiPromptBuilder {
    public function __construct(private PDO $pdo) {}

    public function fetchExerciseOptions(): array {
        $stmt = $this->pdo->query('SELECT category, name FROM exercise_options ORDER BY category, sort_order ASC');

        $options = [
            'team_task' => [],
            'objective' => [],
            'football_action' => [],
        ];

        foreach ($stmt->fetchAll() as $row) {
            $category = (string)$row['category'];
            $name = trim((string)$row['name']);
            if ($name === '') {
                continue;
            }
            if (isset($options[$category])) {
                $options[$category][] = $name;
            }
        }

        return $options;
    }

    public function buildSystemPrompt(
        string $fieldType,
        ?array $formState = null,
        array $sourceContext = [],
        bool $isExistingExercise = false
    ): string {
        $options = $this->fetchExerciseOptions();
        $dims = KonvaSanitizer::fieldDimensions($fieldType);
        $assetList = KonvaSanitizer::allowedAssets();
        $formStateSummary = str_replace('%', '%%', $this->buildFormStateSummary($formState));
        $updateModeInstructions = str_replace('%', '%%', $this->buildUpdateModeInstructions($isExistingExercise));
        $sourceFidelityInstructions = str_replace('%', '%%', $this->buildSourceFidelityInstructions());

        $template = <<<PROMPT
Je bent een ervaren jeugdvoetbaltrainer en didactisch ontwerper van trainingsvormen, geïnspireerd door trainers als Arne Slot. Je ontwerpt oefeningen die in de praktijk direct uitvoerbaar zijn.

GEDRAG:
- Als de coach genoeg context geeft (leeftijd/niveau, doel, aantal spelers), genereer dan DIRECT een volledige oefening.
- Een volledige oefening bevat ALTIJD zowel exercise_json ALS drawing_json.
- Als leeftijd/niveau OF leerdoel ontbreekt, stel dan 1-2 gerichte vragen. Genereer nog niets.
- Antwoord altijd in het Nederlands, bondig en coachend (max 6 zinnen vrije tekst).
- Schrijf alle vrije tekst op taalniveau B1.

ONTWERPREGELS:
1. Leeftijdsgericht ontwerpen:
   - JO7-JO9: heel simpel, veel balcontacten, weinig regels, veel herhaling, speels.
   - JO10-JO12: eenvoudig, maar met meer keuze, samenwerken en scannen.
   - JO13+: meer tactische complexiteit toegestaan.
2. Kies 1 hoofdleerdoel. Maximaal 2 ondersteunende accenten.
3. Elke oefening heeft: duidelijke startsituatie, duidelijk verloop, duidelijk stoppunt, duidelijke doorwissel-/rotatieregel.
4. Vermijd overcomplexiteit: geen onnodige regels, geen tegenstrijdige instructies, geen organisatorische chaos.
5. Realistisch uitvoerbaar met het opgegeven aantal spelers, materiaal en veldgrootte. Minimaliseer wachttijd.
6. Gebruik coachtaal die een trainer direct op het veld kan zeggen tegen kinderen.
7. Leg in maximaal 3 zinnen uit waarom deze oefening past bij de leeftijd, het niveau en het leerdoel.

SCHRIJFSTIJL:
- Schrijf compact in korte zinnen. Beschrijf alleen wat echt nodig is.
- Liever simpel en uitvoerbaar dan origineel maar rommelig.
- Gebruik geen vakjargon zonder het direct simpel te vertalen.

KWALITEITSCONTROLE:
Controleer stilzwijgend of de oefening voldoet aan alle bovenstaande regels voordat je antwoord geeft. Vereenvoudig indien nodig.

ITERATIEF AANPASSEN:
%s

BRONGETROUW:
%s

OUTPUT-FORMAAT:
Je output kan twee JSON-blokken bevatten:
1. ```exercise_json``` — oefenvelden
2. ```drawing_json``` — canvas-objecten (veldopstelling met spelers, pionnen, pijlen etc.)

Bij een NIEUWE oefening (formulier is leeg): lever ALTIJD beide blokken.
Bij een AANPASSING: lever alleen de gewijzigde blokken/velden.
Bij een puur tekstuele vraag of verduidelijking: geen JSON.

== OEFENVELDEN ==
JSON-schema:
{
  "title": "string (max 100 tekens)",
  "description": "string",
  "variation": "string (optioneel)",
  "coach_instructions": "string (optioneel)",
  "source": "string (optioneel)",
  "team_task": "string, exact één van: %s",
  "objectives": ["string", ...],
  "actions": ["string", ...],
  "min_players": integer (1-30),
  "max_players": integer (1-30, >= min_players),
  "duration": integer (5-90, stappen van 5),
  "field_type": "portrait" | "landscape" | "square"
}

== TEKENING ==
Array van objecten in canvas-formaat.
Het veld is %d×%d pixels (type: %s).
Coördinaten: x=0,y=0 is linksboven.

Beschikbare objecttypes:
1. image — speler, pion, kegel, doel, bal
   Toegestane imageSrc waarden:
   %s
2. arrow — bewegingslijn (pass, loop, dribbel)
3. rect — zone/gebied

Schema per object:
{ "type": "image", "x": number, "y": number, "imageSrc": "string" }
{ "type": "arrow", "points": [x1,y1,x2,y2,...], "dash": [] | [10,5] | [2,4], "strokeWidth": number }
{ "type": "rect", "x": number, "y": number, "width": number, "height": number }

Regels:
- Gebruik ALLEEN imageSrc waarden uit de lijst hierboven.
- Houd alle coördinaten binnen 0..%d (x) en 0..%d (y).
- Gebruik dash: [] voor passen, [10,5] voor loopacties, [2,4] voor dribbels.
- Gebruik shirt_red_black voor team A, shirt_red_white voor team B, shirt_orange voor de keeper.
- Alle lijnen en zones zijn automatisch wit; geef GEEN stroke-kleur mee.
- Teken één kernopstelling. Vermijd meerdere losse vakken tenzij de bron dat expliciet toont.
- Gebruik rect spaarzaam (maximaal 1). Gebruik liever pionnen om vakhoeken te markeren.
- Kies field_type functioneel: square voor klein vak/rondo, landscape voor brede opstelling, portrait voor lengte-opstelling.

Beschikbare teamtaken: %s
Beschikbare doelstellingen: %s
Beschikbare voetbalhandelingen: %s%s
PROMPT;

        $prompt = sprintf(
            $template,
            $updateModeInstructions,
            $sourceFidelityInstructions,
            $this->encodeJson($options['team_task'], '[]'),
            $dims['width'],
            $dims['height'],
            $fieldType,
            $this->encodeJson($assetList, '[]'),
            $dims['width'],
            $dims['height'],
            $this->encodeJson($options['team_task'], '[]'),
            $this->encodeJson($options['objective'], '[]'),
            $this->encodeJson($options['football_action'], '[]'),
            $formStateSummary
        );

        if (!empty($sourceContext)) {
            $prompt .= "\n\n== SOURCE_CONTEXT_JSON ==\n";
            $prompt .= "Gebruik deze bronnen als context. Kopieer niet letterlijk. Motiveer keuzes kort in je vrije tekst.\n";
            $prompt .= $this->encodeJson($sourceContext, '[]');
        }

        return $prompt;
    }

    public function buildGenerationInstruction(array $source, ?array $formState, string $userMessage): string {
        $title = trim((string)($source['title'] ?? ''));
        $url = trim((string)($source['url'] ?? ''));
        $snippet = trim((string)($source['snippet'] ?? ''));
        $channel = trim((string)($source['channel'] ?? ''));
        $durationSeconds = (int)($source['duration_seconds'] ?? 0);
        $chapters = is_array($source['chapters'] ?? null) ? $source['chapters'] : [];
        $transcript = trim((string)($source['transcript_excerpt'] ?? ''));

        $lines = [];
        $lines[] = "Maak een complete, uitvoerbare oefening op basis van de volgende YouTube-video en het coachverzoek.";
        $lines[] = "";
        $lines[] = "== COACHVERZOEK ==";
        $lines[] = $userMessage;
        $lines[] = "";
        $lines[] = "== BRONVIDEO ==";
        $lines[] = "Titel: " . ($title !== '' ? $title : '(onbekend)');
        if ($channel !== '') {
            $lines[] = "Kanaal: " . $channel;
        }
        if ($url !== '') {
            $lines[] = "URL: " . $url;
        }
        if ($durationSeconds > 0) {
            $minutes = (int)floor($durationSeconds / 60);
            $seconds = $durationSeconds % 60;
            $lines[] = "Duur: " . sprintf('%d:%02d', $minutes, $seconds);
        }
        if ($snippet !== '') {
            $lines[] = "Beschrijving: " . $snippet;
        }

        if (!empty($chapters)) {
            $lines[] = "";
            $lines[] = "== VIDEOHOOFDSTUKKEN ==";
            foreach ($chapters as $ch) {
                $ts = trim((string)($ch['timestamp'] ?? ''));
                $label = trim((string)($ch['label'] ?? ''));
                if ($ts !== '' && $label !== '') {
                    $lines[] = "- " . $ts . " " . $label;
                }
            }
        }

        if ($transcript !== '') {
            $lines[] = "";
            $lines[] = "== TRANSCRIPT (FRAGMENT) ==";
            $lines[] = $transcript;
        }

        $lines[] = "";
        $lines[] = "== INSTRUCTIES ==";
        $lines[] = "- Gebruik de video als inspiratiebron, kopieer niet letterlijk.";
        $lines[] = "- Vul exercise_json.source met de video-URL.";
        $lines[] = "- Lever ALTIJD zowel exercise_json als drawing_json.";
        if (is_array($formState) && !empty(array_filter($formState, fn($v) => $v !== '' && $v !== null && $v !== []))) {
            $lines[] = "- Respecteer de huidige formulierwaarden (zie HUIDIGE FORMULIERSTATUS in de systeemprompt).";
        }
        $lines[] = "- Als de video onvoldoende detail biedt, vul aan met je eigen kennis en vermeld je aannames in coach_instructions.";

        return implode("\n", $lines);
    }

    public function buildSourceFactsPrompt(array $source, array $sourceEvidence, string $coachRequest): string {
        $title = trim((string)($source['title'] ?? ''));
        $url = trim((string)($source['url'] ?? ''));
        $channel = trim((string)($source['channel'] ?? ''));
        $snippet = trim((string)($source['snippet'] ?? ''));
        $transcript = trim((string)($source['transcript_excerpt'] ?? ''));
        $chapters = is_array($source['chapters'] ?? null) ? $source['chapters'] : [];
        $visualFacts = is_array($source['visual_facts'] ?? null) ? $source['visual_facts'] : null;
        $visualSourceLabel = trim((string)($source['visual_status'] ?? '')) === 'uploaded_screenshots_ok'
            ? 'uit coachscreenshots van de video'
            : 'uit videoframes';

        $lines = [];
        $lines[] = 'Je bent een video-analist voor voetbaltrainingen.';
        $lines[] = 'Doel: haal alleen de drill-feiten uit de bron die een coach direct zou herkennen na het zien van de video.';
        $lines[] = 'Gebruik ALLEEN feiten die expliciet blijken uit transcript, chapters, beschrijving of visuele analyse.';
        $lines[] = 'Verzin geen concrete organisatie, rotatie of regels als de bron dat niet ondersteunt.';
        $lines[] = 'Als tekstuele en visuele bronnen elkaar tegenspreken, vermeld beide met de herkomst.';
        $lines[] = '';
        $lines[] = 'Coachvraag: ' . $coachRequest;
        $lines[] = '';
        $lines[] = '== BRONVIDEO ==';
        $lines[] = 'Titel: ' . ($title !== '' ? $title : '(onbekend)');
        if ($channel !== '') {
            $lines[] = 'Kanaal: ' . $channel;
        }
        if ($url !== '') {
            $lines[] = 'URL: ' . $url;
        }
        if ($snippet !== '') {
            $lines[] = 'Beschrijving: ' . $snippet;
        }
        if (!empty($chapters)) {
            $lines[] = 'Chapters:';
            foreach ($chapters as $chapter) {
                $timestamp = trim((string)($chapter['timestamp'] ?? ''));
                $label = trim((string)($chapter['label'] ?? ''));
                if ($label !== '') {
                    $lines[] = '- ' . ($timestamp !== '' ? ($timestamp . ' ') : '') . $label;
                }
            }
        }
        if ($transcript !== '') {
            $lines[] = '';
            $lines[] = 'Transcriptfragment:';
            $lines[] = $transcript;
        }

        // Include visual facts when available
        if ($visualFacts !== null) {
            $lines[] = '';
            $lines[] = '== VISUELE ANALYSE ==';
            $lines[] = $this->encodeJson($visualFacts, '{}');
            $lines[] = '';
            $lines[] = 'De visuele analyse hierboven is afkomstig van ' . $visualSourceLabel . '.';
            $lines[] = 'Gebruik deze als aanvullende evidence naast transcript en beschrijving.';
            $lines[] = 'Vermeld bij evidence_items de herkomst: "transcript", "chapters", "description" of "visual".';
        }

        $lines[] = '';
        $lines[] = '== EVIDENCE ==';
        $lines[] = $this->encodeJson($sourceEvidence, '{}');
        $lines[] = '';
        $lines[] = 'Output ALLEEN als JSON met dit schema:';
        $lines[] = '{';
        $lines[] = '  "summary": "korte samenvatting van de trainingsvorm",';
        $lines[] = '  "setup": {';
        $lines[] = '    "starting_shape": "korte omschrijving van de startsituatie",';
        $lines[] = '    "player_structure": "aantallen / posities als dat aantoonbaar in de bron zit",';
        $lines[] = '    "area": "ruimte of vak als aantoonbaar",';
        $lines[] = '    "equipment": ["..."]';
        $lines[] = '  },';
        $lines[] = '  "sequence": ["stap 1", "stap 2", "stap 3"],';
        $lines[] = '  "rotation": "doorwissel / herstart / stoppunt",';
        $lines[] = '  "rules": ["concrete spelregels uit de bron"],';
        $lines[] = '  "coach_cues": ["coachpunten die direct bij deze vorm horen"],';
        $lines[] = '  "recognition_points": ["3-5 punten die de coach direct herkent na het zien van de video"],';
        $lines[] = '  "missing_details": ["welke details niet bewezen zijn door de bron"],';
        $lines[] = '  "confidence": "high|medium|low",';
        $lines[] = '  "evidence_items": [';
        $lines[] = '    {"fact": "...", "source": "transcript|chapters|description|visual", "snippet": "..."}';
        $lines[] = '  ]';
        $lines[] = '}';
        $lines[] = 'Regels: minimaal 3 recognition_points als de bron dat toelaat; gebruik missing_details voor onzekerheden.';
        if ($visualFacts !== null) {
            $lines[] = 'Neem visuele feiten op in evidence_items met source="visual" en hoge/lage certainty.';
        }

        return implode("\n", $lines);
    }

    public function buildSourceAnchoredGenerationInstruction(
        array $source,
        array $sourceFacts,
        array $sourceEvidence,
        string $coachRequest,
        ?array $formState,
        bool $isExistingExercise = false
    ): string {
        $lines = [];
        $lines[] = 'Maak een oefening die dezelfde trainingsvorm beschrijft als de gekozen video.';
        $lines[] = 'De coach moet na het zien van de video direct herkennen wat hij in de omschrijving leest.';
        $lines[] = '';
        $lines[] = '== COACHVERZOEK ==';
        $lines[] = $coachRequest;
        $lines[] = '';
        $lines[] = '== SOURCE_FACTS_JSON ==';
        $lines[] = $this->encodeJson($sourceFacts, '{}');
        $lines[] = '';
        $lines[] = '== SOURCE_EVIDENCE ==';
        $lines[] = $this->encodeJson($sourceEvidence, '{}');
        $lines[] = '';
        $lines[] = '== INSTRUCTIES ==';
        $lines[] = '- Beschrijf in description exact dezelfde startsituatie, volgorde en rotatie als in SOURCE_FACTS_JSON.';
        $lines[] = '- De eerste zin beschrijft de startsituatie. De tweede zin beschrijft het verloop. De derde zin beschrijft het stoppunt of de doorwissel.';
        $lines[] = '- Noem in description GEEN concrete details die niet in SOURCE_FACTS_JSON staan.';
        $lines[] = '- Als je iets moet aanvullen om de oefening werkbaar te maken, houd dat kort en praktisch in coach_instructions (zonder meta-uitleg).';
        $lines[] = '- recognition_points uit SOURCE_FACTS_JSON moeten duidelijk terugkomen in description en drawing_json.';
        $lines[] = '- drawing_json moet dezelfde opstelling ondersteunen als de bronfeiten.';
        $lines[] = '- Vul exercise_json.source met de video-URL.';
        $lines[] = '- Als SOURCE_FACTS_JSON een "contradictions" lijst bevat, benoem die niet in de beschrijving maar volg de meest betrouwbare bron.';
        $lines[] = '- Als SOURCE_FACTS_JSON een "visual_patterns" heeft, gebruik die om de tekening nauwkeuriger te maken.';
        if ($isExistingExercise) {
            $lines[] = '- Dit is een update van een bestaande oefening. Respecteer bestaande velden en lever alleen gewijzigde velden terug in exercise_json.';
        } else {
            $lines[] = '- Dit is een nieuwe generatie. Lever dus een VOLLEDIGE exercise_json en drawing_json.';
        }
        if ($this->hasMeaningfulFormState($formState)) {
            $lines[] = '- Gebruik de formulierstatus alleen als extra constraint, niet als reden om van de bron af te wijken.';
        }
        $lines[] = '- Als SOURCE_FACTS_JSON onzeker is over een detail, houd dat detail bewust algemeen.';
        $lines[] = '- Beschrijf geen andere oefenvorm dan de bronvideo.';
        $lines[] = '';
        $lines[] = '== BRONLINK ==';
        $lines[] = trim((string)($source['url'] ?? ''));

        return implode("\n", $lines);
    }

    public function buildConceptGenerationInstruction(
        array $source,
        array $sourceEvidence,
        string $coachRequest,
        ?array $formState,
        bool $isExistingExercise = false
    ): string {
        $title = trim((string)($source['title'] ?? ''));
        $url = trim((string)($source['url'] ?? ''));
        $channel = trim((string)($source['channel'] ?? ''));
        $snippet = trim((string)($source['snippet'] ?? ''));
        $transcript = trim((string)($source['transcript_excerpt'] ?? ''));
        $chapters = is_array($source['chapters'] ?? null) ? $source['chapters'] : [];

        $lines = [];
        $lines[] = 'Maak een conceptoefening op basis van beperkte broninformatie uit een YouTube-video.';
        $lines[] = 'Dit is GEEN exacte videovertaling. Gebruik de bron als richting en vul ontbrekende details alleen voorzichtig aan.';
        $lines[] = '';
        $lines[] = '== COACHVERZOEK EN EXTRA INPUT ==';
        $lines[] = $coachRequest;
        $lines[] = '';
        $lines[] = '== BRONVIDEO ==';
        $lines[] = 'Titel: ' . ($title !== '' ? $title : '(onbekend)');
        if ($channel !== '') {
            $lines[] = 'Kanaal: ' . $channel;
        }
        if ($url !== '') {
            $lines[] = 'URL: ' . $url;
        }
        if ($snippet !== '') {
            $lines[] = 'Beschrijving: ' . $snippet;
        }
        if (!empty($chapters)) {
            $lines[] = 'Chapters:';
            foreach ($chapters as $chapter) {
                $timestamp = trim((string)($chapter['timestamp'] ?? ''));
                $label = trim((string)($chapter['label'] ?? ''));
                if ($label !== '') {
                    $lines[] = '- ' . ($timestamp !== '' ? ($timestamp . ' ') : '') . $label;
                }
            }
        }
        if ($transcript !== '') {
            $lines[] = '';
            $lines[] = 'Transcriptfragment:';
            $lines[] = $transcript;
        }

        $lines[] = '';
        $lines[] = '== BRONZEKERHEID ==';
        $lines[] = $this->encodeJson($sourceEvidence, '{}');
        $lines[] = '';
        $lines[] = '== CONCEPTREGELS ==';
        $lines[] = '- Houd description praktisch, simpel en uitvoerbaar.';
        $lines[] = '- Noem in description geen concrete aantallen, posities, rotaties of regels tenzij de bron of coachinput daar genoeg steun voor geeft.';
        $lines[] = '- Als een detail onzeker is, houd het detail algemeen en voeg hooguit één korte controle-opmerking toe in coach_instructions.';
        $lines[] = '- Schrijf coach_instructions alsof je direct tegen een trainer praat op het veld; geen technische AI-terminologie.';
        $lines[] = '- Vul exercise_json.source met de video-URL.';
        if ($this->hasMeaningfulFormState($formState)) {
            $lines[] = '- Gebruik de formulierstatus en extra coachinput als harde constraints boven losse vermoedens uit de video.';
        }
        if ($isExistingExercise) {
            $lines[] = '- Dit is een update van een bestaande oefening; lever alleen gewijzigde velden in exercise_json terug.';
        } else {
            $lines[] = '- Dit is een nieuwe generatie; lever een volledige exercise_json en drawing_json terug.';
        }

        return implode("\n", $lines);
    }

    public function buildTextRefinementInstruction(string $coachRequest, ?array $formState): string
    {
        $lines = [];
        $lines[] = 'Pas alleen de TEKST van de bestaande oefening aan.';
        $lines[] = 'Lever alleen `exercise_json` terug met de velden die echt wijzigen.';
        $lines[] = 'Lever GEEN `drawing_json` terug.';
        $lines[] = '';
        $lines[] = '== WIJZIGINGSVERZOEK ==';
        $lines[] = $coachRequest;
        $lines[] = '';
        $lines[] = '== HUIDIGE OEFENING ==';
        $lines[] = $this->buildRefinementFormSnapshot($formState);
        $lines[] = '';
        $lines[] = 'Regels:';
        $lines[] = '- Houd niet-genoemde velden ongemoeid door ze niet terug te sturen.';
        $lines[] = '- Pas `field_type` alleen aan als de coach dat expliciet vraagt.';
        $lines[] = '- Behoud de bronlink in `exercise_json.source` als die al gevuld is.';

        return implode("\n", $lines);
    }

    public function buildDrawingRefinementInstruction(string $coachRequest, ?array $formState): string
    {
        $lines = [];
        $lines[] = 'Pas alleen de TEKENING van de bestaande oefening aan.';
        $lines[] = 'Lever alleen een volledige `drawing_json` terug.';
        $lines[] = 'Lever GEEN `exercise_json` terug.';
        $lines[] = '';
        $lines[] = '== WIJZIGINGSVERZOEK ==';
        $lines[] = $coachRequest;
        $lines[] = '';
        $lines[] = '== HUIDIGE OEFENING ==';
        $lines[] = $this->buildRefinementFormSnapshot($formState);
        $lines[] = '';
        $lines[] = 'Regels:';
        $lines[] = '- Houd de veldoriëntatie gelijk aan de bestaande oefening, tenzij expliciet anders gevraagd.';
        $lines[] = '- Lever een werkbare startsituatie met relevante spelers/materialen en looplijnen.';
        $lines[] = '- Als informatie ontbreekt, kies een eenvoudige en duidelijke opstelling.';

        return implode("\n", $lines);
    }

    public function buildAlignmentEvaluationPrompt(
        array $sourceFacts,
        ?array $textSuggestion,
        ?array $drawingSuggestion,
        string $coachRequest
    ): string {
        $drawingSnapshot = $this->buildDrawingEvaluationSnapshot($drawingSuggestion);
        $proposal = [
            'fields' => is_array($textSuggestion['fields'] ?? null) ? $textSuggestion['fields'] : [],
            'warnings' => is_array($textSuggestion['warnings'] ?? null) ? $textSuggestion['warnings'] : [],
            'drawing' => $drawingSnapshot,
        ];
        $sourceFactsJson = $this->encodeJson($sourceFacts, '{}');
        $proposalJson = $this->encodeJson($proposal, '{}');

        return <<<PROMPT
Je bent kwaliteitscontroleur voor video-naar-oefening vertalingen.
Beoordeel of de gegenereerde oefenomschrijving dezelfde trainingsvorm beschrijft als SOURCE_FACTS_JSON.

Coachvraag: {$coachRequest}

== SOURCE_FACTS_JSON ==
{$sourceFactsJson}

== VOORSTEL ==
{$proposalJson}

Beoordeel streng op:
1. source_alignment: herkent een coach na het zien van de video dezelfde startsituatie, volgorde en rotatie? Weeg zowel tekstuele als visuele evidence mee.
2. coach_request_fit: sluit het voorstel aan op de vraag van de coach zonder van de bron af te wijken?
3. organization_clarity: is de oefening praktisch en logisch uitgelegd?
4. drawing_alignment: ondersteunt de tekening dezelfde vorm als de bronfeiten (inclusief visuele patronen zoals veldvorm en pasrichtingen)?

Output ALLEEN als JSON:
{
  "overall_score": 4.3,
  "source_alignment": 5,
  "coach_request_fit": 4,
  "organization_clarity": 4,
  "drawing_alignment": 4,
  "verdict": "pass|revise|fail",
  "must_fix": ["..."],
  "summary": "..."
}

Regels:
- verdict=pass alleen als overall_score >= 4.2 EN source_alignment >= 4 EN organization_clarity >= 4.
- Als de startsituatie, volgorde of rotatie niet overeenkomen met SOURCE_FACTS_JSON, dan mag source_alignment niet hoger zijn dan 2.
- must_fix moet concreet benoemen wat inhoudelijk afwijkt van de bron.
- Als SOURCE_FACTS_JSON een "contradictions" lijst bevat, beoordeel dan of het voorstel de meest betrouwbare variant volgt.
- Weeg zowel tekstuele als visuele evidence_items mee bij de beoordeling van source_alignment.
- Als de drawing-data zichtbaar te druk of generiek is (veel herhaalde markers, weinig betekenisvolle structuur), geef drawing_alignment maximaal 2.
PROMPT;
    }

    /**
     * Reduce raw Konva JSON to a compact snapshot for alignment evaluation.
     */
    private function buildDrawingEvaluationSnapshot(?array $drawingSuggestion): ?array
    {
        if (!is_array($drawingSuggestion)) {
            return null;
        }

        $snapshot = [
            'field_type' => (string)($drawingSuggestion['field_type'] ?? ''),
            'node_count' => max(0, (int)($drawingSuggestion['node_count'] ?? 0)),
            'class_counts' => [
                'Image' => 0,
                'Arrow' => 0,
                'Rect' => 0,
                'Other' => 0,
            ],
            'asset_counts' => [],
            'dashed_arrows' => 0,
            'curved_arrows' => 0,
        ];

        $raw = trim((string)($drawingSuggestion['drawing_data'] ?? ''));
        if ($raw === '') {
            return $snapshot;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded['children'] ?? null)) {
            return $snapshot;
        }

        foreach ($decoded['children'] as $child) {
            if (!is_array($child)) {
                continue;
            }

            $className = trim((string)($child['className'] ?? ''));
            if (isset($snapshot['class_counts'][$className])) {
                $snapshot['class_counts'][$className]++;
            } else {
                $snapshot['class_counts']['Other']++;
            }

            $attrs = is_array($child['attrs'] ?? null) ? $child['attrs'] : [];
            if ($className === 'Image') {
                $src = trim((string)($attrs['imageSrc'] ?? ''));
                if ($src !== '') {
                    $snapshot['asset_counts'][$src] = (int)($snapshot['asset_counts'][$src] ?? 0) + 1;
                }
                continue;
            }

            if ($className !== 'Arrow') {
                continue;
            }

            $dash = $attrs['dash'] ?? [];
            if (is_array($dash) && !empty($dash)) {
                $snapshot['dashed_arrows']++;
            }
            if (array_key_exists('tension', $attrs)) {
                $snapshot['curved_arrows']++;
            }
        }

        if (!empty($snapshot['asset_counts'])) {
            arsort($snapshot['asset_counts']);
            $snapshot['asset_counts'] = array_slice($snapshot['asset_counts'], 0, 8, true);
        }

        return $snapshot;
    }

    public function buildRevisionInstruction(
        array $source,
        array $sourceFacts,
        array $evaluation,
        ?array $textSuggestion,
        ?array $drawingSuggestion,
        string $coachRequest,
        bool $isExistingExercise = false
    ): string {
        $proposal = [
            'fields' => is_array($textSuggestion['fields'] ?? null) ? $textSuggestion['fields'] : [],
            'drawing' => $drawingSuggestion === null ? null : [
                'field_type' => (string)($drawingSuggestion['field_type'] ?? ''),
                'node_count' => (int)($drawingSuggestion['node_count'] ?? 0),
            ],
        ];

        $lines = [];
        $lines[] = 'Herschrijf het voorstel zodat het strakker aansluit op de bronvideo.';
        $lines[] = 'Los alleen de inhoudelijke afwijkingen op; maak geen nieuwe oefenvorm.';
        $lines[] = '';
        $lines[] = '== COACHVERZOEK ==';
        $lines[] = $coachRequest;
        $lines[] = '';
        $lines[] = '== SOURCE_FACTS_JSON ==';
        $lines[] = $this->encodeJson($sourceFacts, '{}');
        $lines[] = '';
        $lines[] = '== HUIDIG VOORSTEL ==';
        $lines[] = $this->encodeJson($proposal, '{}');
        $lines[] = '';
        $lines[] = '== QUALITY_FEEDBACK ==';
        $lines[] = $this->encodeJson($evaluation, '{}');
        $lines[] = '';
        $lines[] = '== HERSTELREGELS ==';
        $lines[] = '- must_fix punten hebben prioriteit.';
        $lines[] = '- Hou de beschrijving herkenbaar voor iemand die de video net heeft gezien.';
        $lines[] = '- Voeg geen nieuwe concrete spelregels, aantallen of rotaties toe buiten SOURCE_FACTS_JSON.';
        $lines[] = '- coach_instructions moet concreet coachbaar zijn, zonder meta-uitleg over bronzekerheid of modelbeperkingen.';
        $lines[] = '- exercise_json.source moet de video-URL blijven.';
        $lines[] = '- Als SOURCE_FACTS_JSON contradictions bevat, volg de meest betrouwbare bron.';
        if ($isExistingExercise) {
            $lines[] = '- Dit blijft een update van een bestaande oefening: lever alleen gewijzigde velden in exercise_json terug.';
        } else {
            $lines[] = '- Lever opnieuw een volledige exercise_json en drawing_json.';
        }
        $lines[] = '';
        $lines[] = '== BRONLINK ==';
        $lines[] = trim((string)($source['url'] ?? ''));

        return implode("\n", $lines);
    }

    /**
     * Build a ranking prompt that asks the LLM to select and motivate the top 3-5 videos.
     */
    public function buildRankingPrompt(string $message, ?array $formState, array $candidates, array $chatHistory = []): string {
        $candidateLines = [];
        foreach ($candidates as $i => $video) {
            $title = (string)($video['title'] ?? '');
            $channel = (string)($video['channel'] ?? '');
            $durationSecs = (int)($video['duration_seconds'] ?? 0);
            $duration = $durationSecs > 0 ? sprintf('%d:%02d', intdiv($durationSecs, 60), $durationSecs % 60) : 'onbekend';
            $snippet = (string)($video['snippet'] ?? '');
            $chapters = is_array($video['chapters'] ?? null) ? $video['chapters'] : [];
            $publishedAt = (string)($video['published_at'] ?? '');
            $preflight = is_array($video['technical_preflight'] ?? null) ? $video['technical_preflight'] : [];
            $sourceEvidence = is_array($video['source_evidence_preview'] ?? null) ? $video['source_evidence_preview'] : [];

            $line = ($i + 1) . '. "' . $title . '" - ' . $channel . ' - ' . $duration;

            // Publication date
            if ($publishedAt !== '') {
                $year = substr($publishedAt, 0, 4);
                if ($year !== '' && $year !== '0000') {
                    $line .= ' - ' . $year;
                }
            }

            // Statistics
            $stats = [];
            $viewCount = (int)($video['view_count'] ?? 0);
            $likeCount = (int)($video['like_count'] ?? 0);
            $commentCount = (int)($video['comment_count'] ?? 0);
            if ($viewCount > 0) {
                $stats[] = $this->formatCount($viewCount) . ' views';
            }
            if ($likeCount > 0) {
                $stats[] = $this->formatCount($likeCount) . ' likes';
            }
            if ($commentCount > 0) {
                $stats[] = $this->formatCount($commentCount) . ' comments';
            }
            if ($viewCount > 0 && $likeCount > 0) {
                $likeRatio = round(($likeCount / $viewCount) * 100, 1);
                $stats[] = $likeRatio . '% like-ratio';
            }
            if (!empty($stats)) {
                $line .= ' (' . implode(', ', $stats) . ')';
            }

            // Category
            $categoryId = (int)($video['category_id'] ?? 0);
            if ($categoryId > 0 && $categoryId !== 17) {
                $line .= "\n   ⚠ Niet-sport categorie (id: " . $categoryId . ')';
            }

            if ($snippet !== '') {
                $line .= "\n   Beschrijving: " . $snippet;
            }
            if (!empty($chapters)) {
                $chapterLabels = [];
                foreach ($chapters as $ch) {
                    $ts = trim((string)($ch['timestamp'] ?? ''));
                    $label = trim((string)($ch['label'] ?? ''));
                    if ($label !== '') {
                        $chapterLabels[] = ($ts !== '' ? $ts . ' ' : '') . $label;
                    }
                }
                if (!empty($chapterLabels)) {
                    $line .= "\n   Chapters: " . implode(' | ', $chapterLabels);
                }
            }
            // Tags from uploader
            $tags = is_array($video['tags'] ?? null) ? $video['tags'] : [];
            if (!empty($tags)) {
                $line .= "\n   Tags: " . implode(', ', $tags);
            }
            // Transcript excerpt — actual spoken/shown content
            $transcript = trim((string)($video['transcript_excerpt'] ?? ''));
            if ($transcript !== '') {
                $line .= "\n   Transcript: " . $transcript;
            }
            if (!empty($preflight)) {
                $downloadable = $preflight['downloadable_via_ytdlp'] ?? null;
                $downloadableLabel = $downloadable === true ? 'ja' : ($downloadable === false ? 'nee' : 'onbekend');
                $authRequired = !empty($preflight['auth_required']) ? 'ja' : 'nee';
                $chapterCount = max(0, (int)($preflight['chapter_count'] ?? count($chapters)));
                $transcriptSource = trim((string)($preflight['transcript_source'] ?? $video['transcript_source'] ?? 'none'));
                $metadataOnly = !empty($preflight['metadata_only']) ? 'ja' : 'nee';
                $status = trim((string)($preflight['status'] ?? ''));

                $line .= "\n   Viability: downloadbaar via yt-dlp=" . $downloadableLabel
                    . ', auth_required=' . $authRequired
                    . ', chapter_count=' . $chapterCount
                    . ', transcript_source=' . ($transcriptSource !== '' ? $transcriptSource : 'none')
                    . ', metadata_only=' . $metadataOnly;
                if ($status !== '') {
                    $line .= ', status=' . $status;
                }
            }
            if (!empty($sourceEvidence)) {
                $line .= "\n   Evidence: level=" . trim((string)($sourceEvidence['level'] ?? 'low'))
                    . ', score=' . number_format((float)($sourceEvidence['score'] ?? 0.0), 2, '.', '')
                    . ', transcript_chars=' . max(0, (int)($sourceEvidence['transcript_chars'] ?? 0))
                    . ', snippet_chars=' . max(0, (int)($sourceEvidence['snippet_chars'] ?? 0));
                $warning = trim((string)($sourceEvidence['blocking_reasons'][0] ?? ''));
                if ($warning !== '') {
                    $line .= ', warning=' . $warning;
                }
            }
            $candidateLines[] = $line;
        }

        $formContext = '';
        if (is_array($formState)) {
            $parts = [];
            foreach (['team_task', 'objectives', 'actions'] as $key) {
                $val = $formState[$key] ?? null;
                if (is_array($val) && !empty($val)) {
                    $parts[] = $key . ': ' . implode(', ', $val);
                } elseif (is_string($val) && $val !== '') {
                    $parts[] = $key . ': ' . $val;
                }
            }
            if (!empty($parts)) {
                $formContext = "\nFormulier-context: " . implode('; ', $parts);
            }
        }

        $candidatesText = implode("\n\n", $candidateLines);

        $conversationContext = '';
        if (!empty($chatHistory)) {
            $historyLines = [];
            foreach ($chatHistory as $historyMsg) {
                $role = (string)($historyMsg['role'] ?? '');
                $content = trim((string)($historyMsg['content'] ?? ''));
                if (in_array($role, ['user', 'assistant'], true) && $content !== '') {
                    $label = $role === 'user' ? 'Coach' : 'AI';
                    $historyLines[] = $label . ': ' . $content;
                }
            }
            if (!empty($historyLines)) {
                $conversationContext = "\n\nGesprekshistorie:\n" . implode("\n", $historyLines);
            }
        }

        return <<<PROMPT
Je bent een expert voetbaltrainer die YouTube-video's beoordeelt voor gebruik als trainingsmateriaal.

De coach zoekt oefenstof. Analyseer de kandidaat-video's en selecteer de 3-5 beste.

Coach vraag: "{$message}"{$formContext}{$conversationContext}

--- KANDIDATEN ---
{$candidatesText}
--- EINDE KANDIDATEN ---

Beoordeel elke kandidaat op deze criteria (in volgorde van belang):

1. **Technische verwerkbaarheid**: Kandidaten met `downloadbaar via yt-dlp = nee` mogen GEEN aanbevolen topkeuze zijn. Als `auth_required = ja`, zie de video als risicovol en hooguit als fallback.
2. **Inhoudelijke match**: Past de oefening bij wat de coach zoekt? Gebruik de beschrijving, chapters EN transcript om te bepalen wat de video daadwerkelijk toont. Chapters geven de structuur, het transcript geeft de feitelijke inhoud.
3. **Evidence-readiness**: Verkies video's met chapters en/of echte captions boven `metadata_only = ja`. Zeer korte clips (< 20-30s) zijn zwakker.
4. **Leeftijd/niveau**: Sluit de oefening aan bij de genoemde leeftijdsgroep? Let op tags (U9, U12, youth, etc.) en of de beschrijving een leeftijd noemt.
5. **Kwaliteit**: Hoge like-ratio (likes/views) wijst op gewaardeerde content. Veel comments duidt op actieve coaching-community. Verkies kanalen die gespecialiseerd zijn in voetbaltraining.
6. **Relevantie-filter**: Negeer video's die GEEN concrete oefening/drill bevatten (wedstrijdhighlights, interviews, compilaties, vlogs). Een niet-sport categorie is een sterk negatief signaal.

Per geselecteerde video, geef een motivatie (2-3 zinnen, in het Nederlands) die de coach helpt kiezen. Benoem specifiek: welke oefening de video toont, waarom deze past bij de vraag, en eventuele sterke/zwakke punten.
Schrijf de motivatie coachgericht: inhoud en toepasbaarheid op het veld.
Gebruik GEEN technische termen zoals yt-dlp, metadata, transcript_source, auth_required, cookies of preflight.
Als je twijfel wilt noemen, gebruik alleen: "Bronzekerheid: laag/middel/hoog".

Output ALLEEN als JSON:
{
  "selections": [
    {"candidate_index": 1, "reason": "..."},
    ...
  ]
}
PROMPT;
    }

    private function truncateText(string $text, int $limit): string {
        if (strlen($text) <= $limit) {
            return $text;
        }
        return rtrim(substr($text, 0, $limit - 3)) . '...';
    }

    private function formatCount(int $count): string {
        if ($count >= 1_000_000) {
            return round($count / 1_000_000, 1) . 'M';
        }
        if ($count >= 1_000) {
            return round($count / 1_000, 1) . 'K';
        }
        return (string)$count;
    }

    public function buildSuggestionSummary(?array $textSuggestion, ?array $drawingSuggestion): string {
        $parts = [];
        if ($textSuggestion !== null && !empty($textSuggestion['fields'])) {
            $labels = [
                'title' => 'titel', 'description' => 'beschrijving', 'variation' => 'variatie',
                'coach_instructions' => 'coachinstructies', 'source' => 'bron',
                'team_task' => 'teamtaak', 'objectives' => 'doelstellingen',
                'actions' => 'voetbalhandelingen', 'min_players' => 'min. spelers',
                'max_players' => 'max. spelers', 'duration' => 'duur', 'field_type' => 'veldtype',
            ];
            $fieldNames = [];
            foreach (array_keys($textSuggestion['fields']) as $key) {
                $fieldNames[] = $labels[$key] ?? $key;
            }
            $parts[] = 'Ik heb dit ingevuld: ' . implode(', ', $fieldNames) . '.';
        }
        if ($drawingSuggestion !== null) {
            $parts[] = 'Ik heb ook de tekening aangepast.';
        }
        return !empty($parts) ? implode(' ', $parts) : 'Ik heb de oefening bijgewerkt.';
    }

    public function buildFormStateSummary(?array $formState): string {
        if (!$this->hasMeaningfulFormState($formState)) {
            return '';
        }

        $filled = [];
        $hasDrawing = !empty($formState['has_drawing']);
        foreach ($formState as $key => $value) {
            if ($key === 'has_drawing') {
                continue;
            }
            if ($key === 'field_type' && !$hasDrawing && !$this->hasMeaningfulNonDrawingState($formState)) {
                continue;
            }
            if (is_array($value)) {
                if (!empty($value)) {
                    $filled[] = $key . ': ' . implode(', ', $value);
                }
            } elseif ((string)$value !== '') {
                $filled[] = $key . ': ' . (string)$value;
            }
        }

        if (empty($filled) && !$hasDrawing) {
            return '';
        }
        $summary = "\n\n== HUIDIGE FORMULIERSTATUS ==\nDeze velden zijn momenteel ingevuld in het formulier:\n";
        $summary .= !empty($filled) ? implode("\n", $filled) : '(geen velden ingevuld)';
        $summary .= "\nTekening aanwezig: " . ($hasDrawing ? 'ja' : 'nee');
        return $summary;
    }

    public function hasMeaningfulFormState(?array $formState): bool {
        if (!is_array($formState)) {
            return false;
        }

        if (!empty($formState['has_drawing'])) {
            return true;
        }

        return $this->hasMeaningfulNonDrawingState($formState);
    }

    private function hasMeaningfulNonDrawingState(array $formState): bool {
        foreach ($formState as $key => $value) {
            if (in_array($key, ['has_drawing', 'field_type'], true)) {
                continue;
            }

            if (is_array($value)) {
                if (!empty($value)) {
                    return true;
                }
                continue;
            }

            if (trim((string)$value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function buildUpdateModeInstructions(bool $isExistingExercise): string {
        if ($isExistingExercise) {
            return implode("\n", [
                '- Er staat al een bestaande oefening in het formulier. Dit is dus een AANPASSING.',
                '- Lever bij een aanpassing ALLEEN de velden die wijzigen in exercise_json. Laat ongewijzigde velden weg.',
                '- Bij een aanpassing van de tekening lever je de VOLLEDIGE drawing_json, want tekeningen kunnen niet deels bijgewerkt worden.',
                '- Als de coach niet expliciet om een tekening-aanpassing vraagt, lever dan GEEN drawing_json.',
                '- Genereer geen compleet nieuwe oefening tenzij de coach dat expliciet vraagt.',
            ]);
        }

        return implode("\n", [
            '- Behandel deze request als een NIEUWE oefening, ook als er al standaardvelden zoals field_type gevuld zijn.',
            '- Gebruik eventuele formulierwaarden alleen als extra constraints of voorkeuren.',
            '- Lever daarom bij een nieuwe oefening ALTIJD een volledige exercise_json en drawing_json.',
            '- Gebruik partial updates alleen wanneer expliciet is aangegeven dat een bestaande oefening wordt aangepast.',
        ]);
    }

    private function buildSourceFidelityInstructions(): string {
        return implode("\n", [
            '- Als SOURCE_CONTEXT_JSON of SOURCE_FACTS_JSON aanwezig is, beschrijf dan dezelfde trainingsvorm als in die bron staat.',
            '- SOURCE_FACTS_JSON kan zowel tekstuele als visuele evidence bevatten met herkomst per feit (transcript, chapters, description, visual/frame).',
            '- De beschrijving moet herkenbaar zijn voor een coach die de video net heeft bekeken.',
            '- Noem in description geen concrete opstelling, spelersaantallen, rotaties of regels die niet door de bron worden ondersteund.',
            '- Als een detail onzeker is, houd description bewust algemeen en voeg hooguit één korte controle-opmerking toe in coach_instructions.',
            '- drawing_json moet dezelfde startsituatie ondersteunen als de beschrijving; gebruik visuele patronen (visual_patterns) voor nauwkeurigheid.',
            '- Als er contradictions tussen tekst en beeld zijn, volg de meest betrouwbare bron.',
        ]);
    }

    private function buildRefinementFormSnapshot(?array $formState): string
    {
        if (!is_array($formState)) {
            return '(geen formulierstatus beschikbaar)';
        }

        $snapshot = [
            'title' => trim((string)($formState['title'] ?? '')),
            'description' => trim((string)($formState['description'] ?? '')),
            'variation' => trim((string)($formState['variation'] ?? '')),
            'coach_instructions' => trim((string)($formState['coach_instructions'] ?? '')),
            'source' => trim((string)($formState['source'] ?? '')),
            'team_task' => trim((string)($formState['team_task'] ?? '')),
            'objectives' => is_array($formState['objectives'] ?? null) ? array_values($formState['objectives']) : [],
            'actions' => is_array($formState['actions'] ?? null) ? array_values($formState['actions']) : [],
            'min_players' => isset($formState['min_players']) ? (int)$formState['min_players'] : null,
            'max_players' => isset($formState['max_players']) ? (int)$formState['max_players'] : null,
            'duration' => isset($formState['duration']) ? (int)$formState['duration'] : null,
            'field_type' => trim((string)($formState['field_type'] ?? '')),
            'has_drawing' => !empty($formState['has_drawing']),
        ];

        return $this->encodeJson($snapshot, '{}');
    }

    private function safeConstraintInt(mixed $value, int $min, int $max): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_scalar($value) || !is_numeric((string)$value)) {
            return null;
        }

        $parsed = (int)$value;
        if ($parsed < $min || $parsed > $max) {
            return null;
        }

        return $parsed;
    }

    private function encodeJson(mixed $value, string $fallback): string {
        $encoded = json_encode($value, $this->jsonFlags());
        if ($encoded === false) {
            return $fallback;
        }

        return $encoded;
    }

    private function jsonFlags(): int {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        return $flags;
    }
}
