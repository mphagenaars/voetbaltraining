<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/Session.php';

spl_autoload_register(function (string $class): void {
    $class = str_replace('\\', '/', $class);
    $base = __DIR__ . '/../src/';

    $paths = [
        $base . $class . '.php',
        $base . 'models/' . $class . '.php',
        $base . 'controllers/' . $class . '.php',
        $base . 'services/' . $class . '.php',
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

function assertTrue(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertSame(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' (expected: ' . var_export($expected, true) . ', actual: ' . var_export($actual, true) . ')');
    }
}

class FakeYouTubeSearchClient extends YouTubeSearchClient {
    public array $searchCalls = [];
    public array $videoCalls = [];
    public array $searchItems = [];
    public array $videoItems = [];
    public bool $forceSearchError = false;
    public bool $forceVideoError = false;

    public function searchVideos(string $apiKey, string $query, int $maxResults, ?string $relevanceLanguage = null): array {
        $this->searchCalls[] = [
            'query' => $query,
            'max_results' => $maxResults,
            'language' => $relevanceLanguage,
        ];

        if ($this->forceSearchError) {
            return [
                'ok' => false,
                'error' => 'quota exceeded',
                'http_status' => 403,
            ];
        }

        return [
            'ok' => true,
            'items' => array_slice($this->searchItems, 0, max(1, $maxResults)),
            'http_status' => 200,
        ];
    }

    public function getVideoById(string $apiKey, string $videoId): array {
        $this->videoCalls[] = [
            'video_id' => $videoId,
        ];

        if ($this->forceVideoError) {
            return [
                'ok' => false,
                'error' => 'video unavailable',
                'http_status' => 404,
            ];
        }

        if (!isset($this->videoItems[$videoId]) || !is_array($this->videoItems[$videoId])) {
            return [
                'ok' => false,
                'error' => 'video unavailable',
                'http_status' => 404,
            ];
        }

        return [
            'ok' => true,
            'item' => $this->videoItems[$videoId],
            'http_status' => 200,
        ];
    }
}

class TestableAiRetrievalService extends AiRetrievalService {
    public function __construct(
        PDO $pdo,
        ?YouTubeSearchClient $youtubeClient = null,
        ?OpenRouterClient $openRouterClient = null,
        ?VideoFrameExtractor $frameExtractor = null,
        private ?string $testApiKey = 'test-api-key'
    ) {
        parent::__construct($pdo, $youtubeClient, $openRouterClient, $frameExtractor);
    }

    protected function resolveYouTubeApiKey(string $encrypted): ?string {
        return $this->testApiKey;
    }
}

class FakeVideoFrameExtractor extends VideoFrameExtractor {
    public array $probeCalls = [];
    public array $probeResponses = [];

    public function __construct() {}

    public function probeAvailability(string $videoId, ?string $cookiesPath = null): array {
        $this->probeCalls[] = [
            'video_id' => $videoId,
            'cookies_path' => $cookiesPath,
        ];

        if (isset($this->probeResponses[$videoId]) && is_array($this->probeResponses[$videoId])) {
            return $this->probeResponses[$videoId];
        }

        return [
            'checked' => true,
            'downloadable_via_ytdlp' => true,
            'auth_required' => false,
            'error_code' => null,
            'error' => '',
            'duration_seconds' => 0,
            'used_cookies' => false,
        ];
    }
}

class StubbedProcessVideoFrameExtractor extends VideoFrameExtractor {
    public array $queuedResults = [];
    public array $commands = [];
    public $afterRunProcess = null;

    public function __construct() {
        parent::__construct('/tmp/vfe_stub_' . bin2hex(random_bytes(4)));
    }

    protected function findExecutable(string $name): string {
        return '/usr/bin/' . $name;
    }

    protected function runProcess(array $cmd, int $timeoutSeconds): array {
        $this->commands[] = [
            'cmd' => $cmd,
            'timeout' => $timeoutSeconds,
        ];

        if (empty($this->queuedResults)) {
            return ['ok' => false, 'error' => 'no queued result'];
        }

        $result = array_shift($this->queuedResults);
        if (is_callable($this->afterRunProcess)) {
            ($this->afterRunProcess)($cmd, $timeoutSeconds, $result);
        }

        return $result;
    }
}

class FakeChatOpenRouterClient extends OpenRouterClient {
    public array $responses = [];
    public array $messagesLog = [];

    public function __construct() {}

    public function chatCompletion(array $messages, string $modelId, int $userId): array {
        $this->messagesLog[] = [
            'messages' => $messages,
            'model_id' => $modelId,
            'user_id' => $userId,
        ];

        if (empty($this->responses)) {
            return ['ok' => false, 'error' => 'no fake response set'];
        }

        return array_shift($this->responses);
    }
}

class FakeSearchPhaseRetrievalService extends AiRetrievalService {
    public array $fakeSearchResult = [];

    public function __construct(PDO $pdo) {
        parent::__construct($pdo);
    }

    public function searchVideos(
        string $message,
        ?array $formState,
        array $settings,
        int $userId,
        string $modelId,
        array $chatHistory = []
    ): array {
        return $this->fakeSearchResult;
    }
}

class FakeEvaluationRetrievalService extends AiRetrievalService {
    public array $directResponses = [];

    public function __construct(PDO $pdo) {
        parent::__construct($pdo);
    }

    public function fetchDirectVideo(string $videoId, array $settings, bool $forceRefresh = false): array {
        return $this->directResponses[$videoId] ?? [
            'ok' => false,
            'error' => 'missing fake direct response',
            'code' => 'missing_fake_response',
        ];
    }
}

class FakeEvaluationWorkflowService extends AiWorkflowService {
    public array $technicalByVideoId = [];
    public array $evidenceByVideoId = [];

    public function __construct(PDO $pdo) {
        parent::__construct($pdo);
    }

    public function assessTechnicalViability(array $source): array {
        $videoId = trim((string)($source['external_id'] ?? ''));
        return $this->technicalByVideoId[$videoId] ?? [];
    }

    public function assessSourceEvidence(array $source, array $settings = []): array {
        $videoId = trim((string)($source['external_id'] ?? ''));
        return $this->evidenceByVideoId[$videoId] ?? [];
    }
}

function createRetrievalPdo(): PDO {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec(
        'CREATE TABLE exercises (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            team_id INTEGER NULL,
            title TEXT,
            description TEXT,
            team_task TEXT,
            training_objective TEXT,
            football_action TEXT,
            min_players INTEGER,
            max_players INTEGER,
            duration INTEGER,
            created_at TEXT
        )'
    );

    $pdo->exec(
        "INSERT INTO exercises
            (id, team_id, title, description, team_task, training_objective, football_action, min_players, max_players, duration, created_at)
         VALUES
            (1, NULL, 'Interne Opbouw Rondo', 'Opbouw onder druk, passing in kleine ruimte.', 'Opbouw', '[\"Opbouw\"]', '[\"Passen\"]', 6, 10, 20, '2026-03-10 10:00:00'),
            (2, NULL, 'Interne Afwerkvorm', 'Afwerken na combinaties met hoge intensiteit.', 'Aanval', '[\"Afwerken\"]', '[\"Schieten\"]', 8, 14, 25, '2026-03-10 11:00:00')"
    );

    $pdo->exec(
        'CREATE TABLE ai_source_cache (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            provider TEXT NOT NULL,
            external_id TEXT NOT NULL,
            title TEXT NOT NULL,
            url TEXT NOT NULL,
            channel_or_author TEXT NULL,
            duration_seconds INTEGER NULL,
            language TEXT NULL,
            snippet TEXT NULL,
            metadata_json TEXT NULL,
            fetched_at TEXT NOT NULL,
            expires_at TEXT NOT NULL
        )'
    );
    $pdo->exec('CREATE UNIQUE INDEX idx_ai_source_cache_provider_external ON ai_source_cache(provider, external_id)');
    $pdo->exec('CREATE INDEX idx_ai_source_cache_expires_at ON ai_source_cache(expires_at)');

    $pdo->exec(
        'CREATE TABLE exercise_options (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category TEXT NOT NULL,
            name TEXT NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0
        )'
    );
    $pdo->exec(
        "INSERT INTO exercise_options (category, name, sort_order) VALUES
            ('team_task', 'Opbouw', 1),
            ('objective', 'Drukzetten', 1),
            ('football_action', 'Passen', 1)"
    );

    return $pdo;
}

function createAiControllerTestPdo(): PDO {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec(
        'CREATE TABLE app_settings (
            key TEXT PRIMARY KEY,
            value TEXT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE ai_chat_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id INTEGER NOT NULL,
            role TEXT NOT NULL,
            content TEXT NOT NULL,
            model_id TEXT NULL,
            metadata_json TEXT NULL,
            created_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE ai_quality_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NULL,
            team_id INTEGER NULL,
            session_id INTEGER NULL,
            event_type TEXT NOT NULL,
            status TEXT NOT NULL,
            external_id TEXT NULL,
            payload_json TEXT NULL,
            created_at TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE ai_models (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            model_id TEXT NOT NULL,
            label TEXT NOT NULL,
            enabled INTEGER NOT NULL DEFAULT 1,
            supports_vision INTEGER NOT NULL DEFAULT 0,
            sort_order INTEGER NOT NULL DEFAULT 0
        )'
    );
    $pdo->exec(
        'CREATE TABLE ai_model_pricing (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            model_id TEXT NOT NULL,
            currency TEXT NOT NULL DEFAULT "EUR",
            input_price_per_mtoken REAL NOT NULL DEFAULT 0,
            output_price_per_mtoken REAL NOT NULL DEFAULT 0,
            request_flat_price REAL NOT NULL DEFAULT 0,
            min_request_price REAL NOT NULL DEFAULT 0,
            is_active INTEGER NOT NULL DEFAULT 1
        )'
    );
    $pdo->exec(
        "INSERT INTO ai_models (model_id, label, enabled, supports_vision, sort_order)
         VALUES ('openai/gpt-4o', 'GPT-4o', 1, 1, 1)"
    );
    $pdo->exec(
        "INSERT INTO ai_model_pricing (
            model_id, currency, input_price_per_mtoken, output_price_per_mtoken, request_flat_price, min_request_price, is_active
         ) VALUES ('openai/gpt-4o', 'EUR', 0.1, 0.1, 0, 0, 1)"
    );

    return $pdo;
}

function createAiAdminTestPdo(): PDO {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec(
        'CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE ai_quality_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NULL,
            team_id INTEGER NULL,
            session_id INTEGER NULL,
            event_type TEXT NOT NULL,
            status TEXT NOT NULL,
            external_id TEXT NULL,
            payload_json TEXT NULL,
            created_at TEXT NOT NULL
        )'
    );

    return $pdo;
}

function retrievalSettings(array $overrides = []): array {
    return array_merge([
        'ai_retrieval_enabled' => '1',
        'ai_retrieval_youtube_enabled' => '1',
        'youtube_api_key_enc' => 'ignored-in-tests',
        'ai_retrieval_max_candidates' => '10',
        'ai_retrieval_min_youtube_sources' => '2',
        'ai_retrieval_internal_limit' => '2',
        'ai_ytdlp_cookies_path' => '',
        'ai_retrieval_min_option_score' => '0.28',
    ], $overrides);
}

$tests = [];

$tests['AiPricingEngine berekent billable correct'] = function (): void {
    $engine = new AiPricingEngine();
    $result = $engine->calculate([
        'currency' => 'EUR',
        'input_price_per_mtoken' => 2.0,
        'output_price_per_mtoken' => 4.0,
        'request_flat_price' => 0.1,
        'min_request_price' => 0.0,
    ], 1000, 500);

    // 0.1 + (1000/1M*2) + (500/1M*4) = 0.104
    assertSame(0.104, $result['raw_billable_eur'], 'Raw billable mismatch');
    assertSame(0.104, $result['billable_cost_eur'], 'Billable mismatch');
};

$tests['AiPricingEngine respecteert min_request_price'] = function (): void {
    $engine = new AiPricingEngine();
    $result = $engine->calculate([
        'currency' => 'EUR',
        'input_price_per_mtoken' => 0.1,
        'output_price_per_mtoken' => 0.2,
        'request_flat_price' => 0.0,
        'min_request_price' => 0.05,
    ], 10, 10);

    assertSame(0.05, $result['billable_cost_eur'], 'Min request price not applied');
};

$tests['AiStructuredOutputParser parse fenced blocks'] = function (): void {
    $parser = new AiStructuredOutputParser();
    $response = "Tekst buiten blok\n```exercise_json\n{\"title\":\"Test\",\"description\":\"D\",\"objectives\":[],\"actions\":[],\"min_players\":1,\"max_players\":2,\"duration\":10,\"field_type\":\"portrait\"}\n```\n```drawing_json\n[]\n```";

    $parsed = $parser->parse($response);
    assertTrue(is_array($parsed['exercise_raw']), 'exercise_raw should be array');
    assertTrue(is_array($parsed['drawing_raw']), 'drawing_raw should be array');
    assertSame('Tekst buiten blok', $parsed['chat_text'], 'chat_text mismatch');
};

$tests['AiExerciseOutputValidator fuzzy en normalisatie'] = function (): void {
    $validator = new AiExerciseOutputValidator();
    $validated = $validator->validate([
        'title' => str_repeat('A', 120),
        'description' => 'Beschrijving',
        'team_task' => 'Opbouw',
        'objectives' => ['drukzetten', 'onbekend'],
        'actions' => ['passen'],
        'min_players' => 40,
        'max_players' => 1,
        'duration' => 37,
        'field_type' => 'unknown',
    ], [
        'team_task' => ['Opbouw van achteruit'],
        'objective' => ['Drukzetten'],
        'football_action' => ['Passen'],
    ]);

    assertSame(100, strlen($validated['fields']['title']), 'Title should be truncated');
    assertSame(1, $validated['fields']['min_players'], 'Min players should be swapped/clamped');
    assertSame(30, $validated['fields']['max_players'], 'Max players should be swapped/clamped');
    assertSame(35, $validated['fields']['duration'], 'Duration should be rounded to nearest 5');
    assertSame('portrait', $validated['fields']['field_type'], 'Invalid field type should default to portrait');
    assertTrue(!empty($validated['warnings']), 'Warnings expected for fuzzy/invalid values');
};

$tests['KonvaSanitizer whitelisted assets en clamps'] = function (): void {
    $sanitizer = new KonvaSanitizer();
    $result = $sanitizer->sanitize([
        ['type' => 'image', 'x' => 999, 'y' => -5, 'imageSrc' => '/images/assets/ball.svg'],
        ['type' => 'image', 'x' => 10, 'y' => 10, 'imageSrc' => '/images/assets/not_allowed.svg'],
        ['type' => 'arrow', 'points' => [0, 0, 700, 700], 'stroke' => '#fff', 'dash' => [10, 5], 'strokeWidth' => 2],
        ['type' => 'rect', 'x' => 50, 'y' => 50, 'width' => 100, 'height' => 80, 'stroke' => '#ffffff'],
    ], 'landscape');

    assertSame('landscape', $result['field_type'], 'Field type mismatch');
    assertSame(6, $result['node_count'], 'Rect should be converted into cone markers');

    $layer = $result['layer'];
    assertTrue(is_array($layer['children']), 'Layer children should be array');
    assertTrue(count($layer['children']) === 6, 'Expected 6 children in layer');

    $classNames = array_map(
        static fn(array $child): string => (string)($child['className'] ?? ''),
        $layer['children']
    );
    assertTrue(!in_array('Rect', $classNames, true), 'Rect nodes should be rewritten as cone markers');

    $firstImage = $layer['children'][0]['attrs'] ?? [];
    assertSame(588.0, (float)($firstImage['x'] ?? -1), 'Image x should stay fully inside field width');
    assertSame(12.0, (float)($firstImage['y'] ?? -1), 'Image y should stay fully inside field height');
};

$tests['KonvaSanitizer bouwt fallback starttekening uit oefenvelden'] = function (): void {
    $sanitizer = new KonvaSanitizer();
    $result = $sanitizer->buildFallbackDrawing([
        'title' => '1-tegen-1 naar kleine doeltjes',
        'description' => 'Dribbel, passeer je tegenstander en scoor in een klein doeltje.',
        'min_players' => 4,
        'max_players' => 8,
        'field_type' => 'square',
        'actions' => ['Dribbelen', 'Passen'],
    ], 'square');

    assertSame('square', $result['field_type'], 'Fallback should respect requested field type');
    assertSame(true, $result['fallback_generated'] ?? false, 'Fallback flag should be set');
    assertTrue(($result['node_count'] ?? 0) >= 10, 'Fallback should create a visible schematic setup');

    $goalCount = 0;
    foreach (($result['layer']['children'] ?? []) as $child) {
        $imageSrc = (string)($child['attrs']['imageSrc'] ?? '');
        if ($imageSrc === '/images/assets/goal.svg') {
            $goalCount++;
        }
    }
    assertSame(2, $goalCount, 'Goal-oriented fallback should include two mini goals');
};

$tests['AiPromptBuilder negeert default field_type als enige formulierstatus'] = function (): void {
    $pdo = createRetrievalPdo();
    $builder = new AiPromptBuilder($pdo);

    $summary = $builder->buildFormStateSummary([
        'field_type' => 'square',
        'has_drawing' => false,
    ]);

    assertSame('', $summary, 'Alleen default field_type mag geen update-context opleveren');
    assertTrue($builder->hasMeaningfulFormState([
        'field_type' => 'square',
        'has_drawing' => false,
    ]) === false, 'Default field_type alone should not count as meaningful state');
};

$tests['AiWorkflowService buildTranslatabilityRating is coachvriendelijk gekalibreerd'] = function (): void {
    $pdo = createRetrievalPdo();
    $service = new AiWorkflowService($pdo);

    $r1 = $service->buildTranslatabilityRating(['score' => 0.12, 'is_sufficient' => false]);
    assertSame(1, $r1['rating'] ?? null, 'Very weak evidence should stay 1 star');

    $r2 = $service->buildTranslatabilityRating(['score' => 0.30, 'is_sufficient' => false]);
    assertSame(2, $r2['rating'] ?? null, 'Weak but usable evidence should be 2 stars');

    $r3 = $service->buildTranslatabilityRating(['score' => 0.50, 'is_sufficient' => false]);
    assertSame(3, $r3['rating'] ?? null, 'Unsufficient sources should cap at 3 stars');

    $r4 = $service->buildTranslatabilityRating(['score' => 0.62, 'is_sufficient' => true]);
    assertSame(4, $r4['rating'] ?? null, 'Sufficient medium-good sources should get 4 stars');

    $r5 = $service->buildTranslatabilityRating(['score' => 0.81, 'is_sufficient' => true]);
    assertSame(5, $r5['rating'] ?? null, 'Strong sufficient sources should reach 5 stars');
};

