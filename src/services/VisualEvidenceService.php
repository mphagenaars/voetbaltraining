<?php
declare(strict_types=1);

/**
 * Analyses extracted video frames via a vision-capable LLM to produce structured
 * visual evidence (facts) that complement the textual evidence from transcripts/chapters.
 *
 * Output is a structured array of visual facts suitable for fusion with textual facts
 * in a later pipeline step (SourceEvidenceFusionService).
 */
class VisualEvidenceService {
    private OpenRouterClient $client;
    private AiPromptBuilder $promptBuilder;

    public function __construct(OpenRouterClient $client, AiPromptBuilder $promptBuilder) {
        $this->client = $client;
        $this->promptBuilder = $promptBuilder;
    }

    /**
     * Run vision analysis on extracted frames and return structured visual facts.
     *
     * @param array  $frames       Array from VideoFrameExtractor (each has 'data_uri', 'timestamp_formatted', 'timestamp')
     * @param array  $source       The enriched video source array (title, url, snippet, etc.)
     * @param string $coachRequest The original coach question
     * @param string $modelId      Vision-capable model ID
     * @param int    $userId       Current user ID
     * @return array{ok: bool, visual_facts?: array, usage?: array, error?: string}
     */
    public function analyseFrames(
        array $frames,
        array $source,
        string $coachRequest,
        string $modelId,
        int $userId
    ): array {
        if (empty($frames)) {
            return ['ok' => false, 'error' => 'Geen frames beschikbaar voor visuele analyse.'];
        }

        $prompt = $this->buildVisionAnalysisPrompt($source, $frames, $coachRequest);
        $imageUrls = $this->collectFrameDataUris($frames);

        $content = OpenRouterClient::buildImageContent($prompt, $imageUrls);

        $messages = [
            ['role' => 'user', 'content' => $content],
        ];

        $response = $this->client->visionCompletion($messages, $modelId, $userId);

        if (!$response['ok']) {
            return [
                'ok' => false,
                'error' => $response['error'] ?? 'Vision-analyse mislukt.',
                'usage' => $response['usage'] ?? null,
            ];
        }

        $rawContent = trim((string)($response['content'] ?? ''));
        $parsed = $this->parseVisualFacts($rawContent);

        if ($parsed === null) {
            return [
                'ok' => false,
                'error' => 'Vision-output kon niet worden geparsed als JSON.',
                'usage' => $response['usage'] ?? [],
                'raw_content' => $rawContent,
            ];
        }

        return [
            'ok' => true,
            'visual_facts' => $parsed,
            'usage' => $response['usage'] ?? [],
            'generation_id' => $response['generation_id'] ?? null,
        ];
    }

