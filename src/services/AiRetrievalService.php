<?php
declare(strict_types=1);

/**
 * Two-step retrieval service:
 * Step 1: searchVideos() — generates search queries via LLM, searches YouTube, returns candidates plus preflight signals.
 * Step 2: enrichSelectedVideo() — fetches full details + transcript for the coach's chosen video.
 */
class AiRetrievalService {
    private YouTubeSearchClient $youtubeClient;
    private OpenRouterClient $openRouterClient;
    private VideoFrameExtractor $frameExtractor;

    public function __construct(
        private PDO $pdo,
        ?YouTubeSearchClient $youtubeClient = null,
        ?OpenRouterClient $openRouterClient = null,
        ?VideoFrameExtractor $frameExtractor = null
    ) {
        $this->youtubeClient = $youtubeClient ?? new YouTubeSearchClient();
        $this->openRouterClient = $openRouterClient ?? new OpenRouterClient($pdo);
        $this->frameExtractor = $frameExtractor ?? new VideoFrameExtractor();
    }

    /**
     * Step 1 of two-step flow: search YouTube for candidate videos.
     * Returns a deduplicated list of candidates with metadata and lightweight technical preflight.
     */
    public function searchVideos(
        string $message,
        ?array $formState,
        array $settings,
        int $userId,
        string $modelId,
        array $chatHistory = []
    ): array {
        $youtubeApiKey = $this->resolveYouTubeApiKey((string)($settings['youtube_api_key_enc'] ?? ''));
        if ($youtubeApiKey === null) {
            return [
                'ok' => false,
                'candidates' => [],
                'warnings' => [],
                'error' => 'Ik kan nu geen video\'s ophalen.',
                'code' => 'youtube_api_key_missing',
                'http_status' => 503,
            ];
        }

        $searchQueries = $this->generateSearchQueries($message, $formState, $modelId, $userId, $chatHistory);
        if (empty($searchQueries)) {
            $searchQueries = [$this->buildFallbackQuery($message)];
        }

        $allVideos = [];
        foreach (array_slice($searchQueries, 0, 3) as $query) {
            $result = $this->youtubeClient->searchVideos($youtubeApiKey, $query, 5, null);
            if ($result['ok'] && !empty($result['items'])) {
                foreach ($result['items'] as $item) {
                    $videoId = (string)($item['external_id'] ?? '');
                    if ($videoId !== '' && !isset($allVideos[$videoId])) {
                        $allVideos[$videoId] = $item;
                    }
                }
            }
        }

        if (empty($allVideos)) {
            return [
                'ok' => false,
                'candidates' => [],
                'warnings' => [],
                'error' => 'Ik vond geen passende video\'s. Omschrijf de oefening iets anders.',
                'code' => 'no_youtube_results',
                'http_status' => 404,
            ];
        }

        $maxCandidates = max(1, min(20, (int)($settings['ai_retrieval_max_candidates'] ?? 10)));
        $candidates = array_slice(array_values($allVideos), 0, $maxCandidates);
        $candidates = $this->attachCachedPreviewDataToVideos($candidates);

        return [
            'ok' => true,
            'candidates' => $candidates,
            'warnings' => $this->buildCandidatePreflightWarnings($candidates),
        ];
    }

    /**
     * Step 2 of two-step flow: enrich a selected video with full details including transcript.
     */
    public function enrichSelectedVideo(string $videoId, array $settings): array {
        return $this->fetchDirectVideo($videoId, $settings);
    }