$tests['AiPromptBuilder buildSourceFactsPrompt bevat visuele analyse als beschikbaar'] = function (): void {
    $pdo = createRetrievalPdo();
    $builder = new AiPromptBuilder($pdo);

    // Without visual facts
    $promptNoVisual = $builder->buildSourceFactsPrompt(
        [
            'title' => 'Rondo drill',
            'url' => 'https://youtube.com/watch?v=abc',
            'snippet' => 'Een rondo oefening.',
            'transcript_excerpt' => 'We gaan een rondo spelen.',
            'chapters' => [],
        ],
        ['score' => 0.65, 'level' => 'medium'],
        'Geef me een rondo'
    );

    assertTrue(!str_contains($promptNoVisual, 'VISUELE ANALYSE'), 'Without visual facts: should not contain VISUELE ANALYSE section');
    assertTrue(str_contains($promptNoVisual, 'video-analist'), 'Should contain role description');

    // With visual facts
    $promptWithVisual = $builder->buildSourceFactsPrompt(
        [
            'title' => 'Rondo drill',
            'url' => 'https://youtube.com/watch?v=abc',
            'snippet' => 'Een rondo oefening.',
            'transcript_excerpt' => 'We gaan een rondo spelen.',
            'chapters' => [],
            'visual_facts' => [
                'setup' => ['starting_shape' => 'vierkant', 'player_count' => '6'],
                'confidence' => 'medium',
            ],
        ],
        ['score' => 0.75, 'level' => 'high'],
        'Geef me een rondo'
    );

    assertTrue(str_contains($promptWithVisual, 'VISUELE ANALYSE'), 'With visual facts: should contain VISUELE ANALYSE section');
    assertTrue(str_contains($promptWithVisual, 'vierkant'), 'Should include visual setup data');
    assertTrue(str_contains($promptWithVisual, '"source": "transcript|chapters|description|visual"'), 'Evidence schema should include visual source');
};

$tests['AiPromptBuilder generatie-instructies refereren visuele patronen en contradictions'] = function (): void {
    $pdo = createRetrievalPdo();
    $builder = new AiPromptBuilder($pdo);

    $instruction = $builder->buildSourceAnchoredGenerationInstruction(
        ['url' => 'https://youtube.com/watch?v=abc'],
        [
            'summary' => 'Rondo 4v2',
            'setup' => ['starting_shape' => 'vierkant'],
            'sequence' => [['description' => 'Passing', 'source' => 'transcript']],
            'contradictions' => [['field' => 'equipment', 'description' => 'Materiaal verschilt']],
            'visual_patterns' => ['passing_directions' => 'horizontaal'],
        ],
        ['score' => 0.70],
        'Rondo oefening',
        null
    );

    assertTrue(str_contains($instruction, 'contradictions'), 'Should reference contradictions handling');
    assertTrue(str_contains($instruction, 'visual_patterns'), 'Should reference visual_patterns for drawing');
};

$tests['AiPromptBuilder buildConceptGenerationInstruction houdt conceptmodus coachgericht'] = function (): void {
    $pdo = createRetrievalPdo();
    $builder = new AiPromptBuilder($pdo);

    $prompt = $builder->buildConceptGenerationInstruction(
        [
            'title' => 'Passing drill',
            'url' => 'https://youtube.com/watch?v=abc123',
            'snippet' => 'Korte passingvorm met drie stations.',
            'transcript_excerpt' => '',
            'chapters' => [],
        ],
        [
            'score' => 0.22,
            'level' => 'low',
            'transcript_source' => 'metadata_fallback',
        ],
        'JO11, 10 spelers, focus op passen en vrijlopen',
        [
            'min_players' => 10,
            'team_task' => 'Opbouw',
        ],
        false
    );

    assertTrue(str_contains($prompt, 'conceptoefening'), 'Prompt should explicitly say conceptoefening');
    assertTrue(str_contains($prompt, 'één korte controle-opmerking'), 'Prompt should keep uncertainty handling short and practical');
    assertTrue(str_contains($prompt, 'technische AI-terminologie'), 'Prompt should enforce coach-facing language');
};

$tests['AiWorkflowService blokkeert metadata-only evidence'] = function (): void {
    $pdo = createRetrievalPdo();
    $service = new AiWorkflowService($pdo);

    $profile = $service->assessSourceEvidence([
        'transcript_source' => 'metadata_fallback',
        'transcript_excerpt' => 'Videofocus: generic drill',
        'snippet' => 'Korte beschrijving zonder concrete drillstappen.',
        'chapters' => [],
        'duration_seconds' => 420,
    ], [
        'ai_source_min_evidence_score' => '0.55',
    ]);

    assertTrue($profile['is_sufficient'] === false, 'Metadata-only evidence should be blocked');
    assertSame('low', $profile['level'], 'Expected low evidence level');
    assertTrue(!empty($profile['blocking_reasons']), 'Blocking reasons expected');
};

$tests['AiWorkflowService accepteert captions met chapters als voldoende evidence'] = function (): void {
    $pdo = createRetrievalPdo();
    $service = new AiWorkflowService($pdo);

    $profile = $service->assessSourceEvidence([
        'transcript_source' => 'captions',
        'transcript_excerpt' => str_repeat('Speler opent naar de zijkant en kaatst terug. ', 40),
        'snippet' => 'Passing drill met duidelijke organisatie en rotatie.',
        'chapters' => [
            ['timestamp' => '0:00', 'label' => 'Setup'],
            ['timestamp' => '0:30', 'label' => 'Passing pattern'],
            ['timestamp' => '1:10', 'label' => 'Rotation'],
        ],
        'duration_seconds' => 480,
    ], [
        'ai_source_min_evidence_score' => '0.55',
    ]);

    assertTrue($profile['is_sufficient'] === true, 'Captions plus chapters should be sufficient');
    assertTrue(in_array($profile['level'], ['high', 'medium'], true), 'Evidence level should be medium or high');
    assertTrue($profile['score'] >= 0.55, 'Evidence score should meet threshold');
};

$tests['AiWorkflowService behandelt rijke metadata-fallback met chapters als bruikbare bron'] = function (): void {
    $pdo = createRetrievalPdo();
    $service = new AiWorkflowService($pdo);

    $profile = $service->assessSourceEvidence([
        'title' => '5 Best Soccer Drills for U8 & U9',
        'transcript_source' => 'metadata_fallback',
        'transcript_excerpt' => '',
        'snippet' => str_repeat('Looking for fun, effective soccer drills for your U8 or U9 team with clear setup, rotation and coaching details. ', 8),
        'chapters' => [
            ['timestamp' => '0:00', 'label' => 'Intro'],
            ['timestamp' => '0:08', 'label' => 'Touch the Post Shooting'],
            ['timestamp' => '1:25', 'label' => 'Switching Tag'],
            ['timestamp' => '3:01', 'label' => 'First touch 1v1'],
            ['timestamp' => '4:41', 'label' => 'Shooting to 1v1 to 2v1'],
            ['timestamp' => '6:26', 'label' => '3v3 Funino'],
        ],
        'duration_seconds' => 420,
    ], [
        'ai_source_min_evidence_score' => '0.55',
    ]);

    assertTrue($profile['transcript_chars'] >= 220, 'Metadata fallback should synthesize a substantial transcript excerpt');
    assertTrue($profile['is_sufficient'] === true, 'Rich metadata plus chapters should be sufficient');
    assertTrue($profile['score'] >= 0.55, 'Score should cross the evidence threshold');
};

$tests['AiWorkflowService nuanceert warning voor chapter-rijke metadata-bron'] = function (): void {
    $pdo = createRetrievalPdo();
    $service = new AiWorkflowService($pdo);

    $display = $service->describeSourceEvidence([
        'is_sufficient' => false,
        'level' => 'low',
        'transcript_source' => 'metadata_fallback',
        'chapter_count' => 6,
        'transcript_chars' => 640,
        'duration_seconds' => 420,
        'blocking_reasons' => [
            'Transcript is te kort voor betrouwbare drillherkenning.',
        ],
    ]);

    assertTrue(str_contains($display['warning'], 'check zelf nog even'), 'Warning should invite manual checking');
    assertTrue(!str_contains($display['warning'], 'Transcript is te kort'), 'Warning should not blindly repeat the first blocker');
};

$tests['AiWorkflowService verduidelijkt metadata-only blokkade in warning'] = function (): void {
    $pdo = createRetrievalPdo();
    $service = new AiWorkflowService($pdo);

    // Without visual analysis
    $warning = $service->buildSourceEvidenceWarning([
        'transcript_source' => 'metadata_fallback',
        'chapter_count' => 0,
        'visual_confidence' => 'none',
        'blocking_reasons' => [
            'De beschikbare bron steunt te veel op beschrijving in plaats van echte video-evidence.',
        ],
    ]);

    assertTrue(str_contains($warning, 'titel en beschrijving'), 'Warning should explain that only title and description are available');
    assertTrue(str_contains($warning, 'beeldcheck is nu niet beschikbaar'), 'Warning should explain that visual checking is unavailable');

    // With visual analysis that was insufficient
    $warningWithVisual = $service->buildSourceEvidenceWarning([
        'transcript_source' => 'metadata_fallback',
        'chapter_count' => 0,
        'visual_confidence' => 'low',
        'blocking_reasons' => [
            'De beschikbare bron steunt te veel op beschrijving in plaats van echte video-evidence.',
        ],
    ]);

    assertTrue(str_contains($warningWithVisual, 'beeld blijft het nog te onduidelijk'), 'Warning should mention insufficient visual clarity');
};

$tests['AiWorkflowService noemt ontbrekende vision-config expliciet in warning'] = function (): void {
    $pdo = createRetrievalPdo();
    $service = new AiWorkflowService($pdo);

    $warning = $service->buildSourceEvidenceWarning([
        'transcript_source' => 'metadata_fallback',
        'chapter_count' => 0,
        'visual_status' => 'disabled_no_model',
        'visual_confidence' => 'none',
        'blocking_reasons' => [
            'De beschikbare bron steunt te veel op beschrijving in plaats van echte video-evidence.',
        ],
    ]);

    assertTrue(str_contains($warning, 'beeldcheck is nu niet beschikbaar'), 'Warning should explain that visual checking is unavailable');
};

$tests['AiWorkflowService noemt video-onbeschikbaarheid expliciet bij frame-extractie'] = function (): void {
    $pdo = createRetrievalPdo();
    $service = new AiWorkflowService($pdo);

    $warning = $service->buildSourceEvidenceWarning([
        'transcript_source' => 'metadata_fallback',
        'chapter_count' => 0,
        'visual_status' => 'frame_extraction_failed',
        'visual_error' => 'Video downloaden mislukt: ERROR: [youtube] EDJKPs2Qcag: This video is not available',
        'visual_confidence' => 'none',
        'blocking_reasons' => [
            'De beschikbare bron steunt te veel op beschrijving in plaats van echte video-evidence.',
        ],
    ]);

    assertTrue(str_contains($warning, 'beelden van deze video niet goed openen'), 'Warning should explain current video unavailability');
};

$tests['AiWorkflowService visuele signalen verhogen evidence score'] = function (): void {
    $pdo = createRetrievalPdo();
    $service = new AiWorkflowService($pdo);

    // Baseline: text-only score
    $textOnly = $service->assessSourceEvidence([
        'transcript_source' => 'captions',
        'transcript_excerpt' => str_repeat('Pass naar de zijkant. ', 35),
        'snippet' => 'Passing drill.',
        'chapters' => [['timestamp' => '0:00', 'label' => 'Setup']],
        'duration_seconds' => 300,
    ]);

    // Same source but with visual evidence
    $withVisual = $service->assessSourceEvidence([
        'transcript_source' => 'captions',
        'transcript_excerpt' => str_repeat('Pass naar de zijkant. ', 35),
        'snippet' => 'Passing drill.',
        'chapters' => [['timestamp' => '0:00', 'label' => 'Setup']],
        'duration_seconds' => 300,
        'visual_frame_count' => 8,
        'visual_confidence' => 'high',
        'visual_facts' => [
            'setup' => ['starting_shape' => 'vierkant met spelers op de hoeken'],
            'sequence' => [
                ['frame' => 1, 'action' => 'Opstelling'],
                ['frame' => 2, 'action' => 'Passing'],
                ['frame' => 3, 'action' => 'Rotatie'],
            ],
        ],
    ]);

    assertTrue($withVisual['score'] > $textOnly['score'], 'Visual signals should increase score');
    assertTrue($withVisual['visual_frame_count'] === 8, 'Should report visual frame count');
    assertTrue($withVisual['visual_setup_detected'] === true, 'Should detect visual setup');
    assertTrue($withVisual['visual_sequence_detected'] === true, 'Should detect visual sequence');
    assertSame('high', $withVisual['visual_confidence'], 'Should report visual confidence');
};

$tests['AiWorkflowService sterke visuele evidence heft tekstblokkade op'] = function (): void {
    $pdo = createRetrievalPdo();
    $service = new AiWorkflowService($pdo);

    // Without visual: metadata_fallback + no chapters = blocked
    $blocked = $service->assessSourceEvidence([
        'transcript_source' => 'metadata_fallback',
        'transcript_excerpt' => 'Korte tekst.',
        'snippet' => 'Korte snippet.',
        'chapters' => [],
        'duration_seconds' => 300,
    ], ['ai_source_min_evidence_score' => '0.20']);

    assertSame(false, $blocked['is_sufficient'], 'Without visual: should be blocked');
    assertTrue(!empty($blocked['blocking_reasons']), 'Without visual: should have blocking reasons');

    // With strong visual: same text but good visual evidence lifts the block
    $unblocked = $service->assessSourceEvidence([
        'transcript_source' => 'metadata_fallback',
        'transcript_excerpt' => 'Korte tekst.',
        'snippet' => 'Korte snippet.',
        'chapters' => [],
        'duration_seconds' => 300,
        'visual_frame_count' => 10,
        'visual_confidence' => 'medium',
        'visual_facts' => [
            'setup' => ['starting_shape' => 'rondo in vierkant vak'],
            'sequence' => [
                ['frame' => 1, 'action' => 'Opstelling'],
                ['frame' => 2, 'action' => 'Passing actie'],
            ],
        ],
    ], ['ai_source_min_evidence_score' => '0.20']);

    assertSame(true, $unblocked['is_sufficient'], 'With strong visual: block should be lifted');

    // Blocking reasons that control is_sufficient should not be present
    $decisionBlocks = array_filter($unblocked['blocking_reasons'], function ($r) {
        return str_contains($r, 'te veel op beschrijving')
            || str_contains($r, 'geen captions of hoofdstukken')
            || str_contains($r, 'te weinig concrete evidence');
    });
    assertTrue(empty($decisionBlocks), 'With strong visual: decision-blocking reasons should be lifted');
};

$tests['AiWorkflowService coachscreenshots kunnen metadata-bron voldoende maken'] = function (): void {
    $pdo = createRetrievalPdo();
    $service = new AiWorkflowService($pdo);

    $profile = $service->assessSourceEvidence([
        'transcript_source' => 'metadata_fallback',
        'transcript_excerpt' => 'Korte omschrijving.',
        'snippet' => str_repeat('Passing drill met duidelijke opbouw en rotatie. ', 6),
        'chapters' => [],
        'duration_seconds' => 360,
        'visual_status' => 'uploaded_screenshots_ok',
        'visual_frame_count' => 4,
        'visual_confidence' => 'medium',
        'visual_facts' => [
            'setup' => ['starting_shape' => 'driehoek met inspeelstation'],
            'sequence' => [
                ['frame' => 1, 'action' => 'Startsituatie'],
                ['frame' => 2, 'action' => 'Pass en doorbewegen'],
            ],
        ],
    ], [
        'ai_source_min_evidence_score' => '0.55',
    ]);

    assertSame(true, $profile['is_sufficient'], 'Uploaded screenshots should be able to lift a metadata source over the threshold');
    assertTrue($profile['score'] >= 0.55, 'Screenshot-assisted evidence should meet the minimum score');
};

$tests['AiRetrievalService gebruikt broncache voor directe video'] = function (): void {
    $pdo = createRetrievalPdo();
    $youtube = new FakeYouTubeSearchClient();
    $extractor = new FakeVideoFrameExtractor();
    $youtube->videoItems['cachevid001'] = [
        'external_id' => 'cachevid001',
        'title' => 'Passing drill 4v4',
        'snippet' => 'Oefening 4v4 met pressing.',
        'channel' => 'CoachCache',
        'published_at' => '2025-01-01T00:00:00Z',
        'url' => 'https://www.youtube.com/watch?v=cachevid001',
        'duration_seconds' => 540,
        'first_chapter' => 'Passing drill',
        'chapters' => [],
        'transcript_excerpt' => '',
        'transcript_source' => 'captions',
    ];
    $extractor->probeResponses['cachevid001'] = [
        'checked' => true,
        'downloadable_via_ytdlp' => true,
        'auth_required' => false,
        'error_code' => null,
        'error' => '',
        'duration_seconds' => 540,
        'used_cookies' => false,
    ];

    $service = new TestableAiRetrievalService($pdo, $youtube, null, $extractor);
    $settings = retrievalSettings();

    $first = $service->fetchDirectVideo('cachevid001', $settings);
    assertTrue($first['ok'] === true, 'First direct-video retrieval should succeed');
    assertSame(1, count($youtube->videoCalls), 'Direct video should be fetched once');
    assertSame('captions', (string)($first['source']['transcript_source'] ?? ''), 'Transcript source should be preserved');
    assertSame(true, $first['source']['technical_preflight']['downloadable_via_ytdlp'] ?? null, 'Preflight should be attached to cached source');
    assertSame(1, count($extractor->probeCalls), 'Preflight should run on first fetch');

    $youtube->forceVideoError = true;
    $second = $service->fetchDirectVideo('cachevid001', $settings);
    assertTrue($second['ok'] === true, 'Second direct-video retrieval should succeed from cache');
    assertSame(1, count($youtube->videoCalls), 'Second call should hit cache and skip API');
    assertSame(1, count($extractor->probeCalls), 'Second call should reuse cached preflight');
};