    /**
     * Build the vision analysis prompt.
     * The frames are sent as images; this prompt provides textual context and instructions.
     */
    private function buildVisionAnalysisPrompt(array $source, array $frames, string $coachRequest): string {
        $title = trim((string)($source['title'] ?? ''));
        $channel = trim((string)($source['channel'] ?? ''));
        $visualStatus = trim((string)($source['visual_status'] ?? ''));
        $frameDescriptor = $visualStatus === 'uploaded_screenshots_ready' || $visualStatus === 'uploaded_screenshots_ok'
            ? 'screenshots die een coach uit een YouTube-video heeft geüpload'
            : 'keyframes uit een YouTube-video';

        $lines = [];
        $lines[] = 'Je bent een visueel analist voor voetbaltrainingen.';
        $lines[] = 'Je krijgt ' . count($frames) . ' ' . $frameDescriptor . ' met een voetbaloefening.';
        $lines[] = '';
        $lines[] = 'Doel: beschrijf wat er feitelijk zichtbaar is in de frames.';
        $lines[] = 'Gebruik ALLEEN wat je in de beelden ziet. Verzin NIETS.';
        $lines[] = 'Als iets onduidelijk of niet zichtbaar is, zeg dat expliciet.';
        $lines[] = '';

        if ($title !== '') {
            $lines[] = 'Videotitel: ' . $title;
        }
        if ($channel !== '') {
            $lines[] = 'Kanaal: ' . $channel;
        }
        if ($coachRequest !== '') {
            $lines[] = 'Coachvraag (voor context): ' . $coachRequest;
        }

        $lines[] = '';
        $lines[] = '== FRAME-TIMESTAMPS ==';
        foreach ($frames as $i => $frame) {
            $ts = $frame['timestamp_formatted'] ?? $this->formatTimestamp((float)($frame['timestamp'] ?? 0));
            $lines[] = 'Frame ' . ($i + 1) . ': ' . $ts;
        }

        $lines[] = '';
        $lines[] = '== ANALYSE-INSTRUCTIES ==';
        $lines[] = 'Analyseer de frames en geef een JSON-object terug met dit schema:';
        $lines[] = '{';
        $lines[] = '  "setup": {';
        $lines[] = '    "starting_shape": "beschrijving van de startsituatie/opstelling die je ziet",';
        $lines[] = '    "field_shape": "vorm van het speelveld/vak (rechthoek, vierkant, driehoek, cirkel, L-vorm, etc.)",';
        $lines[] = '    "field_markings": "zichtbare markeringen (lijnen, zones, doelen, etc.)",';
        $lines[] = '    "estimated_dimensions": "geschatte afmetingen als afleidbaar, anders null",';
        $lines[] = '    "player_count": "aantal zichtbare spelers, of bereik als wisselend",';
        $lines[] = '    "player_roles": ["herkenbare rollen of kleuren/hesjes, bijv. aanvallers, verdedigers, keeper"],';
        $lines[] = '    "equipment": ["zichtbaar materiaal: pionnen, hekjes, doeltjes, ballen, hesjes, etc."]';
        $lines[] = '  },';
        $lines[] = '  "sequence": [';
        $lines[] = '    {';
        $lines[] = '      "frame": 1,';
        $lines[] = '      "timestamp": "MM:SS",';
        $lines[] = '      "action": "beschrijving van wat er op dit moment gebeurt",';
        $lines[] = '      "movement_patterns": "zichtbare loop- of paspatronen"';
        $lines[] = '    }';
        $lines[] = '  ],';
        $lines[] = '  "patterns": {';
        $lines[] = '    "passing_directions": "zichtbare pasrichtingen als herkenbaar",';
        $lines[] = '    "running_lines": "herkenbare looplijnen als zichtbaar",';
        $lines[] = '    "rotation_visible": "is er een doorwisseling of draaiing zichtbaar?"';
        $lines[] = '  },';
        $lines[] = '  "uncertainties": ["lijst van dingen die niet duidelijk zichtbaar zijn"],';
        $lines[] = '  "confidence": "high|medium|low",';
        $lines[] = '  "evidence_items": [';
        $lines[] = '    {"fact": "wat je ziet", "frame": 1, "certainty": "high|medium|low"}';
        $lines[] = '  ]';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = 'Regels:';
        $lines[] = '- Geef alleen feiten die je echt ziet, geen aannames.';
        $lines[] = '- Gebruik "uncertainties" voor alles dat je niet goed kunt zien.';
        $lines[] = '- Bij slechte beeldkwaliteit: lage confidence en eerlijke uncertainties.';
        $lines[] = '- Focus op informatie die relevant is voor het nauwkeurig beschrijven van een voetbaloefening.';
        $lines[] = '- Antwoord ALLEEN met de JSON, geen extra tekst.';

        return implode("\n", $lines);
    }

    /**
     * Collect data URIs from frame array, limited to avoid excessive token usage.
     * Cap at 12 frames to keep the vision call manageable.
     */
    private function collectFrameDataUris(array $frames): array {
        $uris = [];
        $maxFrames = min(count($frames), 12);
        for ($i = 0; $i < $maxFrames; $i++) {
            $uri = $frames[$i]['data_uri'] ?? null;
            if (is_string($uri) && $uri !== '') {
                $uris[] = $uri;
            }
        }
        return $uris;
    }