    /**
     * Handle direct YouTube URL: user pasted a video link.
     */
    public function fetchDirectVideo(string $videoId, array $settings): array {
        $cachedSource = $this->loadCachedSource('youtube', $videoId);
        if ($cachedSource !== null && $this->isUsableDirectVideoCache($cachedSource)) {
            if ($this->needsTechnicalPreflightRefresh($cachedSource['technical_preflight'] ?? null)) {
                $cachedSource = $this->attachTechnicalPreflight($cachedSource, $settings);
                $this->storeSourceCache($cachedSource);
            }
            return [
                'ok' => true,
                'source' => $cachedSource,
                'sources_used' => [$cachedSource],
                'warnings' => [],
            ];
        }

        $youtubeApiKey = $this->resolveYouTubeApiKey((string)($settings['youtube_api_key_enc'] ?? ''));
        if ($youtubeApiKey === null) {
            return ['ok' => false, 'source' => null, 'sources_used' => [], 'warnings' => [], 'error' => 'Ik kan deze video nu niet openen.', 'http_status' => 503];
        }

        $result = $this->youtubeClient->getVideoById($youtubeApiKey, $videoId);
        if (!$result['ok']) {
            return ['ok' => false, 'source' => null, 'sources_used' => [], 'warnings' => [], 'error' => 'Ik kan deze video nu niet openen.', 'http_status' => $result['http_status'] ?? 404];
        }

        $source = $this->buildSourceArray($result['item']);
        $source['cache_scope'] = 'full_video';
        $source = $this->attachTechnicalPreflight($source, $settings);
        $this->storeSourceCache($source);

        return [
            'ok' => true,
            'source' => $source,
            'sources_used' => [$source],
            'warnings' => [],
        ];
    }

    public function persistSourceCache(array $source): void {
        $this->storeSourceCache($source);
    }

    // ── Step 1: Generate search queries via LLM ────────────────────