$tests['AiRetrievalService kan directe video-preflight geforceerd opnieuw controleren'] = function (): void {
    $pdo = createRetrievalPdo();
    $youtube = new FakeYouTubeSearchClient();
    $extractor = new FakeVideoFrameExtractor();
    $youtube->videoItems['forceretry01'] = [
        'external_id' => 'forceretry01',
        'title' => 'Retry drill',
        'snippet' => 'Oefening voor force refresh.',
        'channel' => 'Coach Retry',
        'published_at' => '2025-01-01T00:00:00Z',
        'url' => 'https://www.youtube.com/watch?v=forceretry01',
        'duration_seconds' => 300,
        'first_chapter' => 'Setup',
        'chapters' => [],
        'transcript_excerpt' => '',
        'transcript_source' => 'captions',
    ];
    $extractor->probeResponses['forceretry01'] = [
        'checked' => true,
        'downloadable_via_ytdlp' => false,
        'auth_required' => false,
        'error_code' => 'unavailable',
        'error' => 'Video is unavailable',
        'duration_seconds' => 300,
        'used_cookies' => false,
    ];

    $service = new TestableAiRetrievalService($pdo, $youtube, null, $extractor);
    $settings = retrievalSettings();

    $first = $service->fetchDirectVideo('forceretry01', $settings);
    assertTrue($first['ok'] === true, 'First direct-video retrieval should succeed');
    assertSame(false, $first['source']['technical_preflight']['downloadable_via_ytdlp'] ?? null, 'Initial preflight should be unavailable');
    assertSame(1, count($extractor->probeCalls), 'Initial call should probe availability once');

    $extractor->probeResponses['forceretry01'] = [
        'checked' => true,
        'downloadable_via_ytdlp' => true,
        'auth_required' => false,
        'error_code' => null,
        'error' => '',
        'duration_seconds' => 300,
        'used_cookies' => false,
    ];

    $retry = $service->fetchDirectVideo('forceretry01', $settings, true);
    assertTrue($retry['ok'] === true, 'Force-refresh retry should still return source');
    assertSame(true, $retry['source']['technical_preflight']['downloadable_via_ytdlp'] ?? null, 'Forced retry should overwrite stale preflight status');
    assertSame('ok', $retry['source']['technical_preflight']['status'] ?? '', 'Forced retry should mark preflight as ok');
    assertSame(2, count($extractor->probeCalls), 'Forced retry should run availability probe again immediately');
    assertSame(1, count($youtube->videoCalls), 'Forced retry should keep using cached source metadata');
};