    /**
     * Parse the LLM vision response into a structured array.
     * Handles fenced JSON blocks (```json ... ```) and bare JSON.
     */
    private function parseVisualFacts(string $raw): ?array {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $candidates = [$raw];
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?\s*```/s', $raw, $m)) {
            $candidates[] = trim($m[1]);
        }

        foreach ($candidates as $candidate) {
            foreach ($this->buildJsonParseCandidates($candidate) as $variant) {
                $decoded = json_decode($variant, true);
                if (is_array($decoded)) {
                    return $this->normalizeVisualFacts($decoded);
                }
            }
        }

        return null;
    }

    private function buildJsonParseCandidates(string $raw): array {
        $variants = [];
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return [];
        }

        $variants[] = $trimmed;

        if (preg_match('/\{.*\}/s', $trimmed, $m) === 1) {
            $variants[] = trim($m[0]);
        }

        $cleaned = preg_replace('/,\s*([}\]])/', '$1', $trimmed) ?? $trimmed;
        $variants[] = trim($cleaned);

        if (preg_match('/\{.*\}/s', $cleaned, $m) === 1) {
            $variants[] = trim($m[0]);
        }

        return array_values(array_unique(array_filter($variants, static fn(string $item): bool => $item !== '')));
    }

    /**
     * Normalize and validate the structure of parsed visual facts.
     */
    private function normalizeVisualFacts(array $facts): array {
        $normalized = [
            'setup' => $this->normalizeSetup($facts['setup'] ?? []),
            'sequence' => $this->normalizeSequence($facts['sequence'] ?? []),
            'patterns' => $this->normalizePatterns($facts['patterns'] ?? []),
            'uncertainties' => $this->normalizeStringList($facts['uncertainties'] ?? []),
            'confidence' => $this->normalizeConfidence($facts['confidence'] ?? 'low'),
            'evidence_items' => $this->normalizeEvidenceItems($facts['evidence_items'] ?? []),
        ];

        return $normalized;
    }

    private function normalizeSetup(mixed $setup): array {
        if (!is_array($setup)) {
            return ['starting_shape' => null, 'field_shape' => null, 'field_markings' => null, 'estimated_dimensions' => null, 'player_count' => null, 'player_roles' => [], 'equipment' => []];
        }

        return [
            'starting_shape' => is_string($setup['starting_shape'] ?? null) ? trim($setup['starting_shape']) : null,
            'field_shape' => is_string($setup['field_shape'] ?? null) ? trim($setup['field_shape']) : null,
            'field_markings' => is_string($setup['field_markings'] ?? null) ? trim($setup['field_markings']) : null,
            'estimated_dimensions' => is_string($setup['estimated_dimensions'] ?? null) ? trim($setup['estimated_dimensions']) : null,
            'player_count' => is_string($setup['player_count'] ?? null) || is_int($setup['player_count'] ?? null)
                ? (string)$setup['player_count']
                : null,
            'player_roles' => $this->normalizeStringList($setup['player_roles'] ?? []),
            'equipment' => $this->normalizeStringList($setup['equipment'] ?? []),
        ];
    }

    private function normalizeSequence(mixed $sequence): array {
        if (!is_array($sequence)) {
            return [];
        }

        $result = [];
        foreach ($sequence as $entry) {
            if (!is_array($entry)) continue;
            $result[] = [
                'frame' => is_int($entry['frame'] ?? null) ? $entry['frame'] : null,
                'timestamp' => is_string($entry['timestamp'] ?? null) ? trim($entry['timestamp']) : null,
                'action' => is_string($entry['action'] ?? null) ? trim($entry['action']) : '',
                'movement_patterns' => is_string($entry['movement_patterns'] ?? null) ? trim($entry['movement_patterns']) : null,
            ];
        }

        return $result;
    }

    private function normalizePatterns(mixed $patterns): array {
        if (!is_array($patterns)) {
            return ['passing_directions' => null, 'running_lines' => null, 'rotation_visible' => null];
        }

        return [
            'passing_directions' => is_string($patterns['passing_directions'] ?? null) ? trim($patterns['passing_directions']) : null,
            'running_lines' => is_string($patterns['running_lines'] ?? null) ? trim($patterns['running_lines']) : null,
            'rotation_visible' => is_string($patterns['rotation_visible'] ?? null) ? trim($patterns['rotation_visible']) : null,
        ];
    }

    private function normalizeEvidenceItems(mixed $items): array {
        if (!is_array($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $fact = is_string($item['fact'] ?? null) ? trim($item['fact']) : '';
            if ($fact === '') continue;

            $result[] = [
                'fact' => $fact,
                'frame' => is_int($item['frame'] ?? null) ? $item['frame'] : null,
                'certainty' => $this->normalizeConfidence($item['certainty'] ?? 'medium'),
            ];
        }

        return $result;
    }

    private function normalizeStringList(mixed $list): array {
        if (!is_array($list)) {
            return [];
        }

        $result = [];
        foreach ($list as $item) {
            if (is_string($item) && trim($item) !== '') {
                $result[] = trim($item);
            }
        }
        return $result;
    }

    private function normalizeConfidence(mixed $value): string {
        if (is_string($value)) {
            $value = strtolower(trim($value));
            if (in_array($value, ['high', 'medium', 'low'], true)) {
                return $value;
            }
        }
        return 'low';
    }

    private function formatTimestamp(float $seconds): string {
        $mins = (int)floor($seconds / 60);
        $secs = (int)floor($seconds) % 60;
        return sprintf('%02d:%02d', $mins, $secs);
    }
}