    private function generateSearchQueries(string $message, ?array $formState, string $modelId, int $userId, array $chatHistory = []): array {
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

        $systemPrompt = <<<'PROMPT'
Je bent een assistent die zoekqueries maakt voor YouTube om voetbaltraining-video's te vinden.

Gegeven een coachvraag, genereer 2-3 Engelse YouTube-zoekqueries die relevante trainingsvideo's zouden vinden.
Focus op concrete oefenvormen/drills, niet op wedstrijdhighlights of interviews.
Voeg leeftijdsgroep-termen toe als die in de vraag staan (bijv. U9, U12, youth).

BELANGRIJK: als er eerdere berichten in het gesprek staan, combineer dan ALLE context tot de best mogelijke queries. Een vervolgbericht voegt vaak specificaties toe (leeftijd, aantal spelers, type oefening, niveau). Bouw je queries op basis van het TOTAALBEELD van wat de coach zoekt, niet alleen op het laatste bericht.

Antwoord ALLEEN met een JSON-array van strings, geen uitleg.
Voorbeeld: ["youth football passing drill U10", "soccer rondo exercise kids"]
PROMPT;

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // Include relevant chat history for context
        foreach ($chatHistory as $historyMsg) {
            $role = (string)($historyMsg['role'] ?? '');
            $content = trim((string)($historyMsg['content'] ?? ''));
            if (in_array($role, ['user', 'assistant'], true) && $content !== '') {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }

        $userContent = 'Coachvraag: ' . $message . $formContext;
        $messages[] = ['role' => 'user', 'content' => $userContent];

        try {
            $response = $this->openRouterClient->chatCompletion(
                $messages,
                $modelId,
                $userId
            );
        } catch (Throwable $e) {
            error_log('[AI] generateSearchQueries failed: ' . $e->getMessage());
            return [];
        }

        if (!($response['ok'] ?? false)) {
            return [];
        }

        $content = trim((string)($response['content'] ?? ''));
        return $this->parseJsonStringArray($content);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    private function buildSourceArray(array $item): array {
        $chapters = is_array($item['chapters'] ?? null) ? $item['chapters'] : [];
        $snippet = (string)($item['snippet'] ?? '');
        $transcriptExcerpt = (string)($item['transcript_excerpt'] ?? '');
        $technicalPreflight = $this->normalizeTechnicalPreflight(
            is_array($item['technical_preflight'] ?? null) ? $item['technical_preflight'] : null,
            $item
        );

        return [
            'provider' => 'youtube',
            'external_id' => (string)($item['external_id'] ?? ''),
            'title' => (string)($item['title'] ?? ''),
            'url' => (string)($item['url'] ?? ''),
            'snippet' => $snippet,
            'channel' => (string)($item['channel'] ?? ''),
            'duration_seconds' => (int)($item['duration_seconds'] ?? 0),
            'chapters' => $chapters,
            'transcript_excerpt' => $transcriptExcerpt,
            'transcript_source' => (string)($item['transcript_source'] ?? 'none'),
            'cache_scope' => trim((string)($item['cache_scope'] ?? '')),
            'technical_preflight' => $technicalPreflight,
        ];
    }

    protected function resolveYouTubeApiKey(string $encryptedKey): ?string {
        if (trim($encryptedKey) === '') {
            return null;
        }
        try {
            $decrypted = Encryption::decrypt($encryptedKey);
            return trim($decrypted) !== '' ? trim($decrypted) : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function buildFallbackQuery(string $message): string {
        $translations = [
            'warming up' => 'warm up drill', 'rondo' => 'rondo',
            'positiespel' => 'possession game', 'afwerken' => 'finishing drill',
            'passen' => 'passing drill', 'dribbelen' => 'dribbling drill',
            'verdedigen' => 'defending drill', 'aanvallen' => 'attacking drill',
            'conditioneel' => 'conditioning drill', 'traptechniek' => 'shooting technique',
        ];

        $terms = [];
        foreach ($translations as $nl => $en) {
            if (stripos($message, $nl) !== false) {
                $terms[] = $en;
            }
        }

        if (preg_match('/\bJO(\d{1,2})\b/i', $message, $m)) {
            $terms[] = 'U' . $m[1];
        }

        if (empty($terms)) {
            $terms = ['youth football training drill'];
        } else {
            array_unshift($terms, 'football');
        }

        return implode(' ', $terms);
    }

    public function extractYouTubeVideoIds(string $text): array {
        $ids = [];
        if (preg_match_all('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]{6,20})/', $text, $matches)) {
            foreach ($matches[1] as $id) {
                $clean = trim($id);
                if ($clean !== '' && !in_array($clean, $ids, true)) {
                    $ids[] = $clean;
                }
            }
        }
        return $ids;
    }

    private function parseJsonStringArray(string $content): array {
        if (preg_match('/\[.*\]/s', $content, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                $result = [];
                foreach ($decoded as $item) {
                    if (is_string($item) && trim($item) !== '') {
                        $result[] = trim($item);
                    }
                }
                return $result;
            }
        }
        return [];
    }

    private function loadCachedSource(string $provider, string $externalId): ?array {
        $provider = trim($provider);
        $externalId = trim($externalId);
        if ($provider === '' || $externalId === '') {
            return null;
        }
        try {
            $stmt = $this->pdo->prepare(
                'SELECT title, url, snippet, metadata_json
                 FROM ai_source_cache
                 WHERE provider = :provider
                   AND external_id = :external_id
                   AND expires_at > CURRENT_TIMESTAMP
                 LIMIT 1'
            );
            $stmt->execute([
                ':provider' => $provider,
                ':external_id' => $externalId,
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return null;
            }

            $metadata = json_decode((string)($row['metadata_json'] ?? ''), true);
            if (!is_array($metadata)) {
                return null;
            }

            $source = [
                'provider' => $provider,
                'external_id' => $externalId,
                'title' => trim((string)($metadata['title'] ?? $row['title'] ?? '')),
                'url' => trim((string)($metadata['url'] ?? $row['url'] ?? '')),
                'snippet' => trim((string)($metadata['snippet'] ?? $row['snippet'] ?? '')),
                'channel' => trim((string)($metadata['channel'] ?? '')),
                'duration_seconds' => (int)($metadata['duration_seconds'] ?? 0),
                'chapters' => is_array($metadata['chapters'] ?? null) ? $metadata['chapters'] : [],
                'transcript_excerpt' => trim((string)($metadata['transcript_excerpt'] ?? '')),
                'transcript_source' => trim((string)($metadata['transcript_source'] ?? 'none')),
                'cache_scope' => trim((string)($metadata['cache_scope'] ?? '')),
            ];
            $source['technical_preflight'] = $this->normalizeTechnicalPreflight(
                is_array($metadata['technical_preflight'] ?? null) ? $metadata['technical_preflight'] : null,
                $source
            );
            $sourceEvidence = $this->normalizeCachedSourceEvidence(
                is_array($metadata['source_evidence'] ?? null) ? $metadata['source_evidence'] : null,
                $source
            );
            if ($sourceEvidence !== null) {
                $source['source_evidence'] = $sourceEvidence;
            }

            if ($source['title'] === '' || $source['external_id'] === '') {
                return null;
            }

            return $source;
        } catch (Throwable) {
            return null;
        }
    }

    private function storeSourceCache(array $source): void {
        $provider = trim((string)($source['provider'] ?? ''));
        $externalId = trim((string)($source['external_id'] ?? ''));
        $title = trim((string)($source['title'] ?? ''));
        if ($provider === '' || $externalId === '' || $title === '') {
            return;
        }
        try {
            $cacheScope = $this->inferCacheScope($source);
            $metadata = [
                'title' => $title,
                'url' => trim((string)($source['url'] ?? '')),
                'snippet' => trim((string)($source['snippet'] ?? '')),
                'channel' => trim((string)($source['channel'] ?? '')),
                'duration_seconds' => (int)($source['duration_seconds'] ?? 0),
                'chapters' => is_array($source['chapters'] ?? null) ? $source['chapters'] : [],
                'transcript_excerpt' => trim((string)($source['transcript_excerpt'] ?? '')),
                'transcript_source' => trim((string)($source['transcript_source'] ?? 'none')),
                'cache_scope' => $cacheScope,
                'technical_preflight' => $this->normalizeTechnicalPreflight(
                    is_array($source['technical_preflight'] ?? null) ? $source['technical_preflight'] : null,
                    $source
                ),
            ];
            $sourceEvidence = $this->normalizeCachedSourceEvidence(
                is_array($source['source_evidence'] ?? null) ? $source['source_evidence'] : null,
                $source
            );
            if ($sourceEvidence !== null) {
                $metadata['source_evidence'] = $sourceEvidence;
            }

            $stmt = $this->pdo->prepare(
                "INSERT INTO ai_source_cache (provider, external_id, title, url, snippet, metadata_json, fetched_at, expires_at)
                 VALUES (:provider, :external_id, :title, :url, :snippet, :metadata_json, CURRENT_TIMESTAMP, datetime('now', '+7 days'))
                 ON CONFLICT(provider, external_id) DO UPDATE SET
                    title = excluded.title,
                    url = excluded.url,
                    snippet = excluded.snippet,
                    metadata_json = excluded.metadata_json,
                    fetched_at = excluded.fetched_at,
                    expires_at = excluded.expires_at"
            );
            $stmt->execute([
                ':provider' => $provider,
                ':external_id' => $externalId,
                ':title' => $title,
                ':url' => trim((string)($source['url'] ?? '')),
                ':snippet' => trim((string)($source['snippet'] ?? '')),
                ':metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable) {
            // Cache miss/hit is an optimization; generation should continue without it.
        }
    }

    private function attachCachedPreviewDataToVideos(array $videos): array {
        $result = [];

        foreach ($videos as $video) {
            if (!is_array($video)) {
                continue;
            }

            $video['technical_preflight'] = $this->normalizeTechnicalPreflight(
                is_array($video['technical_preflight'] ?? null) ? $video['technical_preflight'] : null,
                $video
            );

            $videoId = trim((string)($video['external_id'] ?? ''));
            if ($videoId === '') {
                $result[] = $video;
                continue;
            }

            $cached = $this->loadCachedSource('youtube', $videoId);
            if ($cached !== null) {
                if (trim((string)($video['transcript_source'] ?? 'none')) === 'none'
                    && trim((string)($cached['transcript_source'] ?? 'none')) !== 'none') {
                    $video['transcript_source'] = (string)$cached['transcript_source'];
                }
                if (trim((string)($video['transcript_excerpt'] ?? '')) === ''
                    && trim((string)($cached['transcript_excerpt'] ?? '')) !== '') {
                    $video['transcript_excerpt'] = (string)$cached['transcript_excerpt'];
                }
                if (empty($video['chapters']) && !empty($cached['chapters'])) {
                    $video['chapters'] = $cached['chapters'];
                }
                if ((int)($video['duration_seconds'] ?? 0) <= 0 && (int)($cached['duration_seconds'] ?? 0) > 0) {
                    $video['duration_seconds'] = (int)$cached['duration_seconds'];
                }
                if (!empty($cached['technical_preflight'])) {
                    $video['technical_preflight'] = $this->normalizeTechnicalPreflight(
                        is_array($cached['technical_preflight'] ?? null) ? $cached['technical_preflight'] : null,
                        $video
                    );
                }
                if (!empty($cached['cache_scope'])) {
                    $video['cache_scope'] = (string)$cached['cache_scope'];
                }
            }

            $result[] = $video;
        }

        return $result;
    }

    private function attachTechnicalPreflight(array $video, array $settings): array {
        $videoId = trim((string)($video['external_id'] ?? ''));
        $existing = $this->normalizeTechnicalPreflight(
            is_array($video['technical_preflight'] ?? null) ? $video['technical_preflight'] : null,
            $video
        );
        $cookiesPath = $this->resolveConfiguredYtDlpCookiesPath($settings);

        if ($videoId === '') {
            $video['technical_preflight'] = $existing;
            return $video;
        }

        if (!$existing['checked'] || $this->needsTechnicalPreflightRefresh($existing)) {
            $probe = $this->frameExtractor->probeAvailability($videoId, $cookiesPath);
            $existing = $this->mergeProbeIntoTechnicalPreflight($existing, $probe, $video);
        }

        $video['technical_preflight'] = $existing;
        return $video;
    }

    private function mergeProbeIntoTechnicalPreflight(array $existing, array $probe, array $video): array {
        $merged = $this->normalizeTechnicalPreflight($existing, $video);
        $downloadable = $probe['downloadable_via_ytdlp'] ?? null;
        $probeErrorCode = trim((string)($probe['error_code'] ?? ''));
        $probeAttempts = $this->normalizeAvailabilityAttempts(
            is_array($probe['attempts'] ?? null) ? $probe['attempts'] : []
        );

        if (
            $probeErrorCode === 'network_error'
            && (($merged['downloadable_via_ytdlp'] ?? null) === true || !in_array(trim((string)($merged['status'] ?? '')), ['', 'network_error', 'unknown_error'], true))
        ) {
            $merged['checked'] = (bool)($probe['checked'] ?? false);
            $merged['preflight_checked_at'] = gmdate('c');
            $merged['attempts'] = $probeAttempts;
            return $merged;
        }

        $merged['checked'] = (bool)($probe['checked'] ?? false);
        $merged['downloadable_via_ytdlp'] = is_bool($downloadable) ? $downloadable : null;
        $merged['auth_required'] = (bool)($probe['auth_required'] ?? false);
        $merged['error_code'] = $probeErrorCode;
        $merged['error'] = trim((string)($probe['error'] ?? ''));
        $merged['status'] = $merged['downloadable_via_ytdlp'] === true
            ? 'ok'
            : ($merged['error_code'] !== '' ? $merged['error_code'] : 'unknown_error');
        $merged['duration_seconds'] = max(
            0,
            (int)($video['duration_seconds'] ?? 0),
            (int)($probe['duration_seconds'] ?? 0)
        );
        $merged['preflight_checked_at'] = gmdate('c');
        $merged['used_cookies'] = (bool)($probe['used_cookies'] ?? false);
        $merged['attempts'] = $probeAttempts;

        return $merged;
    }

    private function normalizeTechnicalPreflight(?array $preflight, array $video): array {
        $preflight = is_array($preflight) ? $preflight : [];
        $chapterCount = is_array($video['chapters'] ?? null)
            ? count($video['chapters'])
            : max(0, (int)($preflight['chapter_count'] ?? 0));
        $transcriptSource = trim((string)($video['transcript_source'] ?? ($preflight['transcript_source'] ?? 'none')));
        $downloadable = $preflight['downloadable_via_ytdlp'] ?? null;

        return [
            'checked' => !empty($preflight['checked']),
            'downloadable_via_ytdlp' => is_bool($downloadable) ? $downloadable : null,
            'auth_required' => !empty($preflight['auth_required']),
            'status' => trim((string)($preflight['status'] ?? '')),
            'error_code' => trim((string)($preflight['error_code'] ?? '')),
            'error' => trim((string)($preflight['error'] ?? '')),
            'duration_seconds' => max(0, (int)($preflight['duration_seconds'] ?? $video['duration_seconds'] ?? 0)),
            'chapter_count' => $chapterCount,
            'transcript_source' => $transcriptSource !== '' ? $transcriptSource : 'none',
            'metadata_only' => array_key_exists('metadata_only', $preflight)
                ? (bool)$preflight['metadata_only']
                : $this->isMetadataOnlySource($transcriptSource, $chapterCount),
            'preflight_checked_at' => trim((string)($preflight['preflight_checked_at'] ?? '')),
            'used_cookies' => !empty($preflight['used_cookies']),
            'attempts' => $this->normalizeAvailabilityAttempts(
                is_array($preflight['attempts'] ?? null) ? $preflight['attempts'] : []
            ),
        ];
    }

    private function normalizeAvailabilityAttempts(array $attempts): array {
        $normalized = [];

        foreach ($attempts as $attempt) {
            if (!is_array($attempt)) {
                continue;
            }

            $stage = trim((string)($attempt['stage'] ?? ''));
            $mode = trim((string)($attempt['mode'] ?? ''));
            if ($stage === '' || $mode === '') {
                continue;
            }

            $errorCode = $attempt['error_code'] ?? null;
            $normalized[] = [
                'stage' => $stage,
                'mode' => $mode,
                'attempted' => !array_key_exists('attempted', $attempt) || (bool)$attempt['attempted'],
                'used_cookies' => !empty($attempt['used_cookies']),
                'ok' => !empty($attempt['ok']),
                'auth_required' => !empty($attempt['auth_required']),
                'error_code' => is_string($errorCode) && trim($errorCode) !== '' ? trim($errorCode) : null,
                'error' => trim((string)($attempt['error'] ?? '')),
                'duration_seconds' => max(0, (int)($attempt['duration_seconds'] ?? 0)),
            ];
        }

        return $normalized;
    }

    private function inferCacheScope(array $source): string {
        $scope = trim((string)($source['cache_scope'] ?? ''));
        if ($scope !== '') {
            return $scope;
        }

        $transcriptSource = trim((string)($source['transcript_source'] ?? 'none'));
        if (in_array($transcriptSource, ['captions', 'captions_fallback'], true)) {
            return 'full_video';
        }

        return 'search_preview';
    }

    private function isUsableDirectVideoCache(array $source): bool {
        $snippet = strtolower(trim((string)($source['snippet'] ?? '')));
        if ($snippet !== '' && str_starts_with($snippet, 'gekozen videodeel:')) {
            return false;
        }

        $scope = trim((string)($source['cache_scope'] ?? ''));
        if ($scope === 'full_video') {
            return true;
        }

        $transcriptSource = trim((string)($source['transcript_source'] ?? 'none'));
        return in_array($transcriptSource, ['captions', 'captions_fallback'], true);
    }

    private function normalizeCachedSourceEvidence(?array $sourceEvidence, array $source): ?array {
        if (!is_array($sourceEvidence)) {
            return null;
        }

        $level = strtolower(trim((string)($sourceEvidence['level'] ?? 'low')));
        if (!in_array($level, ['low', 'medium', 'high'], true)) {
            $level = 'low';
        }

        $signals = [];
        foreach (is_array($sourceEvidence['signals'] ?? null) ? $sourceEvidence['signals'] : [] as $signal) {
            $text = trim((string)$signal);
            if ($text !== '') {
                $signals[] = $text;
            }
        }

        $blockingReasons = [];
        foreach (is_array($sourceEvidence['blocking_reasons'] ?? null) ? $sourceEvidence['blocking_reasons'] : [] as $reason) {
            $text = trim((string)$reason);
            if ($text !== '') {
                $blockingReasons[] = $text;
            }
        }

        return [
            'score' => round(max(0.0, min(1.0, (float)($sourceEvidence['score'] ?? 0.0))), 2),
            'level' => $level,
            'is_sufficient' => !empty($sourceEvidence['is_sufficient']),
            'transcript_source' => trim((string)($sourceEvidence['transcript_source'] ?? $source['transcript_source'] ?? 'none')),
            'transcript_chars' => max(0, (int)($sourceEvidence['transcript_chars'] ?? 0)),
            'chapter_count' => max(0, (int)($sourceEvidence['chapter_count'] ?? 0)),
            'snippet_chars' => max(0, (int)($sourceEvidence['snippet_chars'] ?? 0)),
            'visual_frame_count' => max(0, (int)($sourceEvidence['visual_frame_count'] ?? 0)),
            'visual_status' => trim((string)($sourceEvidence['visual_status'] ?? 'unknown')),
            'visual_confidence' => trim((string)($sourceEvidence['visual_confidence'] ?? 'none')),
            'signals' => array_slice(array_values(array_unique($signals)), 0, 5),
            'blocking_reasons' => array_slice(array_values(array_unique($blockingReasons)), 0, 5),
        ];
    }

    private function isMetadataOnlySource(string $transcriptSource, int $chapterCount): bool {
        return $chapterCount === 0
            && in_array(trim($transcriptSource), ['none', 'metadata_fallback'], true);
    }

    private function needsTechnicalPreflightRefresh(mixed $preflight): bool {
        if (!is_array($preflight) || empty($preflight['checked'])) {
            return true;
        }

        $checkedAt = trim((string)($preflight['preflight_checked_at'] ?? ''));
        if ($checkedAt === '') {
            return true;
        }

        $timestamp = strtotime($checkedAt);
        if ($timestamp === false) {
            return true;
        }

        return $timestamp < (time() - 6 * 3600);
    }

    private function buildCandidatePreflightWarnings(array $candidates): array {
        $warnings = [];
        $downloadable = 0;
        $authRequired = 0;
        $networkUncertain = 0;

        foreach ($candidates as $candidate) {
            $preflight = is_array($candidate['technical_preflight'] ?? null) ? $candidate['technical_preflight'] : [];
            if (($preflight['downloadable_via_ytdlp'] ?? null) === true) {
                $downloadable++;
            }
            if (!empty($preflight['auth_required']) && ($preflight['downloadable_via_ytdlp'] ?? null) !== true) {
                $authRequired++;
            }
            if (trim((string)($preflight['status'] ?? '')) === 'network_error') {
                $networkUncertain++;
            }
        }

        if ($downloadable === 0 && $networkUncertain > 0 && !empty($candidates)) {
            $warnings[] = 'Sommige video\'s kon ik nog niet goed controleren. Je kunt ze wel proberen.';
        } elseif ($downloadable === 0 && !empty($candidates)) {
            $warnings[] = 'Geen van deze video\'s is nu direct klaar voor gebruik. Kies rustig de beste match.';
        } elseif ($authRequired > 0) {
            $warnings[] = 'Sommige video\'s vragen extra toegang. Daarom raad ik die niet meteen aan.';
        }

        return $warnings;
    }

    private function resolveConfiguredYtDlpCookiesPath(array $settings): ?string {
        $path = trim((string)($settings['ai_ytdlp_cookies_path'] ?? ''));
        return $path !== '' ? $path : null;
    }
}