$tests['AiRetrievalService ververst incomplete zoekcache bij directe video'] = function (): void {
    $pdo = createRetrievalPdo();
    $youtube = new FakeYouTubeSearchClient();
    $extractor = new FakeVideoFrameExtractor();

    $stmt = $pdo->prepare(
        "INSERT INTO ai_source_cache (provider, external_id, title, url, snippet, metadata_json, fetched_at, expires_at)
         VALUES (:provider, :external_id, :title, :url, :snippet, :metadata_json, CURRENT_TIMESTAMP, datetime('now', '+7 days'))"
    );
    $stmt->execute([
        ':provider' => 'youtube',
        ':external_id' => 'preview001',
        ':title' => 'Zoekpreview video',
        ':url' => 'https://www.youtube.com/watch?v=preview001',
        ':snippet' => 'Korte zoekpreview.',
        ':metadata_json' => json_encode([
            'title' => 'Zoekpreview video',
            'url' => 'https://www.youtube.com/watch?v=preview001',
            'snippet' => 'Korte zoekpreview.',
            'channel' => 'Coach Preview',
            'duration_seconds' => 240,
            'chapters' => [],
            'transcript_excerpt' => '',
            'transcript_source' => 'metadata_fallback',
            'cache_scope' => 'search_preview',
            'technical_preflight' => [
                'checked' => false,
                'downloadable_via_ytdlp' => null,
                'auth_required' => false,
                'status' => '',
                'error_code' => '',
                'error' => '',
                'duration_seconds' => 240,
                'chapter_count' => 0,
                'transcript_source' => 'metadata_fallback',
                'metadata_only' => true,
                'preflight_checked_at' => '',
                'used_cookies' => false,
                'attempts' => [],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $youtube->videoItems['preview001'] = [
        'external_id' => 'preview001',
        'title' => 'Volledige video',
        'snippet' => 'Uitgewerkte oefening met captions.',
        'channel' => 'Coach Full',
        'published_at' => '2025-01-01T00:00:00Z',
        'url' => 'https://www.youtube.com/watch?v=preview001',
        'duration_seconds' => 420,
        'first_chapter' => 'Setup',
        'chapters' => [['timestamp' => '0:00', 'label' => 'Setup']],
        'transcript_excerpt' => 'Pass, open draaien, kaats, doorstart.',
        'transcript_source' => 'captions',
    ];
    $extractor->probeResponses['preview001'] = [
        'checked' => true,
        'downloadable_via_ytdlp' => true,
        'auth_required' => false,
        'error_code' => null,
        'error' => '',
        'duration_seconds' => 420,
        'used_cookies' => false,
    ];

    $service = new TestableAiRetrievalService($pdo, $youtube, null, $extractor);
    $result = $service->fetchDirectVideo('preview001', retrievalSettings());

    assertTrue($result['ok'] === true, 'Direct video retrieval should succeed');
    assertSame(1, count($youtube->videoCalls), 'Incomplete search cache should be refreshed via direct video API');
    assertSame('captions', (string)($result['source']['transcript_source'] ?? ''), 'Refreshed source should use full transcript evidence');
    assertSame('full_video', (string)($result['source']['cache_scope'] ?? ''), 'Refreshed source should be stored as full video cache');
};

$tests['AiRetrievalService bewaart eerdere succesvolle preflight bij tijdelijke network_error refresh'] = function (): void {
    $pdo = createRetrievalPdo();
    $youtube = new FakeYouTubeSearchClient();
    $extractor = new FakeVideoFrameExtractor();

    $stmt = $pdo->prepare(
        "INSERT INTO ai_source_cache (provider, external_id, title, url, snippet, metadata_json, fetched_at, expires_at)
         VALUES (:provider, :external_id, :title, :url, :snippet, :metadata_json, CURRENT_TIMESTAMP, datetime('now', '+7 days'))"
    );
    $stmt->execute([
        ':provider' => 'youtube',
        ':external_id' => 'cacheok001',
        ':title' => 'Stable public drill',
        ':url' => 'https://www.youtube.com/watch?v=cacheok001',
        ':snippet' => 'Passing drill',
        ':metadata_json' => json_encode([
            'title' => 'Stable public drill',
            'url' => 'https://www.youtube.com/watch?v=cacheok001',
            'snippet' => 'Passing drill',
            'channel' => 'Coach Stable',
            'duration_seconds' => 360,
            'chapters' => [['timestamp' => '0:00', 'label' => 'Setup']],
            'transcript_excerpt' => 'Pass, kaats, doorstart.',
            'transcript_source' => 'captions',
            'technical_preflight' => [
                'checked' => true,
                'downloadable_via_ytdlp' => true,
                'auth_required' => false,
                'status' => 'ok',
                'error_code' => '',
                'error' => '',
                'duration_seconds' => 360,
                'chapter_count' => 1,
                'transcript_source' => 'captions',
                'metadata_only' => false,
                'preflight_checked_at' => '2026-03-14T00:00:00Z',
                'used_cookies' => false,
                'attempts' => [],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $extractor->probeResponses['cacheok001'] = [
        'checked' => true,
        'downloadable_via_ytdlp' => null,
        'auth_required' => false,
        'error_code' => 'network_error',
        'error' => 'Temporary failure in name resolution',
        'duration_seconds' => 0,
        'used_cookies' => false,
        'attempts' => [
            [
                'stage' => 'probe',
                'mode' => 'anonymous',
                'attempted' => true,
                'used_cookies' => false,
                'ok' => false,
                'auth_required' => false,
                'error_code' => 'network_error',
                'error' => 'Temporary failure in name resolution',
                'duration_seconds' => 0,
            ],
        ],
    ];

    $service = new TestableAiRetrievalService($pdo, $youtube, null, $extractor);
    $result = $service->fetchDirectVideo('cacheok001', retrievalSettings());

    assertTrue($result['ok'] === true, 'Cached direct video should still load');
    assertSame(true, $result['source']['technical_preflight']['downloadable_via_ytdlp'] ?? null, 'Successful cached preflight should be preserved');
    assertSame('ok', $result['source']['technical_preflight']['status'] ?? '', 'Successful cached status should be preserved');
    assertSame(1, count($result['source']['technical_preflight']['attempts'] ?? []), 'Network refresh attempt should still be recorded');
};

$tests['AiRetrievalService bewaart source_evidence metadata in cache'] = function (): void {
    $pdo = createRetrievalPdo();
    $extractor = new FakeVideoFrameExtractor();
    $service = new TestableAiRetrievalService($pdo, new FakeYouTubeSearchClient(), null, $extractor);

    $service->persistSourceCache([
        'provider' => 'youtube',
        'external_id' => 'cacheevidence1',
        'title' => 'Evidence cache video',
        'url' => 'https://www.youtube.com/watch?v=cacheevidence1',
        'snippet' => 'Passing drill met drie stations.',
        'channel' => 'Coach Cache',
        'duration_seconds' => 240,
        'chapters' => [['timestamp' => '0:00', 'label' => 'Setup']],
        'transcript_excerpt' => 'Pass, kaats en doorstart in drie stations.',
        'transcript_source' => 'captions',
        'technical_preflight' => [
            'checked' => true,
            'downloadable_via_ytdlp' => true,
            'auth_required' => false,
            'status' => 'ok',
            'duration_seconds' => 240,
            'chapter_count' => 1,
            'transcript_source' => 'captions',
            'metadata_only' => false,
            'preflight_checked_at' => gmdate('c'),
        ],
        'source_evidence' => [
            'score' => 0.66,
            'level' => 'medium',
            'is_sufficient' => true,
            'transcript_source' => 'captions',
            'transcript_chars' => 420,
            'chapter_count' => 1,
            'snippet_chars' => 110,
            'signals' => ['Volledig caption-fragment beschikbaar.'],
            'blocking_reasons' => [],
        ],
    ]);

    $result = $service->fetchDirectVideo('cacheevidence1', retrievalSettings());

    assertTrue($result['ok'] === true, 'Cached evidence video should load');
    assertSame(0.66, $result['source']['source_evidence']['score'] ?? null, 'Cached evidence score should round-trip');
    assertSame('medium', $result['source']['source_evidence']['level'] ?? '', 'Cached evidence level should round-trip');
    assertSame(420, $result['source']['source_evidence']['transcript_chars'] ?? null, 'Cached transcript_chars should round-trip');
};

$tests['AiRetrievalService gebruikt geen segment-scoped direct-video cache'] = function (): void {
    $pdo = createRetrievalPdo();
    $youtube = new FakeYouTubeSearchClient();
    $extractor = new FakeVideoFrameExtractor();
    $service = new TestableAiRetrievalService($pdo, $youtube, null, $extractor);

    // Corrupted cache shape caused by previous segment-scoped persistence.
    $service->persistSourceCache([
        'provider' => 'youtube',
        'external_id' => 'segcache01',
        'title' => '5 Best Soccer Drills',
        'url' => 'https://www.youtube.com/watch?v=segcache01',
        'snippet' => "Gekozen videodeel: Switching-Tag\nHoofdstuk: 1:25 Switching-Tag",
        'channel' => 'Coach Cache',
        'duration_seconds' => 480,
        'chapters' => [
            ['timestamp' => '1:25', 'seconds' => 85, 'label' => 'Switching-Tag'],
        ],
        'transcript_excerpt' => '',
        'transcript_source' => 'metadata_fallback',
        'cache_scope' => 'full_video',
        'technical_preflight' => [
            'checked' => true,
            'downloadable_via_ytdlp' => true,
            'status' => 'ok',
            'duration_seconds' => 480,
            'chapter_count' => 1,
            'transcript_source' => 'metadata_fallback',
            'metadata_only' => false,
            'preflight_checked_at' => gmdate('c'),
        ],
    ]);

    $youtube->videoItems['segcache01'] = [
        'external_id' => 'segcache01',
        'title' => '5 Best Soccer Drills',
        'url' => 'https://www.youtube.com/watch?v=segcache01',
        'snippet' => 'Volledige video met meerdere drills.',
        'channel' => 'Coach Channel',
        'duration_seconds' => 480,
        'chapters' => [
            ['timestamp' => '0:08', 'seconds' => 8, 'label' => 'Touch the Post Shooting'],
            ['timestamp' => '1:25', 'seconds' => 85, 'label' => 'Switching-Tag'],
            ['timestamp' => '3:05', 'seconds' => 185, 'label' => '1v1 Duel'],
        ],
        'transcript_excerpt' => '',
        'transcript_source' => 'metadata_fallback',
    ];

    $result = $service->fetchDirectVideo('segcache01', retrievalSettings());

    assertTrue($result['ok'] === true, 'Direct fetch should succeed');
    assertSame(1, count($youtube->videoCalls), 'Segment-scoped cache should trigger fresh API fetch');
    assertSame(3, count($result['source']['chapters'] ?? []), 'Fresh source should restore full chapter list');
};

$tests['AiRetrievalService houdt zoekresultaten licht en slaat live preflight over'] = function (): void {
    $pdo = createRetrievalPdo();
    $youtube = new FakeYouTubeSearchClient();
    $openRouter = new FakeChatOpenRouterClient();
    $extractor = new FakeVideoFrameExtractor();

    $openRouter->responses[] = [
        'ok' => true,
        'content' => '["youth passing drill"]',
    ];

    $youtube->searchItems = [
        [
            'external_id' => 'readyvid01',
            'title' => 'Passing pattern for U11',
            'snippet' => 'Duidelijke passing drill met 3 stations.',
            'channel' => 'Coach Ready',
            'url' => 'https://www.youtube.com/watch?v=readyvid01',
            'duration_seconds' => 360,
            'chapters' => [
                ['timestamp' => '0:00', 'label' => 'Setup'],
                ['timestamp' => '0:45', 'label' => 'Rotation'],
            ],
            'transcript_excerpt' => 'Open draaien, inspeelpass, kaats, doorstart.',
            'transcript_source' => 'captions',
        ],
        [
            'external_id' => 'authvid002',
            'title' => 'Passing drill hidden behind login',
            'snippet' => 'Passing drill die in browser nog kan werken.',
            'channel' => 'Coach Locked',
            'url' => 'https://www.youtube.com/watch?v=authvid002',
            'duration_seconds' => 420,
            'chapters' => [],
            'transcript_excerpt' => '',
            'transcript_source' => 'metadata_fallback',
        ],
    ];

    $extractor->probeResponses['readyvid01'] = [
        'checked' => true,
        'downloadable_via_ytdlp' => true,
        'auth_required' => false,
        'error_code' => null,
        'error' => '',
        'duration_seconds' => 360,
        'used_cookies' => false,
    ];
    $extractor->probeResponses['authvid002'] = [
        'checked' => true,
        'downloadable_via_ytdlp' => false,
        'auth_required' => true,
        'error_code' => 'auth_required',
        'error' => 'ERROR: Sign in to confirm you’re not a bot',
        'duration_seconds' => 420,
        'used_cookies' => false,
    ];

    $service = new TestableAiRetrievalService($pdo, $youtube, $openRouter, $extractor);
    $result = $service->searchVideos(
        'Ik zoek een passing oefening voor JO11',
        null,
        retrievalSettings(['ai_retrieval_max_candidates' => '5']),
        1,
        'openai/gpt-4o-mini'
    );

    assertTrue($result['ok'] === true, 'Search should succeed');
    assertSame(2, count($result['candidates']), 'Expected 2 candidates');
    assertSame(false, !empty($result['candidates'][0]['technical_preflight']['checked']), 'Search candidate should stay unchecked');
    assertSame(null, $result['candidates'][0]['technical_preflight']['downloadable_via_ytdlp'] ?? null, 'Search candidate should not run live availability probe');
    assertSame(true, in_array('Geen van deze video\'s is nu direct klaar voor gebruik. Kies rustig de beste match.', $result['warnings'], true), 'Expected lightweight search warning');
    assertSame(0, count($extractor->probeCalls), 'Search should skip live preflight probes');
    assertTrue(array_key_exists('language', $youtube->searchCalls[0]), 'Search call should record the language field');
    assertSame(null, $youtube->searchCalls[0]['language'], 'Search should not force a relevanceLanguage filter');
};

$tests['AiWorkflowService technical viability onderscheidt aanbevolen en auth-required kandidaten'] = function (): void {
    $pdo = createRetrievalPdo();
    $service = new AiWorkflowService($pdo);

    $ready = $service->assessTechnicalViability([
        'duration_seconds' => 300,
        'chapters' => [['timestamp' => '0:00', 'label' => 'Setup']],
        'transcript_source' => 'captions',
        'technical_preflight' => [
            'checked' => true,
            'downloadable_via_ytdlp' => true,
            'auth_required' => false,
            'status' => 'ok',
            'duration_seconds' => 300,
            'chapter_count' => 1,
            'transcript_source' => 'captions',
            'metadata_only' => false,
        ],
    ]);
    assertSame(true, $ready['is_selectable'], 'Ready candidate should be selectable');
    assertSame(true, $ready['is_recommended'], 'Ready candidate should be recommended');
    assertSame('Klaar voor gebruik', $ready['label'], 'Ready candidate should get ready label');

    $visionNeeded = $service->assessTechnicalViability([
        'duration_seconds' => 300,
        'chapters' => [],
        'transcript_source' => 'metadata_fallback',
        'technical_preflight' => [
            'checked' => true,
            'downloadable_via_ytdlp' => true,
            'auth_required' => false,
            'status' => 'ok',
            'duration_seconds' => 300,
            'chapter_count' => 0,
            'transcript_source' => 'metadata_fallback',
            'metadata_only' => true,
        ],
    ]);
    assertSame(true, $visionNeeded['is_selectable'], 'Metadata-only but downloadable candidate should stay selectable');
    assertSame(false, $visionNeeded['is_recommended'], 'Metadata-only candidate should not be recommended');
    assertSame('Beeld check', $visionNeeded['label'], 'Metadata-only candidate should be marked for visual checking');

    $unchecked = $service->assessTechnicalViability([
        'duration_seconds' => 300,
        'chapters' => [['timestamp' => '0:00', 'label' => 'Setup']],
        'transcript_source' => 'metadata_fallback',
        'technical_preflight' => [
            'checked' => false,
            'downloadable_via_ytdlp' => null,
            'auth_required' => false,
            'status' => '',
            'error_code' => '',
            'error' => '',
            'duration_seconds' => 300,
            'chapter_count' => 1,
            'transcript_source' => 'metadata_fallback',
            'metadata_only' => false,
        ],
    ]);
    assertSame(true, $unchecked['is_selectable'], 'Unchecked candidate should still be selectable');
    assertSame(false, $unchecked['is_recommended'], 'Unchecked candidate should not be recommended yet');
    assertSame('Even checken', $unchecked['label'], 'Unchecked candidate should ask for a manual check');

    $authRequired = $service->assessTechnicalViability([
        'duration_seconds' => 300,
        'chapters' => [],
        'transcript_source' => 'metadata_fallback',
        'technical_preflight' => [
            'checked' => true,
            'downloadable_via_ytdlp' => false,
            'auth_required' => true,
            'status' => 'auth_required',
            'error_code' => 'auth_required',
            'error' => 'ERROR: Sign in to confirm you’re not a bot',
            'duration_seconds' => 300,
            'chapter_count' => 0,
            'transcript_source' => 'metadata_fallback',
            'metadata_only' => true,
        ],
    ]);
    assertSame(false, $authRequired['is_selectable'], 'Auth-required candidate should not be selectable');
    assertSame(false, $authRequired['is_recommended'], 'Auth-required candidate should not be recommended');
    assertSame('Extra toegang nodig', $authRequired['label'], 'Auth-required candidate should get access label');

    $networkUncertain = $service->assessTechnicalViability([
        'duration_seconds' => 300,
        'chapters' => [['timestamp' => '0:00', 'label' => 'Setup']],
        'transcript_source' => 'captions',
        'technical_preflight' => [
            'checked' => true,
            'downloadable_via_ytdlp' => null,
            'auth_required' => false,
            'status' => 'network_error',
            'error_code' => 'network_error',
            'error' => 'Temporary failure in name resolution',
            'duration_seconds' => 300,
            'chapter_count' => 1,
            'transcript_source' => 'captions',
            'metadata_only' => false,
        ],
    ]);
    assertSame(true, $networkUncertain['is_selectable'], 'Network-inconclusive candidate should remain selectable');
    assertSame(false, $networkUncertain['is_recommended'], 'Network-inconclusive candidate should not be recommended');
    assertSame(true, $networkUncertain['preflight_inconclusive'], 'Network-inconclusive candidate should expose inconclusive flag');
    assertSame('Even checken', $networkUncertain['label'], 'Network-inconclusive candidate should get softer label');
};

$tests['AiWorkflowService technical viability markeert cookie-backed video als verwerkbaar'] = function (): void {
    $pdo = createRetrievalPdo();
    $service = new AiWorkflowService($pdo);

    $profile = $service->assessTechnicalViability([
        'duration_seconds' => 300,
        'chapters' => [['timestamp' => '0:00', 'label' => 'Setup']],
        'transcript_source' => 'captions',
        'technical_preflight' => [
            'checked' => true,
            'downloadable_via_ytdlp' => true,
            'auth_required' => true,
            'used_cookies' => true,
            'status' => 'ok',
            'duration_seconds' => 300,
            'chapter_count' => 1,
            'transcript_source' => 'captions',
            'metadata_only' => false,
        ],
    ]);

    assertSame(true, $profile['is_selectable'], 'Cookie-backed candidate should stay selectable');
    assertSame(true, $profile['is_recommended'], 'Cookie-backed candidate should stay recommendable');
    assertSame('Extra toegang', $profile['label'], 'Cookie-backed candidate should get dedicated label');
};

$tests['AiWorkflowService markeert clips onder 30 seconden als korte clip'] = function (): void {
    $pdo = createRetrievalPdo();
    $service = new AiWorkflowService($pdo);

    $profile = $service->assessTechnicalViability([
        'duration_seconds' => 25,
        'chapters' => [['timestamp' => '0:00', 'label' => 'Setup']],
        'transcript_source' => 'captions',
        'technical_preflight' => [
            'checked' => true,
            'downloadable_via_ytdlp' => true,
            'auth_required' => false,
            'status' => 'ok',
            'duration_seconds' => 25,
            'chapter_count' => 1,
            'transcript_source' => 'captions',
            'metadata_only' => false,
        ],
    ]);

    assertSame(true, $profile['is_short_clip'], '25-second clip should count as short clip');
    assertSame(false, $profile['is_recommended'], 'Short clip should not be recommended');
    assertSame('Korte video', $profile['label'], 'Short clip should get short-video label');
};

$tests['AiWorkflowService sorteert aanbevolen kandidaten voor auth-required fallback'] = function (): void {
    $pdo = createRetrievalPdo();
    $retrieval = new FakeSearchPhaseRetrievalService($pdo);
    $retrieval->fakeSearchResult = [
        'ok' => true,
        'candidates' => [
            [
                'external_id' => 'authvid002',
                'title' => 'Login-gated drill',
                'channel' => 'Coach Locked',
                'duration_seconds' => 420,
                'snippet' => 'Beperkte metadata voor login-gated drill.',
                'transcript_source' => 'metadata_fallback',
                'chapters' => [],
                'technical_preflight' => [
                    'checked' => true,
                    'downloadable_via_ytdlp' => false,
                    'auth_required' => true,
                    'status' => 'auth_required',
                    'error_code' => 'auth_required',
                    'metadata_only' => true,
                    'chapter_count' => 0,
                    'transcript_source' => 'metadata_fallback',
                    'duration_seconds' => 420,
                ],
            ],
            [
                'external_id' => 'readyvid01',
                'title' => 'Processing-ready drill',
                'channel' => 'Coach Ready',
                'duration_seconds' => 360,
                'snippet' => 'Passing drill met drie stations en duidelijke rotatie.',
                'transcript_excerpt' => str_repeat('Pass, kaats, open draaien en doorstart. ', 8),
                'transcript_source' => 'captions',
                'chapters' => [['timestamp' => '0:00', 'label' => 'Setup']],
                'technical_preflight' => [
                    'checked' => true,
                    'downloadable_via_ytdlp' => true,
                    'auth_required' => false,
                    'status' => 'ok',
                    'metadata_only' => false,
                    'chapter_count' => 1,
                    'transcript_source' => 'captions',
                    'duration_seconds' => 360,
                ],
            ],
        ],
        'warnings' => [],
    ];

    $openRouter = new FakeChatOpenRouterClient();
    $openRouter->responses[] = [
        'ok' => true,
        'content' => json_encode([
            'selections' => [
                ['candidate_index' => 1, 'reason' => 'Inhoudelijk passend maar risicovol.'],
                ['candidate_index' => 2, 'reason' => 'Past goed en heeft voldoende structuur.'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 40, 'supplier_cost_usd' => 0.01],
    ];

    $workflow = new AiWorkflowService($pdo, $retrieval, new AiPromptBuilder($pdo), $openRouter);
    $result = $workflow->handleSearchPhase(
        'Zoek een passing oefening',
        null,
        retrievalSettings(),
        1,
        'openai/gpt-4o-mini'
    );

    assertTrue($result['ok'] === true, 'Search phase should succeed');
    assertSame('readyvid01', $result['video_choices'][0]['video_id'] ?? '', 'Processing-ready candidate should be sorted to the front');
    assertSame(true, $result['video_choices'][0]['is_recommended'] ?? false, 'Front candidate should be recommended');
    assertSame(false, $result['video_choices'][1]['is_recommended'] ?? true, 'Auth-required fallback should not be recommended');
    assertSame('Bruikbare bron', $result['video_choices'][0]['source_evidence_label'] ?? '', 'Ready candidate should expose evidence label');
    assertTrue(($result['video_choices'][0]['source_evidence_score'] ?? 0) > 0.55, 'Ready candidate should expose useful evidence score');
};

$tests['AiWorkflowService syntheseert metadata-fallback transcript voor zoekkaarten'] = function (): void {
    $pdo = createRetrievalPdo();
    $retrieval = new FakeSearchPhaseRetrievalService($pdo);
    $retrieval->fakeSearchResult = [
        'ok' => true,
        'candidates' => [
            [
                'external_id' => 'metavid01',
                'title' => '5 Best Soccer Drills for U8 & U9',
                'channel' => 'Coach Chapters',
                'duration_seconds' => 420,
                'snippet' => str_repeat('Looking for fun, effective soccer drills for your U8 or U9 team with clear setup, rotation and coaching details. ', 8),
                'transcript_source' => 'metadata_fallback',
                'chapters' => [
                    ['timestamp' => '0:00', 'label' => 'Intro'],
                    ['timestamp' => '0:08', 'label' => 'Touch the Post Shooting'],
                    ['timestamp' => '1:25', 'label' => 'Switching Tag'],
                    ['timestamp' => '3:01', 'label' => 'First touch 1v1'],
                    ['timestamp' => '4:41', 'label' => 'Shooting to 1v1 to 2v1'],
                    ['timestamp' => '6:26', 'label' => '3v3 Funino'],
                ],
                'technical_preflight' => [
                    'checked' => true,
                    'downloadable_via_ytdlp' => true,
                    'auth_required' => false,
                    'status' => 'ok',
                    'metadata_only' => false,
                    'chapter_count' => 6,
                    'transcript_source' => 'metadata_fallback',
                    'duration_seconds' => 420,
                ],
            ],
        ],
        'warnings' => [],
    ];

    $workflow = new AiWorkflowService($pdo, $retrieval, new AiPromptBuilder($pdo), new FakeChatOpenRouterClient());
    $result = $workflow->handleSearchPhase(
        'Zoek een leuke U9 passoefening',
        null,
        retrievalSettings(),
        1,
        'openai/gpt-4o-mini'
    );

    assertTrue($result['ok'] === true, 'Search phase should succeed');
    assertSame('Bruikbare bron', $result['video_choices'][0]['source_evidence_label'] ?? '', 'Chapter-rich metadata candidate should become a usable source');
    assertTrue(($result['video_choices'][0]['source_evidence_score'] ?? 0) >= 0.55, 'Score should cross the threshold after metadata transcript synthesis');
    assertSame('', $result['video_choices'][0]['source_evidence_warning'] ?? '', 'Usable source should not show the low-evidence warning');
};

$tests['AiWorkflowService technical preflight warning benoemt authenticated retrieval-pad'] = function (): void {
    $pdo = createRetrievalPdo();
    $service = new AiWorkflowService($pdo);

    $warning = $service->buildTechnicalPreflightWarning([
        'status' => 'auth_required',
        'auth_required' => true,
        'error' => 'ERROR: Sign in to confirm you’re not a bot',
    ]);

    assertTrue(str_contains($warning, 'extra toegang'), 'Warning should explain that extra access is needed');
    assertTrue(str_contains($warning, 'Kies een andere video'), 'Warning should end with a simple next step');
};

$tests['AiRetrievalService gebruikt cookies-recovery bij directe video'] = function (): void {
    $pdo = createRetrievalPdo();
    $youtube = new FakeYouTubeSearchClient();
    $extractor = new FakeVideoFrameExtractor();

    $cookiesFile = tempnam(sys_get_temp_dir(), 'cookies_');
    file_put_contents($cookiesFile, "# Netscape HTTP Cookie File\n");

    try {
        $youtube->videoItems['cookvid01'] = [
            'external_id' => 'cookvid01',
            'title' => 'Protected but available drill',
            'snippet' => 'Drill achter login maar server heeft cookies.',
            'channel' => 'Coach Protected',
            'url' => 'https://www.youtube.com/watch?v=cookvid01',
            'duration_seconds' => 360,
            'first_chapter' => 'Setup',
            'chapters' => [['timestamp' => '0:00', 'label' => 'Setup']],
            'transcript_excerpt' => 'Pass, kaats, doorstart.',
            'transcript_source' => 'captions',
        ];
        $extractor->probeResponses['cookvid01'] = [
            'checked' => true,
            'downloadable_via_ytdlp' => true,
            'auth_required' => true,
            'error_code' => null,
            'error' => '',
            'duration_seconds' => 360,
            'used_cookies' => true,
            'attempts' => [
                [
                    'stage' => 'probe',
                    'mode' => 'anonymous',
                    'attempted' => true,
                    'used_cookies' => false,
                    'ok' => false,
                    'auth_required' => true,
                    'error_code' => 'auth_required',
                    'error' => 'ERROR: Sign in to confirm you’re not a bot',
                    'duration_seconds' => 0,
                ],
                [
                    'stage' => 'probe',
                    'mode' => 'cookies',
                    'attempted' => true,
                    'used_cookies' => true,
                    'ok' => true,
                    'auth_required' => false,
                    'error_code' => null,
                    'error' => '',
                    'duration_seconds' => 360,
                ],
            ],
        ];

        $service = new TestableAiRetrievalService($pdo, $youtube, null, $extractor);
        $result = $service->fetchDirectVideo('cookvid01', retrievalSettings(['ai_ytdlp_cookies_path' => $cookiesFile]));

        assertTrue($result['ok'] === true, 'Direct video fetch should succeed');
        assertSame($cookiesFile, $extractor->probeCalls[0]['cookies_path'] ?? null, 'Configured cookies path should be passed into preflight');
        assertSame(true, $result['source']['technical_preflight']['used_cookies'] ?? null, 'Preflight should record cookies usage');
        assertSame(2, count($result['source']['technical_preflight']['attempts'] ?? []), 'Preflight should preserve attempt trace');
        assertSame('ok', $result['source']['technical_preflight']['status'] ?? '', 'Successful cookies recovery should mark the video as usable');
    } finally {
        @unlink($cookiesFile);
    }
};

$tests['AiEvaluationSetService laadt en filtert caseset'] = function (): void {
    $pdo = createRetrievalPdo();
    $service = new AiEvaluationSetService($pdo);
    $caseFile = tempnam(sys_get_temp_dir(), 'ai_eval_cases_');

    file_put_contents($caseFile, <<<'PHP'
<?php
return [
    'version' => 'test',
    'updated_at' => '2026-03-15',
    'description' => 'test set',
    'cases' => [
        [
            'case_id' => 'good_case',
            'label' => 'Goede case',
            'bucket' => 'public_downloadable',
            'video_id' => 'abc12345',
            'enabled' => true,
        ],
        [
            'case_id' => 'disabled_case',
            'bucket' => 'metadata_only',
            'video_id' => '',
            'enabled' => false,
        ],
    ],
];
PHP);

    try {
        $caseSet = $service->loadCaseSet($caseFile);
        assertSame('test', $caseSet['version'] ?? '', 'Version should be loaded from case file');
        assertSame(2, count($caseSet['cases'] ?? []), 'Should load both cases');
        assertSame(1, $caseSet['enabled_case_count'] ?? null, 'Should count enabled cases');
        assertSame(1, $caseSet['disabled_case_count'] ?? null, 'Should count disabled cases');

        $filtered = $service->filterCaseSet($caseSet, [], ['public_downloadable']);
        assertSame(1, count($filtered['cases'] ?? []), 'Bucket filter should keep only matching case');
        assertSame('good_case', $filtered['cases'][0]['case_id'] ?? '', 'Bucket filter should preserve matching case');
    } finally {
        @unlink($caseFile);
    }
};

$tests['AiEvaluationSetService evalueert direct case tegen fake retrieval en workflow'] = function (): void {
    $pdo = createRetrievalPdo();
    $service = new AiEvaluationSetService($pdo);
    $retrieval = new FakeEvaluationRetrievalService($pdo);
    $workflow = new FakeEvaluationWorkflowService($pdo);

    $retrieval->directResponses['goodvid01'] = [
        'ok' => true,
        'source' => [
            'external_id' => 'goodvid01',
            'duration_seconds' => 320,
            'transcript_source' => 'captions',
            'chapters' => [
                ['timestamp' => '0:00', 'label' => 'Setup'],
                ['timestamp' => '0:30', 'label' => 'Passing pattern'],
            ],
            'technical_preflight' => [
                'downloadable_via_ytdlp' => true,
                'auth_required' => false,
                'status' => 'ok',
                'used_cookies' => false,
            ],
        ],
    ];
    $workflow->technicalByVideoId['goodvid01'] = [
        'downloadable_via_ytdlp' => true,
        'auth_required' => false,
        'is_selectable' => true,
        'is_recommended' => true,
        'status' => 'ok',
        'error_code' => '',
        'duration_seconds' => 320,
        'chapter_count' => 2,
        'transcript_source' => 'captions',
        'metadata_only' => false,
    ];
    $workflow->evidenceByVideoId['goodvid01'] = [
        'is_sufficient' => true,
        'level' => 'medium',
        'score' => 0.68,
    ];

    $result = $service->evaluateDirectCase([
        'case_id' => 'public_reference',
        'label' => 'Publieke referentie',
        'bucket' => 'public_downloadable',
        'video_id' => 'goodvid01',
        'enabled' => true,
        'expect' => [
            'downloadable_via_ytdlp' => true,
            'technical_selectable' => true,
            'availability_mode' => 'anonymous_ok',
            'source_evidence_min_level' => 'medium',
        ],
    ], $retrieval, $workflow, retrievalSettings());

    assertSame('pass', $result['status'] ?? '', 'Expected direct case to pass');
    assertSame('anonymous_ok', $result['observed']['availability_mode'] ?? '', 'Observed availability mode mismatch');
    assertSame(true, $result['observed']['source_evidence_sufficient'] ?? null, 'Observed evidence sufficiency mismatch');
};

$tests['AiEvaluationSetService bouwt summary en cli rapport'] = function (): void {
    $pdo = createRetrievalPdo();
    $service = new AiEvaluationSetService($pdo);

    $results = [
        [
            'case' => ['case_id' => 'case_ok', 'bucket' => 'public_downloadable', 'label' => 'Case ok', 'video_id' => 'vid1', 'notes' => ''],
            'status' => 'pass',
            'observed' => [
                'availability_mode' => 'anonymous_ok',
                'technical_selectable' => true,
                'technical_recommended' => true,
                'duration_seconds' => 300,
                'transcript_source' => 'captions',
                'source_evidence_level' => 'medium',
                'source_evidence_score' => 0.66,
            ],
            'mismatches' => [],
            'error' => '',
        ],
        [
            'case' => ['case_id' => 'case_fail', 'bucket' => 'auth_gated_browser_playable', 'label' => 'Case fail', 'video_id' => 'vid2', 'notes' => ''],
            'status' => 'fail',
            'observed' => [
                'availability_mode' => 'unavailable',
                'technical_selectable' => false,
                'technical_recommended' => false,
                'duration_seconds' => 0,
                'transcript_source' => 'none',
                'source_evidence_level' => 'low',
                'source_evidence_score' => 0.0,
            ],
            'mismatches' => ['availability_mode_in verwacht een van [auth_required] maar was unavailable'],
            'error' => 'Video ophalen is mislukt.',
        ],
        [
            'case' => ['case_id' => 'case_skip', 'bucket' => 'metadata_only', 'label' => 'Case skip', 'video_id' => '', 'notes' => ''],
            'status' => 'skipped',
            'observed' => [],
            'mismatches' => [],
            'error' => 'Geen video_id ingevuld.',
        ],
    ];

    $summary = $service->buildSummary($results);
    assertSame(1, $summary['pass_count'] ?? null, 'Summary should count passes');
    assertSame(1, $summary['fail_count'] ?? null, 'Summary should count failures');
    assertSame(1, $summary['skipped_count'] ?? null, 'Summary should count skipped cases');
    assertSame(['case_fail'], $summary['failing_case_ids'] ?? [], 'Summary should list failing case IDs');

    $report = $service->renderCliReport([
        'path' => '/tmp/test_cases.php',
        'enabled_case_count' => 2,
        'disabled_case_count' => 1,
    ], $results, $summary, false);

    assertTrue(str_contains($report, 'Samenvatting: 1 pass, 1 fail, 1 skip'), 'CLI report should contain summary line');
    assertTrue(str_contains($report, 'Failing cases: case_fail'), 'CLI report should contain failing case IDs');
};

$tests['AiQualityEventService slaat quality-events met payload op'] = function (): void {
    $pdo = createAiControllerTestPdo();
    $service = new AiQualityEventService($pdo);

    $eventId = $service->logEvent(
        7,
        3,
        12,
        'preflight_result',
        'recommended',
        [
            'phase' => 'search_results',
            'availability_mode' => 'cookie_recovered',
            'downloadable_via_ytdlp' => true,
        ],
        'cookvid01'
    );

    assertTrue($eventId > 0, 'Quality event should return inserted id');

    $stmt = $pdo->prepare('SELECT * FROM ai_quality_events WHERE id = :id');
    $stmt->execute([':id' => $eventId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    assertTrue(is_array($row), 'Inserted quality event should exist');
    assertSame('preflight_result', $row['event_type'] ?? '', 'Event type mismatch');
    assertSame('recommended', $row['status'] ?? '', 'Event status mismatch');
    assertSame('cookvid01', $row['external_id'] ?? '', 'External id mismatch');

    $payload = json_decode((string)($row['payload_json'] ?? ''), true);
    assertTrue(is_array($payload), 'Payload JSON should decode to array');
    assertSame('cookie_recovered', $payload['availability_mode'] ?? '', 'Payload should preserve availability mode');
    assertSame(true, $payload['downloadable_via_ytdlp'] ?? null, 'Payload should preserve viability signal');
};

$tests['AiController logt preflight-resultaten voor zoekkeuzes'] = function (): void {
    $pdo = createAiControllerTestPdo();
    $controller = new AiController($pdo, new FakeChatOpenRouterClient());

    $method = new ReflectionMethod(AiController::class, 'logSearchQualityEvents');
    $method->setAccessible(true);
    $method->invoke($controller, 5, 9, 12, [
        [
            'video_id' => 'ready003',
            'is_recommended' => true,
            'is_selectable' => true,
            'technical_label' => 'Verwerkbaar',
            'technical_summary' => 'Technisch bereikbaar.',
            'technical_preflight' => [
                'downloadable_via_ytdlp' => true,
                'auth_required' => false,
                'used_cookies' => false,
                'status' => 'ok',
                'duration_seconds' => 360,
                'chapter_count' => 2,
                'transcript_source' => 'captions',
                'metadata_only' => false,
                'attempts' => [
                    [
                        'stage' => 'probe',
                        'mode' => 'anonymous',
                        'attempted' => true,
                        'used_cookies' => false,
                        'ok' => true,
                        'auth_required' => false,
                        'error_code' => null,
                        'error' => '',
                        'duration_seconds' => 360,
                    ],
                ],
            ],
            'technical_viability' => [
                'is_short_clip' => false,
            ],
        ],
        [
            'video_id' => 'auth002',
            'is_recommended' => false,
            'is_selectable' => false,
            'technical_label' => 'Login vereist',
            'technical_summary' => 'Backend kan deze video anoniem niet uitlezen.',
            'technical_preflight' => [
                'downloadable_via_ytdlp' => false,
                'auth_required' => true,
                'used_cookies' => false,
                'status' => 'auth_required',
                'error_code' => 'auth_required',
                'duration_seconds' => 280,
                'chapter_count' => 0,
                'transcript_source' => 'metadata_fallback',
                'metadata_only' => true,
                'attempts' => [
                    [
                        'stage' => 'probe',
                        'mode' => 'anonymous',
                        'attempted' => true,
                        'used_cookies' => false,
                        'ok' => false,
                        'auth_required' => true,
                        'error_code' => 'auth_required',
                        'error' => 'ERROR: Sign in to confirm you’re not a bot',
                        'duration_seconds' => 0,
                    ],
                    [
                        'stage' => 'probe',
                        'mode' => 'cookies',
                        'attempted' => false,
                        'used_cookies' => true,
                        'ok' => false,
                        'auth_required' => true,
                        'error_code' => 'cookies_invalid',
                        'error' => 'Video vereist authenticatie, maar het geconfigureerde cookies.txt-bestand is niet leesbaar.',
                        'duration_seconds' => 0,
                    ],
                ],
            ],
            'technical_viability' => [
                'is_short_clip' => false,
            ],
        ],
    ]);

    $rows = $pdo->query('SELECT event_type, status, external_id, payload_json FROM ai_quality_events ORDER BY id ASC')
        ->fetchAll(PDO::FETCH_ASSOC);

    assertSame(2, count($rows), 'Two search choices should produce two preflight events');
    assertSame('preflight_result', $rows[0]['event_type'] ?? '', 'Search event type mismatch');
    assertSame('recommended', $rows[0]['status'] ?? '', 'Recommended choice should keep recommended status');
    assertSame('ready003', $rows[0]['external_id'] ?? '', 'First event external id mismatch');
    $firstPayload = json_decode((string)($rows[0]['payload_json'] ?? ''), true);
    assertTrue(is_array($firstPayload), 'First payload should decode');
    assertSame(1, $firstPayload['attempt_count'] ?? null, 'Recommended choice should expose attempt count');

    $secondPayload = json_decode((string)($rows[1]['payload_json'] ?? ''), true);
    assertTrue(is_array($secondPayload), 'Second payload should decode');
    assertSame('blocked', $rows[1]['status'] ?? '', 'Blocked choice should be logged as blocked');
    assertSame('auth_required', $secondPayload['availability_mode'] ?? '', 'Blocked auth choice should log auth_required mode');
    assertSame(true, $secondPayload['cookies_invalid'] ?? null, 'Blocked auth choice should expose cookies_invalid summary');
};

$tests['AiController logt recovery choice selection als quality-event'] = function (): void {
    $pdo = createAiControllerTestPdo();
    $controller = new AiController($pdo, new FakeChatOpenRouterClient());

    $method = new ReflectionMethod(AiController::class, 'logVideoChoiceSelectionEvent');
    $method->setAccessible(true);
    $method->invoke($controller, 5, 9, 12, 'ready003', 'recovery', 'video_preflight_failed', 0);

    $stmt = $pdo->query("SELECT event_type, status, external_id, payload_json FROM ai_quality_events ORDER BY id DESC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    assertTrue(is_array($row), 'Recovery selection event should exist');
    assertSame('recovery_choice_selected', $row['event_type'] ?? '', 'Recovery selection should use dedicated event type');
    assertSame('selected', $row['status'] ?? '', 'Recovery selection should be logged as selected');
    assertSame('ready003', $row['external_id'] ?? '', 'Recovery selection should track selected video');

    $payload = json_decode((string)($row['payload_json'] ?? ''), true);
    assertTrue(is_array($payload), 'Recovery selection payload should decode');
    assertSame('recovery', $payload['selection_origin'] ?? '', 'Selection origin should be recovery');
    assertSame('video_preflight_failed', $payload['recovery_trigger_code'] ?? '', 'Recovery selection should preserve trigger code');
};

$tests['AiController recovery payload gebruikt alleen selecteerbare alternatieven uit laatste zoekresultaten'] = function (): void {
    $pdo = createAiControllerTestPdo();
    $controller = new AiController($pdo, new FakeChatOpenRouterClient());

    $searchMeta = json_encode([
        'phase' => 'search_results',
        'video_choices' => [
            ['video_id' => 'failed001', 'title' => 'Afvaller', 'is_selectable' => true, 'is_recommended' => true],
            ['video_id' => 'auth002', 'title' => 'Auth nodig', 'is_selectable' => false, 'is_recommended' => false],
            ['video_id' => 'ready003', 'title' => 'Werkbare optie 1', 'is_selectable' => true, 'is_recommended' => true],
            ['video_id' => 'ready004', 'title' => 'Werkbare optie 2', 'is_selectable' => true, 'is_recommended' => false],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $otherMeta = json_encode([
        'phase' => 'generation_recovery',
        'foo' => 'bar',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $stmt = $pdo->prepare(
        'INSERT INTO ai_chat_messages (session_id, role, content, model_id, metadata_json, created_at)
         VALUES (:session_id, :role, :content, :model_id, :metadata_json, :created_at)'
    );
    $stmt->execute([
        ':session_id' => 12,
        ':role' => 'assistant',
        ':content' => 'Zoekresultaten',
        ':model_id' => 'openai/gpt-4o-mini',
        ':metadata_json' => $searchMeta,
        ':created_at' => '2026-03-15 10:00:00',
    ]);
    $stmt->execute([
        ':session_id' => 12,
        ':role' => 'assistant',
        ':content' => 'Latere melding',
        ':model_id' => 'openai/gpt-4o-mini',
        ':metadata_json' => $otherMeta,
        ':created_at' => '2026-03-15 10:05:00',
    ]);

    $method = new ReflectionMethod(AiController::class, 'buildRecoveryPayload');
    $method->setAccessible(true);
    $payload = $method->invoke($controller, 12, 'failed001', 3);

    assertTrue(is_array($payload), 'Recovery payload should be array');
    assertSame(2, count($payload['recovery_video_choices'] ?? []), 'Should return only the 2 selectable alternatives');
    assertSame('ready003', $payload['recovery_video_choices'][0]['video_id'] ?? '', 'First fallback should be first selectable alternative');
    assertSame('ready004', $payload['recovery_video_choices'][1]['video_id'] ?? '', 'Second fallback should be second selectable alternative');
};

$tests['AiController bouwt concept-recovery payload voor bronnen met minimale metadata'] = function (): void {
    $pdo = createAiControllerTestPdo();
    $controller = new AiController($pdo, new FakeChatOpenRouterClient());

    $method = new ReflectionMethod(AiController::class, 'buildConceptRecoveryPayload');
    $method->setAccessible(true);
    $payload = $method->invoke($controller, [
        'external_id' => 'meta001',
        'title' => 'Metadata bron',
        'snippet' => 'Beschrijving van de drill',
    ], 'source_evidence_too_low');

    assertTrue(is_array($payload), 'Concept recovery payload should be array');
    assertSame('meta001', $payload['concept_recovery']['video_id'] ?? '', 'Concept payload should keep video id');
    assertSame('source_evidence_too_low', $payload['concept_recovery']['trigger_code'] ?? '', 'Concept payload should keep trigger code');
};

$tests['AiController bouwt screenshot-recovery payload wanneer vision actief is'] = function (): void {
    $pdo = createAiControllerTestPdo();
    $controller = new AiController($pdo, new FakeChatOpenRouterClient());

    $method = new ReflectionMethod(AiController::class, 'buildScreenshotRecoveryPayload');
    $method->setAccessible(true);
    $payload = $method->invoke($controller, [
        'external_id' => 'auth001',
        'title' => 'Auth-video',
    ], [
        'ai_default_vision_model' => 'openai/gpt-4o',
    ], 'video_preflight_failed');

    assertTrue(is_array($payload), 'Screenshot recovery payload should be array');
    assertSame('auth001', $payload['screenshot_recovery']['video_id'] ?? '', 'Screenshot payload should keep video id');
    assertSame('video_preflight_failed', $payload['screenshot_recovery']['trigger_code'] ?? '', 'Screenshot payload should keep trigger code');
};

$tests['AiController leest pending concept-context uit laatste assistantbericht'] = function (): void {
    $pdo = createAiControllerTestPdo();
    $controller = new AiController($pdo, new FakeChatOpenRouterClient());

    $stmt = $pdo->prepare(
        'INSERT INTO ai_chat_messages (session_id, role, content, model_id, metadata_json, created_at)
         VALUES (:session_id, :role, :content, :model_id, :metadata_json, :created_at)'
    );
    $stmt->execute([
        ':session_id' => 12,
        ':role' => 'assistant',
        ':content' => 'Conceptvragen',
        ':model_id' => 'openai/gpt-4o-mini',
        ':metadata_json' => json_encode([
            'phase' => 'concept_questions',
            'concept_context' => [
                'source' => [
                    'external_id' => 'meta001',
                    'title' => 'Metadata bron',
                ],
                'coach_request' => 'Maak hier een passingvorm van',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':created_at' => '2026-03-15 10:00:00',
    ]);

    $method = new ReflectionMethod(AiController::class, 'loadPendingConceptContext');
    $method->setAccessible(true);
    $context = $method->invoke($controller, 12);

    assertTrue(is_array($context), 'Pending concept context should be array');
    assertSame('meta001', $context['source']['external_id'] ?? '', 'Pending concept should expose cached source');
    assertSame('Maak hier een passingvorm van', $context['coach_request'] ?? '', 'Pending concept should preserve original coach request');
};

$tests['AiController leest pending screenshot-context uit laatste assistantbericht'] = function (): void {
    $pdo = createAiControllerTestPdo();
    $controller = new AiController($pdo, new FakeChatOpenRouterClient());

    $stmt = $pdo->prepare(
        'INSERT INTO ai_chat_messages (session_id, role, content, model_id, metadata_json, created_at)
         VALUES (:session_id, :role, :content, :model_id, :metadata_json, :created_at)'
    );
    $stmt->execute([
        ':session_id' => 14,
        ':role' => 'assistant',
        ':content' => 'Gebruik screenshot recovery',
        ':model_id' => 'openai/gpt-4o-mini',
        ':metadata_json' => json_encode([
            'screenshot_recovery' => [
                'video_id' => 'auth001',
            ],
            'screenshot_context' => [
                'source' => [
                    'external_id' => 'auth001',
                    'title' => 'Auth-video',
                ],
                'coach_request' => 'Maak hier een passingvorm van',
                'trigger_code' => 'video_preflight_failed',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':created_at' => '2026-03-15 10:00:00',
    ]);

    $method = new ReflectionMethod(AiController::class, 'loadPendingScreenshotContext');
    $method->setAccessible(true);
    $context = $method->invoke($controller, 14);

    assertTrue(is_array($context), 'Pending screenshot context should be array');
    assertSame('auth001', $context['source']['external_id'] ?? '', 'Pending screenshot should expose cached source');
    assertSame('video_preflight_failed', $context['trigger_code'] ?? '', 'Pending screenshot should preserve trigger code');
};

$tests['AiController normaliseert geuploade screenshots naar vision-frames'] = function (): void {
    $pdo = createAiControllerTestPdo();
    $controller = new AiController($pdo, new FakeChatOpenRouterClient());

    $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+yF9kAAAAASUVORK5CYII=');
    assertTrue(is_string($pngData) && $pngData !== '', 'PNG fixture should decode');

    $tmpPath = tempnam(sys_get_temp_dir(), 'ai_shot_');
    assertTrue(is_string($tmpPath) && $tmpPath !== '', 'Temp path should exist');
    file_put_contents($tmpPath, $pngData);

    try {
        $method = new ReflectionMethod(AiController::class, 'parseUploadedScreenshotFrames');
        $method->setAccessible(true);
        $result = $method->invoke($controller, [
            'name' => ['shot1.png', 'shot2.png'],
            'type' => ['image/png', 'image/png'],
            'tmp_name' => [$tmpPath, $tmpPath],
            'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'size' => [strlen($pngData), strlen($pngData)],
        ]);
    } finally {
        @unlink($tmpPath);
    }

    assertSame(true, $result['ok'] ?? false, 'Screenshot parsing should succeed');
    assertSame(2, count($result['frames'] ?? []), 'Should produce two frame entries');
    assertTrue(str_starts_with((string)($result['frames'][0]['data_uri'] ?? ''), 'data:image/png;base64,'), 'Frame should be converted to data URI');
    assertSame('Screenshot 1', $result['frames'][0]['timestamp_formatted'] ?? '', 'Frame label should be stable');
};

$tests['AiController resolveChatMode ondersteunt refine modi expliciet'] = function (): void {
    $pdo = createAiControllerTestPdo();
    $controller = new AiController($pdo, new FakeChatOpenRouterClient());

    $method = new ReflectionMethod(AiController::class, 'resolveChatMode');
    $method->setAccessible(true);

    $resolvedText = $method->invoke($controller, 'refine_text', '', 0, false);
    $resolvedDrawing = $method->invoke($controller, 'refine_drawing', '', 0, false);

    assertSame('refine_text', $resolvedText, 'refine_text mode should be accepted');
    assertSame('refine_drawing', $resolvedDrawing, 'refine_drawing mode should be accepted');
};

$tests['AiController parseAndValidateOutput negeert lege drawing suggestion'] = function (): void {
    $pdo = createAiControllerTestPdo();
    $controller = new AiController($pdo, new FakeChatOpenRouterClient());
    $pdo->exec(
        'CREATE TABLE exercise_options (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category TEXT NOT NULL,
            name TEXT NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0
        )'
    );
    $pdo->exec(
        "INSERT INTO exercise_options (category, name, sort_order) VALUES
            ('team_task', 'Opbouw', 1),
            ('objective', 'Drukzetten', 1),
            ('football_action', 'Passen', 1)"
    );

    $method = new ReflectionMethod(AiController::class, 'parseAndValidateOutput');
    $method->setAccessible(true);

    $raw = <<<OUT
```exercise_json
{
  "title": "Aanname oefening",
  "description": "Beschrijving van de oefening",
  "min_players": 4,
  "max_players": 8,
  "duration": 10,
  "field_type": "square"
}
```
```drawing_json
[]
```
OUT;

    $output = $method->invoke($controller, $raw, 'square', null, [], 'Werk de tekst bij');

    assertTrue(is_array($output), 'Output should be parsed as array');
    assertTrue(is_array($output['text_suggestion'] ?? null), 'Text suggestion should still be present');
    assertSame(null, $output['drawing_suggestion'] ?? null, 'Empty drawing suggestion should be dropped');

    $warnings = is_array($output['warnings'] ?? null) ? $output['warnings'] : [];
    $hasEmptyDrawingWarning = false;
    foreach ($warnings as $warning) {
        if (str_contains((string)$warning, 'tekening') && str_contains((string)$warning, 'leeg')) {
            $hasEmptyDrawingWarning = true;
            break;
        }
    }
    assertSame(true, $hasEmptyDrawingWarning, 'Dropping an empty drawing should add a warning');
};

$tests['AiController parseAndValidateOutput verwijdert interne meta-zinnen uit coach_instructions'] = function (): void {
    $pdo = createAiControllerTestPdo();
    $controller = new AiController($pdo, new FakeChatOpenRouterClient());
    $pdo->exec(
        'CREATE TABLE exercise_options (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category TEXT NOT NULL,
            name TEXT NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0
        )'
    );
    $pdo->exec(
        "INSERT INTO exercise_options (category, name, sort_order) VALUES
            ('team_task', 'Opbouw', 1),
            ('objective', 'Drukzetten', 1),
            ('football_action', 'Passen', 1)"
    );

    $method = new ReflectionMethod(AiController::class, 'parseAndValidateOutput');
    $method->setAccessible(true);

    $raw = <<<OUT
```exercise_json
{
  "title": "Coachbare oefening",
  "description": "Spelers passen in driehoeksvorm.",
  "coach_instructions": "Dit is een brongetrouwe scenesamenvatting en geen volledig verifieerbare oefenorganisatie. Focus op korte passlijnen en veel herhaling.",
  "min_players": 4,
  "max_players": 8,
  "duration": 10,
  "field_type": "square"
}
```
OUT;

    $output = $method->invoke($controller, $raw, 'square', null, [], 'Maak de uitleg praktisch');
    $textSuggestion = is_array($output['text_suggestion'] ?? null) ? $output['text_suggestion'] : [];
    $fields = is_array($textSuggestion['fields'] ?? null) ? $textSuggestion['fields'] : [];
    $coachInstructions = trim((string)($fields['coach_instructions'] ?? ''));

    assertTrue($coachInstructions !== '', 'Coach instructions should remain available after cleanup');
    assertTrue(!str_contains(strtolower($coachInstructions), 'brongetrouwe scenesamenvatting'), 'Internal meta sentence should be removed');
    assertTrue(str_contains($coachInstructions, 'Focus op korte passlijnen'), 'Practical coaching sentence should remain');
};

$tests['AiController chapter-fallback bouwt segmentkeuze bij meerdere hoofdstukken'] = function (): void {
    $pdo = createAiControllerTestPdo();
    $controller = new AiController($pdo, new FakeChatOpenRouterClient());

    $method = new ReflectionMethod(AiController::class, 'buildChapterFallbackSegments');
    $method->setAccessible(true);

    $segments = $method->invoke($controller, [
        'duration_seconds' => 420,
        'chapters' => [
            ['timestamp' => '0:08', 'label' => 'Touch the Post Shooting'],
            ['timestamp' => '1:25', 'label' => 'Switching-Tag'],
            ['timestamp' => '3:00', 'label' => '1v1 duel'],
            ['timestamp' => '4:45', 'label' => 'Partijvorm'],
        ],
    ]);

    assertSame(4, count($segments), 'Each chapter should become a selectable fallback segment');
    assertSame('Touch the Post Shooting', $segments[0]['title'] ?? '', 'First fallback segment title mismatch');
    assertTrue(($segments[0]['id'] ?? 0) >= 9001, 'Fallback segments should have deterministic ids');
    assertSame(8.0, (float)($segments[0]['start_seconds'] ?? -1), 'Fallback start timestamp mismatch');
    assertSame(85.0, (float)($segments[0]['end_seconds'] ?? -1), 'Fallback end timestamp should align with next chapter');
};

$tests['AiController chapter-fallback gebruikt chapter_count hint als hoofdstuklabels ontbreken'] = function (): void {
    $pdo = createAiControllerTestPdo();
    $controller = new AiController($pdo, new FakeChatOpenRouterClient());

    $method = new ReflectionMethod(AiController::class, 'buildChapterFallbackSegments');
    $method->setAccessible(true);

    $segments = $method->invoke($controller, [
        'duration_seconds' => 360,
        'chapters' => [],
        'snippet' => 'Korte beschrijving zonder timestamps',
        'technical_preflight' => [
            'chapter_count' => 6,
        ],
    ]);

    assertSame(6, count($segments), 'Fallback should synthesize chapter choices from chapter_count hint');
    assertSame('Hoofdstuk 1', $segments[0]['title'] ?? '', 'Synthetic segment title should be stable');
    assertSame('Hoofdstuk 6', $segments[5]['title'] ?? '', 'Synthetic segment title should include last chapter number');
    assertSame(60.0, (float)($segments[0]['end_seconds'] ?? -1), 'Synthetic segments should divide duration evenly');
};

$tests['AiController logt blocker en recovery-offer als aparte quality-events'] = function (): void {
    $pdo = createAiControllerTestPdo();
    $controller = new AiController($pdo, new FakeChatOpenRouterClient());

    $method = new ReflectionMethod(AiController::class, 'logGenerationBlockerQualityEvents');
    $method->setAccessible(true);
    $method->invoke(
        $controller,
        5,
        9,
        12,
        'video_preflight_failed',
        'auth002',
        [
            'phase' => 'generation_preflight',
            'availability_mode' => 'auth_required',
            'auth_required' => true,
            'downloadable_via_ytdlp' => false,
        ],
        [
            'recovery_video_choices' => [
                ['video_id' => 'ready003'],
                ['video_id' => 'ready004'],
            ],
        ]
    );

    $rows = $pdo->query('SELECT event_type, status, external_id, payload_json FROM ai_quality_events ORDER BY id ASC')
        ->fetchAll(PDO::FETCH_ASSOC);

    assertSame(2, count($rows), 'Blocker logging should create blocker and recovery events');
    assertSame('video_preflight_failed', $rows[0]['event_type'] ?? '', 'First event should record blocker');
    assertSame('blocked', $rows[0]['status'] ?? '', 'Blocker event should use blocked status');
    assertSame('recovery_offered', $rows[1]['event_type'] ?? '', 'Second event should record recovery offer');
    assertSame('offered', $rows[1]['status'] ?? '', 'Recovery event should note offered status');

    $recoveryPayload = json_decode((string)($rows[1]['payload_json'] ?? ''), true);
    assertTrue(is_array($recoveryPayload), 'Recovery payload should decode');
    assertSame(2, $recoveryPayload['recovery_count'] ?? null, 'Recovery payload should include alternative count');
    assertSame(['ready003', 'ready004'], $recoveryPayload['recovery_video_ids'] ?? [], 'Recovery payload should include alternative ids');
};

$tests['AiController logt concept-only recovery ook als offered'] = function (): void {
    $pdo = createAiControllerTestPdo();
    $controller = new AiController($pdo, new FakeChatOpenRouterClient());

    $method = new ReflectionMethod(AiController::class, 'logGenerationBlockerQualityEvents');
    $method->setAccessible(true);
    $method->invoke(
        $controller,
        5,
        9,
        12,
        'source_evidence_too_low',
        'meta001',
        [
            'phase' => 'generation_evidence_gate',
            'evidence_score' => 0.22,
        ],
        [],
        [
            'concept_recovery' => [
                'video_id' => 'meta001',
                'trigger_code' => 'source_evidence_too_low',
            ],
        ]
    );

    $rows = $pdo->query('SELECT event_type, status, payload_json FROM ai_quality_events ORDER BY id ASC')
        ->fetchAll(PDO::FETCH_ASSOC);

    assertSame(2, count($rows), 'Concept-only blocker should still log blocker and recovery event');
    assertSame('recovery_offered', $rows[1]['event_type'] ?? '', 'Second event should still be recovery_offered');
    assertSame('offered', $rows[1]['status'] ?? '', 'Concept-only recovery should count as offered');

    $payload = json_decode((string)($rows[1]['payload_json'] ?? ''), true);
    assertTrue(is_array($payload), 'Recovery payload should decode');
    assertSame(true, $payload['concept_available'] ?? null, 'Concept-only recovery should expose concept flag');
    assertSame('meta001', $payload['concept_video_id'] ?? '', 'Concept-only recovery should expose concept video id');
};

$tests['AiAdminController quality summary telt blockers en recovery events'] = function (): void {
    $pdo = createAiAdminTestPdo();
    $pdo->exec("INSERT INTO users (id, name) VALUES (5, 'Coach A')");
    $stmt = $pdo->prepare(
        'INSERT INTO ai_quality_events (user_id, team_id, session_id, event_type, status, external_id, payload_json, created_at)
         VALUES (:user_id, :team_id, :session_id, :event_type, :status, :external_id, :payload_json, :created_at)'
    );
    foreach ([
        ['event_type' => 'video_preflight_failed', 'status' => 'blocked', 'external_id' => 'auth002', 'payload_json' => '{}'],
        ['event_type' => 'recovery_offered', 'status' => 'offered', 'external_id' => 'auth002', 'payload_json' => '{"recovery_count":2}'],
        ['event_type' => 'recovery_choice_selected', 'status' => 'selected', 'external_id' => 'ready003', 'payload_json' => '{"recovery_trigger_code":"video_preflight_failed"}'],
        ['event_type' => 'frame_download_result', 'status' => 'cookie_recovered', 'external_id' => 'cookvid01', 'payload_json' => '{}'],
    ] as $index => $row) {
        $stmt->execute([
            ':user_id' => 5,
            ':team_id' => 9,
            ':session_id' => 12,
            ':event_type' => $row['event_type'],
            ':status' => $row['status'],
            ':external_id' => $row['external_id'],
            ':payload_json' => $row['payload_json'],
            ':created_at' => '2026-03-15 10:0' . $index . ':00',
        ]);
    }

    $controller = new AiAdminController($pdo);
    $method = new ReflectionMethod(AiAdminController::class, 'getQualitySummary');
    $method->setAccessible(true);
    $summary = $method->invoke($controller);

    assertTrue(is_array($summary), 'Quality summary should be array');
    assertSame(4, $summary['total_events'] ?? null, 'Quality summary should count all events');
    assertSame(1, $summary['blocker_events'] ?? null, 'Quality summary should count blockers');
    assertSame(1, $summary['recovery_offered'] ?? null, 'Quality summary should count recovery offers');
    assertSame(1, $summary['recovery_selected'] ?? null, 'Quality summary should count recovery selections');
    assertSame(1, $summary['cookie_recoveries'] ?? null, 'Quality summary should count cookie recoveries');
};

$tests['AiAdminController recente quality events formatteren payloadsamenvatting'] = function (): void {
    $pdo = createAiAdminTestPdo();
    $pdo->exec("INSERT INTO users (id, name) VALUES (5, 'Coach A')");
    $stmt = $pdo->prepare(
        'INSERT INTO ai_quality_events (user_id, team_id, session_id, event_type, status, external_id, payload_json, created_at)
         VALUES (:user_id, :team_id, :session_id, :event_type, :status, :external_id, :payload_json, :created_at)'
    );
    $stmt->execute([
        ':user_id' => 5,
        ':team_id' => 9,
        ':session_id' => 12,
        ':event_type' => 'recovery_choice_selected',
        ':status' => 'selected',
        ':external_id' => 'ready003',
        ':payload_json' => '{"recovery_trigger_code":"source_evidence_too_low"}',
        ':created_at' => '2026-03-15 10:00:00',
    ]);
    $stmt->execute([
        ':user_id' => 5,
        ':team_id' => 9,
        ':session_id' => 12,
        ':event_type' => 'frame_download_result',
        ':status' => 'cookie_recovered',
        ':external_id' => 'cookvid01',
        ':payload_json' => '{"anonymous_attempt_failed":true,"cookie_attempt_success":true}',
        ':created_at' => '2026-03-15 10:01:00',
    ]);

    $controller = new AiAdminController($pdo);
    $method = new ReflectionMethod(AiAdminController::class, 'getRecentQualityEvents');
    $method->setAccessible(true);
    $events = $method->invoke($controller, 10);

    assertTrue(is_array($events), 'Recent quality events should be array');
    assertSame(2, count($events), 'Should return both recent quality events');
    assertSame('frame_download_result', $events[0]['event_type'] ?? '', 'Most recent event should come first');
    assertSame('Anonieme download faalde, cookies-retry slaagde', $events[0]['payload_summary'] ?? '', 'Frame download event should have readable summary');
    assertSame('Coach koos recovery-optie na source_evidence_too_low', $events[1]['payload_summary'] ?? '', 'Recovery selection should have readable summary');
};

$tests['AiController logt frame-download-result met cookie recovery summary'] = function (): void {
    $pdo = createAiControllerTestPdo();
    $controller = new AiController($pdo, new FakeChatOpenRouterClient());

    $method = new ReflectionMethod(AiController::class, 'logFrameDownloadQualityEvent');
    $method->setAccessible(true);
    $method->invoke(
        $controller,
        5,
        9,
        12,
        'cookvid01',
        [
            'ok' => true,
            'duration' => 360,
            'frames' => [
                ['timestamp' => 10.0],
                ['timestamp' => 20.0],
            ],
            'download_attempts' => [
                [
                    'stage' => 'download',
                    'mode' => 'anonymous',
                    'attempted' => true,
                    'used_cookies' => false,
                    'ok' => false,
                    'auth_required' => true,
                    'error_code' => 'auth_required',
                    'error' => 'ERROR: Sign in to confirm you’re not a bot',
                    'duration_seconds' => 0,
                ],
                [
                    'stage' => 'download',
                    'mode' => 'cookies',
                    'attempted' => true,
                    'used_cookies' => true,
                    'ok' => true,
                    'auth_required' => false,
                    'error_code' => null,
                    'error' => '',
                    'duration_seconds' => 0,
                ],
            ],
        ],
        0
    );

    $stmt = $pdo->query("SELECT event_type, status, payload_json FROM ai_quality_events WHERE event_type = 'frame_download_result' ORDER BY id DESC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    assertTrue(is_array($row), 'Frame download quality event should exist');
    assertSame('cookie_recovered', $row['status'] ?? '', 'Cookie-assisted frame download should be marked as recovered');

    $payload = json_decode((string)($row['payload_json'] ?? ''), true);
    assertTrue(is_array($payload), 'Frame download payload should decode');
    assertSame(true, $payload['anonymous_attempt_failed'] ?? null, 'Payload should record anonymous failure');
    assertSame(true, $payload['cookie_attempt_success'] ?? null, 'Payload should record cookie success');
    assertSame(2, $payload['frame_count'] ?? null, 'Payload should record extracted frame count');
};

// === VideoFrameExtractor tests ===

$tests['VideoFrameExtractor berekent uniforme timestamps correct'] = function (): void {
    $extractor = new VideoFrameExtractor('/tmp/vfe_test_' . bin2hex(random_bytes(4)));

    // 60 seconds, no chapters, 5 frames
    $timestamps = $extractor->calculateTimestamps(60, [], 5);
    assertSame(5, count($timestamps), 'Should return exactly 5 timestamps');
    assertTrue($timestamps[0] >= 1.0, 'First timestamp should skip intro');
    assertTrue($timestamps[4] <= 59.0, 'Last timestamp should skip outro');

    // Timestamps should be ordered
    for ($i = 1; $i < count($timestamps); $i++) {
        assertTrue($timestamps[$i] > $timestamps[$i - 1], 'Timestamps should be monotonically increasing');
    }
};

$tests['VideoFrameExtractor berekent chapter-based timestamps correct'] = function (): void {
    $extractor = new VideoFrameExtractor('/tmp/vfe_test_' . bin2hex(random_bytes(4)));

    $chapters = [
        ['start_seconds' => 0, 'title' => 'Intro'],
        ['start_seconds' => 30, 'title' => 'Drill 1'],
        ['start_seconds' => 120, 'title' => 'Drill 2'],
    ];

    $timestamps = $extractor->calculateTimestamps(180, $chapters, 8);
    assertTrue(count($timestamps) >= 3, 'Should have at least 1 frame per chapter');
    assertTrue(count($timestamps) <= 10, 'Should not exceed requested count significantly');

    // Should have frames in different chapter ranges
    $inChapter1 = array_filter($timestamps, fn($t) => $t >= 0 && $t < 30);
    $inChapter2 = array_filter($timestamps, fn($t) => $t >= 30 && $t < 120);
    $inChapter3 = array_filter($timestamps, fn($t) => $t >= 120);
    assertTrue(count($inChapter1) >= 1, 'Should have at least 1 frame in chapter 1');
    assertTrue(count($inChapter2) >= 1, 'Should have at least 1 frame in chapter 2');
    assertTrue(count($inChapter3) >= 1, 'Should have at least 1 frame in chapter 3');
};

$tests['VideoFrameExtractor weigert te lange video'] = function (): void {
    $extractor = new VideoFrameExtractor('/tmp/vfe_test_' . bin2hex(random_bytes(4)));

    $result = $extractor->extractFrames('dQw4w9WgXcQ', 1200); // 20 min > 15 min max
    assertSame(false, $result['ok'], 'Should reject video exceeding max duration');
    assertTrue(str_contains($result['error'], 'te lang'), 'Error should mention video too long');
};

$tests['VideoFrameExtractor weigert ongeldig video ID'] = function (): void {
    $extractor = new VideoFrameExtractor('/tmp/vfe_test_' . bin2hex(random_bytes(4)));

    $result = $extractor->extractFrames('ab', 60); // too short
    assertSame(false, $result['ok'], 'Should reject invalid video ID');
    assertTrue(str_contains($result['error'], 'Ongeldig'), 'Error should mention invalid ID');
};

$tests['VideoFrameExtractor leeg bij 0 duur zonder chapters'] = function (): void {
    $extractor = new VideoFrameExtractor('/tmp/vfe_test_' . bin2hex(random_bytes(4)));

    $timestamps = $extractor->calculateTimestamps(0, [], 10);
    assertSame([], $timestamps, 'Zero duration should return no timestamps');
};

$tests['VideoFrameExtractor preflight probeert cookies na auth-fout'] = function (): void {
    $extractor = new StubbedProcessVideoFrameExtractor();
    $cookiesFile = tempnam(sys_get_temp_dir(), 'cookies_');
    file_put_contents($cookiesFile, "# Netscape HTTP Cookie File\n");

    try {
        $extractor->queuedResults[] = [
            'ok' => false,
            'error' => 'ERROR: Sign in to confirm you’re not a bot',
        ];
        $extractor->queuedResults[] = [
            'ok' => true,
            'output' => '{"duration":321}',
        ];

        $result = $extractor->probeAvailability('dQw4w9WgXcQ', $cookiesFile);

        assertSame(true, $result['downloadable_via_ytdlp'], 'Cookies retry should recover auth-gated video');
        assertSame(true, $result['auth_required'], 'Recovered auth-gated video should still record auth requirement');
        assertSame(true, $result['used_cookies'], 'Recovered auth-gated video should report cookie usage');
        assertSame(321, $result['duration_seconds'], 'Probe should preserve duration from cookies retry');
        assertSame(2, count($result['attempts'] ?? []), 'Probe should record both attempts');
        assertSame('anonymous', $result['attempts'][0]['mode'] ?? '', 'First attempt should be anonymous in trace');
        assertSame('cookies', $result['attempts'][1]['mode'] ?? '', 'Second attempt should be cookie-backed in trace');
        assertSame(true, $result['attempts'][1]['ok'] ?? null, 'Cookie-backed attempt should be marked successful');
        assertSame(2, count($extractor->commands), 'Probe should attempt anonymous run and cookie retry');
        assertTrue(!in_array('--cookies', $extractor->commands[0]['cmd'], true), 'First attempt should be anonymous');
        assertTrue(in_array('--cookies', $extractor->commands[1]['cmd'], true), 'Second attempt should use cookies');
    } finally {
        @unlink($cookiesFile);
    }
};

$tests['VideoFrameExtractor preflight meldt cookies_invalid na auth-fout met onleesbaar path'] = function (): void {
    $extractor = new StubbedProcessVideoFrameExtractor();
    $extractor->queuedResults[] = [
        'ok' => false,
        'error' => 'ERROR: Sign in to confirm you’re not a bot',
    ];

    $result = $extractor->probeAvailability('dQw4w9WgXcQ', '/tmp/does-not-exist-cookies.txt');

    assertSame(false, $result['downloadable_via_ytdlp'], 'Unreadable cookies path should not recover auth-gated video');
    assertSame('cookies_invalid', $result['error_code'], 'Unreadable cookies path should map to cookies_invalid');
    assertSame(true, $result['auth_required'], 'Unreadable cookies path should keep auth_required');
    assertSame(2, count($result['attempts'] ?? []), 'Unreadable cookies path should still record skipped cookie attempt');
    assertSame(false, $result['attempts'][1]['attempted'] ?? true, 'Cookie attempt should be marked as skipped');
    assertSame('cookies_invalid', $result['attempts'][1]['error_code'] ?? '', 'Skipped cookie attempt should record cookies_invalid');
    assertSame(1, count($extractor->commands), 'Unreadable cookies path should not trigger a second yt-dlp run');
};

$tests['VideoFrameExtractor extractFrames bewaart download attempts bij cookie recovery'] = function (): void {
    $extractor = new StubbedProcessVideoFrameExtractor();
    $cookiesFile = tempnam(sys_get_temp_dir(), 'cookies_');
    file_put_contents($cookiesFile, "# Netscape HTTP Cookie File\n");

    $extractor->afterRunProcess = static function (array $cmd, int $timeoutSeconds, array $result): void {
        if (in_array('-o', $cmd, true) && !empty($result['ok'])) {
            $outputIndex = array_search('-o', $cmd, true);
            if ($outputIndex !== false && isset($cmd[$outputIndex + 1])) {
                $template = (string)$cmd[$outputIndex + 1];
                $videoPath = str_replace('%(ext)s', 'mp4', $template);
                file_put_contents($videoPath, str_repeat('V', 256));
            }
        }

        $lastArg = end($cmd);
        if (is_string($lastArg) && str_ends_with($lastArg, '.jpg') && !empty($result['ok'])) {
            file_put_contents($lastArg, str_repeat('J', 256));
        }
    };

    try {
        $extractor->queuedResults[] = [
            'ok' => false,
            'error' => 'ERROR: Sign in to confirm you’re not a bot',
        ];
        $extractor->queuedResults[] = [
            'ok' => true,
            'output' => '',
        ];
        for ($i = 0; $i < 4; $i++) {
            $extractor->queuedResults[] = [
                'ok' => true,
                'output' => '',
            ];
        }

        $result = $extractor->extractFrames('dQw4w9WgXcQ', 60, [], 4, $cookiesFile);

        assertSame(true, $result['ok'], 'Cookie-backed download should allow frame extraction to complete');
        assertSame(4, count($result['frames'] ?? []), 'Frame extraction should return requested frame count');
        assertSame(2, count($result['download_attempts'] ?? []), 'Download trace should include anonymous fail and cookie recovery');
        assertSame('auth_required', $result['download_attempts'][0]['error_code'] ?? '', 'Anonymous download attempt should capture auth failure');
        assertSame(true, $result['download_attempts'][1]['ok'] ?? null, 'Cookie-backed download attempt should succeed');
    } finally {
        @unlink($cookiesFile);
    }
};

// === VisualEvidenceService tests ===

// Minimal fake OpenRouterClient for vision tests
class FakeVisionOpenRouterClient extends OpenRouterClient {
    public ?array $fakeResponse = null;
    public array $lastMessages = [];

    public function __construct() {
        // Skip parent PDO requirement
    }

    public function visionCompletion(array $messages, string $modelId, int $userId, int $maxTokens = 4096): array {
        $this->lastMessages = $messages;
        return $this->fakeResponse ?? ['ok' => false, 'error' => 'no fake response set'];
    }
}

$tests['VisualEvidenceService parseert geldige vision JSON correct'] = function (): void {
    $fakePdo = new PDO('sqlite::memory:');
    $fakeClient = new FakeVisionOpenRouterClient();
    $promptBuilder = new AiPromptBuilder($fakePdo);

    $validJson = json_encode([
        'setup' => [
            'starting_shape' => '4 vs 2 rondo',
            'field_shape' => 'vierkant',
            'field_markings' => 'pionnen op de hoeken',
            'estimated_dimensions' => '10x10 meter',
            'player_count' => '6',
            'player_roles' => ['aanvallers', 'verdedigers'],
            'equipment' => ['pionnen', 'hesjes'],
        ],
        'sequence' => [
            ['frame' => 1, 'timestamp' => '00:05', 'action' => 'Spelers staan in vierkant', 'movement_patterns' => 'statisch'],
            ['frame' => 2, 'timestamp' => '00:15', 'action' => 'Pass naar links', 'movement_patterns' => 'draaien na pass'],
        ],
        'patterns' => [
            'passing_directions' => 'voornamelijk horizontaal',
            'running_lines' => 'kort naar de bal',
            'rotation_visible' => 'ja, na balverlies wisselen',
        ],
        'uncertainties' => ['Exacte afmetingen niet goed te zien'],
        'confidence' => 'medium',
        'evidence_items' => [
            ['fact' => '6 spelers zichtbaar', 'frame' => 1, 'certainty' => 'high'],
            ['fact' => 'Oranje hesjes bij 2 spelers', 'frame' => 1, 'certainty' => 'medium'],
        ],
    ]);

    $fakeClient->fakeResponse = [
        'ok' => true,
        'content' => $validJson,
        'usage' => ['prompt_tokens' => 500, 'completion_tokens' => 200, 'total_tokens' => 700],
    ];

    $service = new VisualEvidenceService($fakeClient, $promptBuilder);
    $result = $service->analyseFrames(
        [['data_uri' => 'data:image/jpeg;base64,abc', 'timestamp_formatted' => '00:05', 'timestamp' => 5.0]],
        ['title' => 'Test video', 'url' => 'https://youtube.com/watch?v=abc'],
        'Geef me een rondo oefening',
        'openai/gpt-4o',
        1
    );

    assertTrue($result['ok'], 'Should succeed with valid JSON response');
    assertTrue(is_array($result['visual_facts']), 'Should have visual_facts array');

    $facts = $result['visual_facts'];
    assertSame('4 vs 2 rondo', $facts['setup']['starting_shape'], 'Setup starting_shape should be preserved');
    assertSame('vierkant', $facts['setup']['field_shape'], 'Setup field_shape should be preserved');
    assertSame(2, count($facts['sequence']), 'Should have 2 sequence entries');
    assertSame('medium', $facts['confidence'], 'Confidence should be medium');
    assertSame(2, count($facts['evidence_items']), 'Should have 2 evidence items');
    assertSame(1, count($facts['uncertainties']), 'Should have 1 uncertainty');
};

$tests['VisualEvidenceService normaliseert ontbrekende velden'] = function (): void {
    $fakePdo = new PDO('sqlite::memory:');
    $fakeClient = new FakeVisionOpenRouterClient();
    $promptBuilder = new AiPromptBuilder($fakePdo);

    // Minimal response: only some fields
    $minimalJson = json_encode([
        'setup' => ['player_count' => 4],
        'confidence' => 'low',
    ]);

    $fakeClient->fakeResponse = [
        'ok' => true,
        'content' => $minimalJson,
        'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50, 'total_tokens' => 150],
    ];

    $service = new VisualEvidenceService($fakeClient, $promptBuilder);
    $result = $service->analyseFrames(
        [['data_uri' => 'data:image/jpeg;base64,xyz', 'timestamp_formatted' => '00:10', 'timestamp' => 10.0]],
        ['title' => 'Minimal test'],
        'Test vraag',
        'openai/gpt-4o',
        1
    );

    assertTrue($result['ok'], 'Should succeed even with minimal JSON');
    $facts = $result['visual_facts'];

    // Normalized defaults
    assertSame('4', $facts['setup']['player_count'], 'player_count should be preserved as string');
    assertSame(null, $facts['setup']['starting_shape'], 'Missing starting_shape should be null');
    assertSame([], $facts['setup']['equipment'], 'Missing equipment should be empty array');
    assertSame([], $facts['sequence'], 'Missing sequence should be empty array');
    assertSame([], $facts['evidence_items'], 'Missing evidence_items should be empty array');
    assertSame('low', $facts['confidence'], 'Confidence should be low');
};

$tests['VisualEvidenceService faalt graceful bij ongeldige JSON'] = function (): void {
    $fakePdo = new PDO('sqlite::memory:');
    $fakeClient = new FakeVisionOpenRouterClient();
    $promptBuilder = new AiPromptBuilder($fakePdo);

    $fakeClient->fakeResponse = [
        'ok' => true,
        'content' => 'Dit is helemaal geen JSON...',
        'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20, 'total_tokens' => 120],
    ];

    $service = new VisualEvidenceService($fakeClient, $promptBuilder);
    $result = $service->analyseFrames(
        [['data_uri' => 'data:image/jpeg;base64,xyz', 'timestamp_formatted' => '00:10', 'timestamp' => 10.0]],
        ['title' => 'Test'],
        'Test',
        'openai/gpt-4o',
        1
    );

    assertSame(false, $result['ok'], 'Should fail on invalid JSON');
    assertTrue(str_contains($result['error'], 'geparsed'), 'Error should mention parse failure');
};

$tests['VisualEvidenceService faalt graceful bij lege frames'] = function (): void {
    $fakePdo = new PDO('sqlite::memory:');
    $fakeClient = new FakeVisionOpenRouterClient();
    $promptBuilder = new AiPromptBuilder($fakePdo);

    $service = new VisualEvidenceService($fakeClient, $promptBuilder);
    $result = $service->analyseFrames([], ['title' => 'Test'], 'Test', 'openai/gpt-4o', 1);

    assertSame(false, $result['ok'], 'Should fail with no frames');
    assertTrue(str_contains($result['error'], 'Geen frames'), 'Error should mention no frames');
};

$tests['VisualEvidenceService handelt fenced JSON blok correct af'] = function (): void {
    $fakePdo = new PDO('sqlite::memory:');
    $fakeClient = new FakeVisionOpenRouterClient();
    $promptBuilder = new AiPromptBuilder($fakePdo);

    $fencedJson = "```json\n" . json_encode([
        'setup' => ['starting_shape' => 'driehoek', 'field_shape' => 'driehoek', 'player_count' => '3'],
        'confidence' => 'high',
        'evidence_items' => [['fact' => 'Driehoeksopstelling', 'frame' => 1, 'certainty' => 'high']],
    ]) . "\n```";

    $fakeClient->fakeResponse = [
        'ok' => true,
        'content' => $fencedJson,
        'usage' => ['prompt_tokens' => 200, 'completion_tokens' => 100, 'total_tokens' => 300],
    ];

    $service = new VisualEvidenceService($fakeClient, $promptBuilder);
    $result = $service->analyseFrames(
        [['data_uri' => 'data:image/jpeg;base64,abc', 'timestamp_formatted' => '00:05', 'timestamp' => 5.0]],
        ['title' => 'Fenced test'],
        'Test',
        'openai/gpt-4o',
        1
    );

    assertTrue($result['ok'], 'Should parse fenced JSON successfully');
    assertSame('driehoek', $result['visual_facts']['setup']['starting_shape'], 'Should parse content inside fences');
    assertSame('high', $result['visual_facts']['confidence'], 'Confidence should be high');
};

$tests['VisualEvidenceService parseert JSON met extra tekst en trailing commas'] = function (): void {
    $fakePdo = new PDO('sqlite::memory:');
    $fakeClient = new FakeVisionOpenRouterClient();
    $promptBuilder = new AiPromptBuilder($fakePdo);

    $fakeClient->fakeResponse = [
        'ok' => true,
        'content' => "Analyse:\n{\n  \"setup\": {\n    \"starting_shape\": \"passing circuit\",\n  },\n  \"sequence\": [\n    {\n      \"frame\": 1,\n      \"timestamp\": \"00:12\",\n      \"action\": \"pass en doorbewegen\",\n    }\n  ],\n  \"confidence\": \"medium\",\n}\nEinde.",
        'usage' => ['prompt_tokens' => 180, 'completion_tokens' => 90, 'total_tokens' => 270],
    ];

    $service = new VisualEvidenceService($fakeClient, $promptBuilder);
    $result = $service->analyseFrames(
        [['data_uri' => 'data:image/jpeg;base64,abc', 'timestamp_formatted' => '00:12', 'timestamp' => 12.0]],
        ['title' => 'Extra text test'],
        'Test',
        'openai/gpt-4o',
        1
    );

    assertSame(true, $result['ok'], 'Should recover JSON when the model adds wrapper text');
    assertSame('passing circuit', $result['visual_facts']['setup']['starting_shape'], 'Recovered JSON should preserve setup');
    assertSame('medium', $result['visual_facts']['confidence'], 'Recovered JSON should preserve confidence');
    assertSame(1, count($result['visual_facts']['sequence']), 'Recovered JSON should preserve sequence');
};

// === SourceEvidenceFusionService tests ===

// Helper: build typical text source facts
function buildSampleTextFacts(array $overrides = []): array {
    return array_merge([
        'summary' => 'Rondo 4 tegen 2',
        'setup' => [
            'starting_shape' => '4 aanvallers in een vierkant, 2 verdedigers in het midden',
            'player_structure' => '6 spelers',
            'area' => '10x10 meter vak',
            'equipment' => ['pionnen', 'hesjes'],
        ],
        'sequence' => ['Aanvallers passen de bal rond', 'Verdedigers proberen te onderscheppen', 'Bij balverlies wisselen'],
        'rotation' => 'Verdediger die de bal pakt wisselt met de aanvaller die de bal verloor',
        'rules' => ['Maximaal 2 balcontacten'],
        'coach_cues' => ['Open staan', 'Kijk voor de pass'],
        'recognition_points' => ['vierkant vak', 'rondo', '4v2'],
        'missing_details' => ['Exacte veldbreedte niet bevestigd'],
        'confidence' => 'medium',
        'evidence_items' => [
            ['fact' => 'Rondo opstelling zichtbaar', 'source' => 'transcript', 'snippet' => '...we spelen een rondo...'],
            ['fact' => '6 spelers in het vak', 'source' => 'chapters', 'snippet' => 'Drill 1: rondo 4v2'],
        ],
    ], $overrides);
}

// Helper: build typical visual facts
function buildSampleVisualFacts(array $overrides = []): array {
    return array_merge([
        'setup' => [
            'starting_shape' => 'Vierkante opstelling met spelers op de hoeken',
            'field_shape' => 'vierkant',
            'field_markings' => 'Oranje pionnen als hoekpunten',
            'estimated_dimensions' => '10x10 meter',
            'player_count' => '6',
            'player_roles' => ['aanvallers in wit', 'verdedigers in oranje hesje'],
            'equipment' => ['pionnen', 'hesjes', 'ballen'],
        ],
        'sequence' => [
            ['frame' => 1, 'timestamp' => '00:05', 'action' => 'Spelers staan in vierkant', 'movement_patterns' => 'statisch'],
            ['frame' => 2, 'timestamp' => '00:12', 'action' => 'Bal wordt gespeeld naar rechts', 'movement_patterns' => 'korte pass'],
        ],
        'patterns' => [
            'passing_directions' => 'horizontaal en diagonaal',
            'running_lines' => 'kort naar de bal',
            'rotation_visible' => 'ja, na balverlies wisselen verdedigers',
        ],
        'uncertainties' => ['Exacte afmetingen niet te bepalen door camerahoek'],
        'confidence' => 'medium',
        'evidence_items' => [
            ['fact' => '6 spelers zichtbaar', 'frame' => 1, 'certainty' => 'high'],
            ['fact' => 'Oranje hesjes bij 2 spelers', 'frame' => 1, 'certainty' => 'medium'],
        ],
    ], $overrides);
}

$tests['FusionService combineert tekst en visuele facts correct'] = function (): void {
    $service = new SourceEvidenceFusionService();
    $combined = $service->fuse(buildSampleTextFacts(), buildSampleVisualFacts());

    // Fusion meta
    assertTrue($combined['fusion_meta']['has_text'], 'Should have text');
    assertTrue($combined['fusion_meta']['has_visual'], 'Should have visual');

    // Summary comes from text
    assertSame('Rondo 4 tegen 2', $combined['summary'], 'Summary should come from text');

    // Setup merges both sources
    assertSame('4 aanvallers in een vierkant, 2 verdedigers in het midden', $combined['setup']['starting_shape'],
        'Starting shape should prefer text when both exist');
    assertSame('vierkant', $combined['setup']['field_shape'], 'field_shape should come from visual');
    assertTrue(in_array('pionnen', $combined['setup']['equipment']), 'Equipment should contain text items');
    assertTrue(in_array('ballen', $combined['setup']['equipment']), 'Equipment should contain visual-only items');

    // Sequence has both text and visual entries
    assertTrue(count($combined['sequence']) >= 5, 'Should have text + visual sequence entries');
    $sources = array_column($combined['sequence'], 'source');
    assertTrue(in_array('transcript', $sources), 'Sequence should have transcript entries');
    $hasFrame = false;
    foreach ($sources as $s) {
        if (str_starts_with($s, 'frame')) { $hasFrame = true; break; }
    }
    assertTrue($hasFrame, 'Sequence should have frame entries');

    // Evidence items have origin markers
    $origins = array_column($combined['evidence_items'], 'origin');
    assertTrue(in_array('text', $origins), 'Evidence should include text items');
    assertTrue(in_array('visual', $origins), 'Evidence should include visual items');

    // Visual patterns are included
    assertSame('horizontaal en diagonaal', $combined['visual_patterns']['passing_directions'],
        'Visual patterns should be present');

    // Contradictions is an array (may be empty if no real contradictions)
    assertTrue(is_array($combined['contradictions']), 'Contradictions should be an array');
};

$tests['FusionService zonder visuele facts geeft text-only met provenance'] = function (): void {
    $service = new SourceEvidenceFusionService();
    $textFacts = buildSampleTextFacts();
    $combined = $service->fuse($textFacts, null);

    assertTrue($combined['fusion_meta']['has_text'], 'Should have text');
    assertSame(false, $combined['fusion_meta']['has_visual'], 'Should not have visual');
    assertSame('none', $combined['fusion_meta']['visual_confidence'], 'Visual confidence should be none');
    assertSame([], $combined['contradictions'], 'No contradictions without visual');

    // Original text fields should be preserved
    assertSame('Rondo 4 tegen 2', $combined['summary'], 'Summary should match');
    assertSame('medium', $combined['confidence'], 'Confidence should match text');
};

$tests['FusionService detecteert materiaal-contradictie correct'] = function (): void {
    $service = new SourceEvidenceFusionService();

    $textFacts = buildSampleTextFacts([
        'setup' => [
            'starting_shape' => 'rondo',
            'player_structure' => '6 spelers',
            'area' => '10x10',
            'equipment' => ['hekjes', 'ladders'],
        ],
    ]);

    $visualFacts = buildSampleVisualFacts([
        'setup' => [
            'starting_shape' => 'rondo',
            'field_shape' => 'vierkant',
            'player_count' => '6',
            'player_roles' => [],
            'equipment' => ['pionnen', 'doeltjes'],
        ],
    ]);

    $combined = $service->fuse($textFacts, $visualFacts);
    assertTrue(count($combined['contradictions']) >= 1, 'Should detect equipment contradiction');

    $equipContra = null;
    foreach ($combined['contradictions'] as $c) {
        if ($c['field'] === 'setup.equipment') { $equipContra = $c; break; }
    }
    assertTrue($equipContra !== null, 'Should have setup.equipment contradiction');
    assertTrue(str_contains($equipContra['description'], 'Materiaal verschilt'), 'Description should mention materiaal');
};

$tests['FusionService confidence combineert correct'] = function (): void {
    $service = new SourceEvidenceFusionService();

    // high + high = high
    $r = $service->fuse(
        buildSampleTextFacts(['confidence' => 'high']),
        buildSampleVisualFacts(['confidence' => 'high'])
    );
    assertSame('high', $r['confidence'], 'high + high = high');

    // high + medium = high
    $r = $service->fuse(
        buildSampleTextFacts(['confidence' => 'high']),
        buildSampleVisualFacts(['confidence' => 'medium'])
    );
    assertSame('high', $r['confidence'], 'high + medium = high');

    // medium + medium = medium
    $r = $service->fuse(
        buildSampleTextFacts(['confidence' => 'medium']),
        buildSampleVisualFacts(['confidence' => 'medium'])
    );
    assertSame('medium', $r['confidence'], 'medium + medium = medium');

    // medium + low = medium
    $r = $service->fuse(
        buildSampleTextFacts(['confidence' => 'medium']),
        buildSampleVisualFacts(['confidence' => 'low'])
    );
    assertSame('medium', $r['confidence'], 'medium + low = medium');

    // low + low = low
    $r = $service->fuse(
        buildSampleTextFacts(['confidence' => 'low']),
        buildSampleVisualFacts(['confidence' => 'low'])
    );
    assertSame('low', $r['confidence'], 'low + low = low');
};

$tests['FusionService missing_details bevat visuele uncertainties met prefix'] = function (): void {
    $service = new SourceEvidenceFusionService();
    $combined = $service->fuse(
        buildSampleTextFacts(['missing_details' => ['Veldbreedte niet beschreven']]),
        buildSampleVisualFacts(['uncertainties' => ['Camerahoek onduidelijk']])
    );

    $hasTextMissing = false;
    $hasVisualMissing = false;
    foreach ($combined['missing_details'] as $item) {
        if (str_contains($item, 'Veldbreedte niet beschreven')) { $hasTextMissing = true; }
        if (str_contains($item, '[visueel]') && str_contains($item, 'Camerahoek')) { $hasVisualMissing = true; }
    }
    assertTrue($hasTextMissing, 'Should contain text missing detail');
    assertTrue($hasVisualMissing, 'Should contain visual uncertainty with [visueel] prefix');
};

// ─── VideoSegmentationService tests ──────────────────────────────

$tests['VideoSegmentationService segmenteert chapters in drills en variaties'] = function (): void {
    $service = new VideoSegmentationService();

    $source = [
        'title' => 'Passing Training',
        'duration_seconds' => 300,
        'chapters' => [
            ['timestamp' => '0:00', 'seconds' => 0, 'label' => 'Intro'],
            ['timestamp' => '0:20', 'seconds' => 20, 'label' => 'Rondo 4v2'],
            ['timestamp' => '1:30', 'seconds' => 90, 'label' => 'Variatie met 3 verdedigers'],
            ['timestamp' => '3:00', 'seconds' => 180, 'label' => 'Positiespel 6v4'],
            ['timestamp' => '4:30', 'seconds' => 270, 'label' => 'Samenvatting'],
        ],
        'transcript_excerpt' => 'We gaan nu een variatie toevoegen met drie verdedigers.',
    ];

    $result = $service->segment($source);

    assertTrue(in_array('chapters', $result['meta']['signals_used']), 'Should use chapters');
    assertTrue($result['meta']['segment_count'] >= 3, 'Should have at least 3 segments');

    // Find types
    $types = array_column($result['segments'], 'type');
    assertTrue(in_array('skip', $types), 'Should have skip segment (intro/samenvatting)');
    assertTrue(in_array('drill', $types), 'Should have drill segment');
    assertTrue(in_array('variation', $types), 'Should have variation segment');
};

$tests['VideoSegmentationService behandelt onbekende chaptertitels als losse drills'] = function (): void {
    $service = new VideoSegmentationService();

    $source = [
        'title' => 'Youth training overview',
        'duration_seconds' => 484,
        'chapters' => [
            ['timestamp' => '0:00', 'seconds' => 0, 'label' => 'Intro'],
            ['timestamp' => '0:08', 'seconds' => 8, 'label' => 'Touch the Post Shooting'],
            ['timestamp' => '1:25', 'seconds' => 85, 'label' => 'Switching-Tag'],
            ['timestamp' => '3:01', 'seconds' => 181, 'label' => 'First touch 1v1 | Opponent from the front'],
            ['timestamp' => '4:41', 'seconds' => 281, 'label' => 'Shooting to 1v1 to 2v1 | Transition'],
            ['timestamp' => '6:26', 'seconds' => 386, 'label' => '3x 3v3 Funino-Winner-Stays'],
        ],
        'transcript_excerpt' => '',
    ];

    $result = $service->segment($source);
    $contentSegments = array_values(array_filter(
        $result['segments'],
        static fn(array $segment): bool => ($segment['type'] ?? '') !== 'skip'
    ));

    assertSame(5, count($contentSegments), 'Standalone drill chapter titles should split into separate segments');
    assertSame('Touch the Post Shooting', $contentSegments[0]['title'], 'First drill chapter should stay isolated');
    assertSame('3x 3v3 Funino-Winner-Stays', $contentSegments[4]['title'], 'Last drill chapter should stay isolated');
};

$tests['VideoSegmentationService geeft single segment zonder chapters of visual'] = function (): void {
    $service = new VideoSegmentationService();

    $source = [
        'title' => 'Simpele Oefening',
        'duration_seconds' => 120,
        'chapters' => [],
        'transcript_excerpt' => 'We doen een eenvoudige passoefening.',
    ];

    $result = $service->segment($source);

    assertSame(1, $result['meta']['segment_count'], 'Should be single segment');
    assertSame('drill', $result['segments'][0]['type'], 'Single segment should be drill');
    assertSame(0, $result['segments'][0]['start_seconds'], 'Should start at 0');
    assertSame(120.0, $result['segments'][0]['end_seconds'], 'Should end at duration');
    assertTrue(empty($result['boundary_uncertainties']), 'No boundaries = no uncertainties');
};

$tests['VideoSegmentationService detecteert visuele shifts als fallback'] = function (): void {
    $service = new VideoSegmentationService();

    $source = [
        'title' => 'Training zonder chapters',
        'duration_seconds' => 240,
        'chapters' => [],
        'transcript_excerpt' => '',
    ];

    $visualFacts = [
        'sequence' => [
            ['frame' => 1, 'timestamp' => '00:10', 'action' => 'Spelers passen in rondo', 'movement_patterns' => 'cirkelvormig'],
            ['frame' => 2, 'timestamp' => '00:40', 'action' => 'Spelers passen in rondo', 'movement_patterns' => 'cirkelvormig'],
            ['frame' => 3, 'timestamp' => '01:20', 'action' => 'Nieuwe opstelling met twee doeltjes', 'movement_patterns' => 'lijnvormig'],
            ['frame' => 4, 'timestamp' => '02:00', 'action' => 'Afwerken op doel', 'movement_patterns' => 'naar voren'],
        ],
    ];

    $result = $service->segment($source, $visualFacts);

    assertTrue(in_array('visual', $result['meta']['signals_used']), 'Should use visual shifts');
    assertTrue($result['meta']['segment_count'] >= 2, 'Should detect at least 2 segments from visual shift');
};

$tests['VideoSegmentationService normaliseert diverse chapter-formaten'] = function (): void {
    $service = new VideoSegmentationService();

    // Mix of different chapter formats: seconds, start_seconds, timestamp
    $source = [
        'title' => 'Multi-format chapters',
        'duration_seconds' => 200,
        'chapters' => [
            ['seconds' => 0, 'label' => 'Warmup'],
            ['start_seconds' => 60, 'title' => 'Drill 1: Passing'],
            ['timestamp' => '2:00', 'label' => 'Drill 2: Shooting'],
        ],
        'transcript_excerpt' => '',
    ];

    $result = $service->segment($source);

    assertTrue(in_array('chapters', $result['meta']['signals_used']), 'Should parse all chapter formats');
    assertTrue($result['meta']['segment_count'] >= 2, 'Should create segments from mixed formats');

    // First should be skip (warmup)
    $firstNonSkip = null;
    foreach ($result['segments'] as $seg) {
        if ($seg['type'] !== 'skip') {
            $firstNonSkip = $seg;
            break;
        }
    }
    assertSame('drill', $firstNonSkip['type'], 'First content segment should be drill');
};

$tests['VideoSegmentationService rapporteert boundary uncertainties'] = function (): void {
    $service = new VideoSegmentationService();

    // Visual-only segmentation has medium confidence → should report uncertainties
    $source = [
        'title' => 'Vague video',
        'duration_seconds' => 180,
        'chapters' => [],
        'transcript_excerpt' => '',
    ];

    $visualFacts = [
        'sequence' => [
            ['frame' => 1, 'timestamp' => '00:05', 'action' => 'Rondo oefening'],
            ['frame' => 2, 'timestamp' => '01:30', 'action' => 'Andere oefening op ander veld'],
        ],
    ];

    $result = $service->segment($source, $visualFacts);

    if ($result['meta']['segment_count'] >= 2) {
        assertTrue(!empty($result['boundary_uncertainties']),
            'Visual-only segmentation should report boundary uncertainties');
        assertTrue(isset($result['boundary_uncertainties'][0]['reason']),
            'Uncertainty should have a reason');
    }
};

// ─── Step 35: Segment choice integration tests ──────────────────

$tests['VideoSegmentationService skip-segmenten worden niet als keuze aangeboden'] = function (): void {
    $service = new VideoSegmentationService();

    $source = [
        'title' => 'Training',
        'duration_seconds' => 300,
        'chapters' => [
            ['seconds' => 0, 'label' => 'Intro'],
            ['seconds' => 30, 'label' => 'Rondo 4v2'],
            ['seconds' => 150, 'label' => 'Positiespel 6v3'],
            ['seconds' => 270, 'label' => 'Samenvatting'],
        ],
        'transcript_excerpt' => '',
    ];

    $result = $service->segment($source);
    $contentSegments = array_values(array_filter(
        $result['segments'],
        fn(array $s) => $s['type'] !== 'skip'
    ));

    assertTrue(count($contentSegments) >= 2, 'Should have at least 2 content segments');
    foreach ($contentSegments as $seg) {
        assertTrue($seg['type'] !== 'skip', 'Content segments should not include skip type');
    }
};

$tests['VideoSegmentationService segment-scoping filtert visuele sequence correct'] = function (): void {
    // Simulate what AiController::scopeSourceToSegment does
    $source = [
        'title' => 'Multi drill',
        'duration_seconds' => 240,
        'chapters' => [
            ['seconds' => 0, 'label' => 'Setup'],
            ['seconds' => 60, 'label' => 'Drill 1'],
            ['seconds' => 120, 'label' => 'Drill 2'],
        ],
        'visual_facts' => [
            'sequence' => [
                ['frame' => 1, 'timestamp' => '00:10', 'action' => 'Setup'],
                ['frame' => 2, 'timestamp' => '01:10', 'action' => 'Drill 1 action'],
                ['frame' => 3, 'timestamp' => '02:10', 'action' => 'Drill 2 action'],
                ['frame' => 4, 'timestamp' => '03:30', 'action' => 'Drill 2 continued'],
            ],
        ],
        'visual_frames' => [
            ['timestamp' => 10.0, 'base64' => 'a'],
            ['timestamp' => 70.0, 'base64' => 'b'],
            ['timestamp' => 130.0, 'base64' => 'c'],
            ['timestamp' => 210.0, 'base64' => 'd'],
        ],
    ];

    // Segment 2 = Drill 2 at 120-240s
    $segmentId = 2;
    $segments = [
        ['id' => 1, 'start_seconds' => 0, 'end_seconds' => 120, 'type' => 'drill'],
        ['id' => 2, 'start_seconds' => 120, 'end_seconds' => 240, 'type' => 'drill'],
    ];

    // Manual scoping (mirrors AiController::scopeSourceToSegment logic)
    $segment = null;
    foreach ($segments as $s) {
        if ($s['id'] === $segmentId) { $segment = $s; break; }
    }
    $start = (float)$segment['start_seconds'];
    $end = (float)$segment['end_seconds'];

    // Filter visual sequence
    $filteredSeq = [];
    foreach ($source['visual_facts']['sequence'] as $entry) {
        $parts = array_reverse(explode(':', $entry['timestamp']));
        $ts = 0;
        foreach ($parts as $i => $p) {
            if ($i === 0) $ts += (float)$p;
            elseif ($i === 1) $ts += (float)$p * 60;
        }
        if ($ts >= $start && $ts < $end) {
            $filteredSeq[] = $entry;
        }
    }

    // Filter visual frames
    $filteredFrames = [];
    foreach ($source['visual_frames'] as $frame) {
        $fts = (float)$frame['timestamp'];
        if ($fts >= $start && $fts < $end) {
            $filteredFrames[] = $frame;
        }
    }

    assertSame(2, count($filteredSeq), 'Should keep only frames in segment 2 time range (120-240s)');
    assertSame('Drill 2 action', $filteredSeq[0]['action'], 'First scoped entry should be Drill 2');
    assertSame(2, count($filteredFrames), 'Should keep only visual frames in segment 2 range');
};

$tests['AiController scopeSourceToSegment versmalt metadata-fallback naar gekozen hoofdstuk'] = function (): void {
    $pdo = createAiControllerTestPdo();
    $controller = new AiController($pdo, new FakeChatOpenRouterClient());
    $method = new ReflectionMethod(AiController::class, 'scopeSourceToSegment');
    $method->setAccessible(true);

    $source = [
        'title' => '5 Best Soccer Drills for U8 & U9',
        'duration_seconds' => 484,
        'snippet' => 'Volledige video met meerdere jeugdoefeningen.',
        'transcript_source' => 'metadata_fallback',
        'transcript_excerpt' => "Videofocus: 5 Best Soccer Drills for U8 & U9\nHoofdstukken: 0:08 Touch the Post Shooting | 1:25 Switching-Tag",
        'chapters' => [
            ['timestamp' => '0:08', 'seconds' => 8, 'label' => 'Touch the Post Shooting'],
            ['timestamp' => '1:25', 'seconds' => 85, 'label' => 'Switching-Tag'],
        ],
        'visual_facts' => ['sequence' => []],
        'visual_frames' => [],
    ];
    $segments = [
        [
            'id' => 1,
            'start_seconds' => 8,
            'end_seconds' => 85,
            'title' => 'Touch the Post Shooting',
            'type' => 'drill',
            'chapter_titles' => ['Touch the Post Shooting'],
        ],
        [
            'id' => 2,
            'start_seconds' => 85,
            'end_seconds' => 181,
            'title' => 'Switching-Tag',
            'type' => 'drill',
            'chapter_titles' => ['Switching-Tag'],
        ],
    ];

    $scoped = $method->invoke($controller, $source, $segments, 2);

    assertSame(96, $scoped['duration_seconds'], 'Scoped source should use segment duration');
    assertSame(1, count($scoped['chapters']), 'Scoped source should keep only segment chapter(s)');
    assertSame('Switching-Tag', $scoped['chapters'][0]['label'], 'Scoped source should keep the selected chapter');
    assertSame('', $scoped['transcript_excerpt'], 'Metadata fallback transcript should be rebuilt from scoped chapter context');
    assertTrue(str_contains((string)$scoped['snippet'], 'Switching-Tag'), 'Scoped snippet should mention the selected segment');
    assertTrue(!str_contains((string)$scoped['snippet'], 'Touch the Post'), 'Scoped snippet should drop unrelated chapter labels');
};

$tests['AiController bouwt fallback drawing suggestion uit text suggestion'] = function (): void {
    $pdo = createAiControllerTestPdo();
    $controller = new AiController($pdo, new FakeChatOpenRouterClient());
    $method = new ReflectionMethod(AiController::class, 'buildFallbackDrawingSuggestion');
    $method->setAccessible(true);

    $drawing = $method->invoke($controller, [
        'fields' => [
            'title' => 'Passen en scoren',
            'description' => 'Speel in een klein vak en werk af op twee kleine doeltjes.',
            'min_players' => 4,
            'max_players' => 8,
            'field_type' => 'landscape',
        ],
    ], 'portrait');

    assertTrue(is_array($drawing), 'Fallback drawing should be created from validated text fields');
    assertSame('landscape', (string)($drawing['field_type'] ?? ''), 'Drawing should inherit the text field type');
    assertTrue(!empty($drawing['drawing_data']), 'Fallback drawing should contain drawing_data');
    assertTrue(($drawing['fallback_generated'] ?? false) === true, 'Fallback drawing should be marked as generated');
};

$tests['VideoSegmentationService single segment retourneert geen segmentkeuze'] = function (): void {
    $service = new VideoSegmentationService();

    $source = [
        'title' => 'Enkel drill video',
        'duration_seconds' => 180,
        'chapters' => [
            ['seconds' => 0, 'label' => 'Intro'],
            ['seconds' => 15, 'label' => 'Rondo'],
        ],
        'transcript_excerpt' => '',
    ];

    $result = $service->segment($source);
    $contentSegments = array_values(array_filter(
        $result['segments'],
        fn(array $s) => $s['type'] !== 'skip'
    ));

    // Only 1 content segment after filtering skip → no segment selection needed
    assertTrue(count($contentSegments) <= 1,
        'Single content segment should not trigger segment selection');
};

$passed = 0;
$total = count($tests);

foreach ($tests as $name => $test) {
    try {
        $test();
        $passed++;
        echo "[PASS] {$name}\n";
    } catch (Throwable $e) {
        echo "[FAIL] {$name}: {$e->getMessage()}\n";
    }
}

echo "\nResult: {$passed}/{$total} tests passed.\n";

if ($passed !== $total) {
    exit(1);
}
