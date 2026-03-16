<?php
declare(strict_types=1);

class YouTubeSearchClient {
    private const API_TIMEOUT_SECONDS = 8;
    private const TRANSCRIPT_TIMEOUT_SECONDS = 3;

    public function searchVideos(string $apiKey, string $query, int $maxResults, ?string $relevanceLanguage = null): array {
        $apiKey = trim($apiKey);
        $query = trim($query);
        $maxResults = max(1, min(25, $maxResults));

        if ($apiKey === '') {
            return [
                'ok' => false,
                'error' => 'YouTube API key ontbreekt.',
                'http_status' => 503,
            ];
        }

        if ($query === '') {
            return [
                'ok' => false,
                'error' => 'Zoekquery voor YouTube is leeg.',
                'http_status' => 422,
            ];
        }

        $params = [
            'part' => 'snippet',
            'type' => 'video',
            'safeSearch' => 'strict',
            'maxResults' => $maxResults,
            'q' => $query,
            'key' => $apiKey,
        ];
        $relevanceLanguage = $this->normalizeLanguageCode($relevanceLanguage);
        if ($relevanceLanguage !== null) {
            $params['relevanceLanguage'] = $relevanceLanguage;
        }

        $url = 'https://www.googleapis.com/youtube/v3/search?' . http_build_query($params);

        $transport = $this->performRequest($url, self::API_TIMEOUT_SECONDS);

        if (!$transport['ok']) {
            $error = trim((string)($transport['error'] ?? ''));
            return [
                'ok' => false,
                'error' => $error !== '' ? ('YouTube netwerkfout: ' . $error) : 'Onbekende YouTube netwerkfout.',
                'http_status' => (int)($transport['http_status'] ?? 503) > 0 ? (int)$transport['http_status'] : 503,
            ];
        }

        $httpStatus = (int)($transport['http_status'] ?? 0);
        $rawBody = (string)($transport['body'] ?? '');

        $decoded = json_decode($rawBody, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return [
                'ok' => false,
                'error' => 'YouTube response kon niet gelezen worden.',
                'http_status' => $httpStatus > 0 ? $httpStatus : 500,
            ];
        }

        if ($httpStatus !== 200) {
            $errorMessage = $this->extractErrorMessage($decoded, $httpStatus);
            return [
                'ok' => false,
                'error' => $errorMessage,
                'http_status' => $httpStatus > 0 ? $httpStatus : 503,
            ];
        }

        $itemsById = [];
        foreach (($decoded['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $videoId = trim((string)($item['id']['videoId'] ?? ''));
            if ($videoId === '') {
                continue;
            }

            $snippet = is_array($item['snippet'] ?? null) ? $item['snippet'] : [];
            $itemsById[$videoId] = [
                'external_id' => $videoId,
                'title' => $this->cleanText($snippet['title'] ?? ''),
                'snippet' => $this->cleanText($snippet['description'] ?? ''),
                'channel' => $this->cleanText($snippet['channelTitle'] ?? ''),
                'published_at' => trim((string)($snippet['publishedAt'] ?? '')),
                'url' => 'https://www.youtube.com/watch?v=' . rawurlencode($videoId),
            ];
        }

        if (!empty($itemsById)) {
            // Keep search lightweight: enrich with batch metadata only.
            $detailMap = $this->fetchVideoDetails($apiKey, array_keys($itemsById), false);
            foreach ($detailMap as $videoId => $details) {
                if (!isset($itemsById[$videoId]) || !is_array($details)) {
                    continue;
                }

                if (isset($details['snippet']) && trim((string)$details['snippet']) !== '') {
                    $itemsById[$videoId]['snippet'] = $this->cleanMultilineText($details['snippet']);
                }
                if (isset($details['duration_seconds'])) {
                    $itemsById[$videoId]['duration_seconds'] = (int)$details['duration_seconds'];
                }
                if (isset($details['first_chapter'])) {
                    $itemsById[$videoId]['first_chapter'] = $this->cleanText($details['first_chapter']);
                }
                if (!empty($details['chapters'])) {
                    $itemsById[$videoId]['chapters'] = $details['chapters'];
                }
                if (isset($details['view_count'])) {
                    $itemsById[$videoId]['view_count'] = (int)$details['view_count'];
                }
                if (isset($details['like_count'])) {
                    $itemsById[$videoId]['like_count'] = (int)$details['like_count'];
                }
                if (isset($details['comment_count'])) {
                    $itemsById[$videoId]['comment_count'] = (int)$details['comment_count'];
                }
                if (!empty($details['tags'])) {
                    $itemsById[$videoId]['tags'] = $details['tags'];
                }
                if (!empty($details['category_id'])) {
                    $itemsById[$videoId]['category_id'] = (int)$details['category_id'];
                }
                if (!empty($details['transcript_excerpt'])) {
                    $itemsById[$videoId]['transcript_excerpt'] = $details['transcript_excerpt'];
                }
                if (!empty($details['transcript_source'])) {
                    $itemsById[$videoId]['transcript_source'] = (string)$details['transcript_source'];
                }
            }
        }

        return [
            'ok' => true,
            'items' => array_values($itemsById),
            'http_status' => 200,
        ];
    }

    public function getVideoById(string $apiKey, string $videoId): array {
        $apiKey = trim($apiKey);
        $videoId = trim($videoId);

        if ($apiKey === '') {
            return [
                'ok' => false,
                'error' => 'YouTube API key ontbreekt.',
                'http_status' => 503,
            ];
        }

        if ($videoId === '' || preg_match('/^[A-Za-z0-9_-]{6,20}$/', $videoId) !== 1) {
            return [
                'ok' => false,
                'error' => 'Ongeldige YouTube video-id.',
                'http_status' => 422,
            ];
        }

        $params = [
            'part' => 'snippet,contentDetails,statistics',
            'id' => $videoId,
            'key' => $apiKey,
        ];
        $url = 'https://www.googleapis.com/youtube/v3/videos?' . http_build_query($params);

        $transport = $this->performRequest($url, self::API_TIMEOUT_SECONDS);

        if (!$transport['ok']) {
            $error = trim((string)($transport['error'] ?? ''));
            return [
                'ok' => false,
                'error' => $error !== '' ? ('YouTube netwerkfout: ' . $error) : 'Onbekende YouTube netwerkfout.',
                'http_status' => (int)($transport['http_status'] ?? 503) > 0 ? (int)$transport['http_status'] : 503,
            ];
        }

        $httpStatus = (int)($transport['http_status'] ?? 0);
        $decoded = json_decode((string)($transport['body'] ?? ''), true);
        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'error' => 'YouTube response kon niet gelezen worden.',
                'http_status' => $httpStatus > 0 ? $httpStatus : 500,
            ];
        }

        if ($httpStatus !== 200) {
            return [
                'ok' => false,
                'error' => $this->extractErrorMessage($decoded, $httpStatus),
                'http_status' => $httpStatus > 0 ? $httpStatus : 503,
            ];
        }

        $items = is_array($decoded['items'] ?? null) ? $decoded['items'] : [];
        if (empty($items) || !is_array($items[0])) {
            return [
                'ok' => false,
                'error' => 'YouTube video niet gevonden of niet beschikbaar.',
                'http_status' => 404,
            ];
        }

        $item = $items[0];
        $snippet = is_array($item['snippet'] ?? null) ? $item['snippet'] : [];
        $contentDetails = is_array($item['contentDetails'] ?? null) ? $item['contentDetails'] : [];
        $statistics = is_array($item['statistics'] ?? null) ? $item['statistics'] : [];
        $rawDescription = (string)($snippet['description'] ?? '');
        $description = $this->cleanMultilineText($rawDescription);
        $firstChapter = $this->extractFirstChapterLabel($description);
        $chapters = $this->extractChaptersFromDescription($description);
        $durationSeconds = $this->parseIso8601DurationSeconds(trim((string)($contentDetails['duration'] ?? '')));
        $transcriptEvidence = $this->fetchTranscriptEvidence(
            $videoId,
            $this->cleanText($snippet['title'] ?? ''),
            $description,
            $chapters
        );
        $transcriptExcerpt = $transcriptEvidence['excerpt'];
        $tags = is_array($snippet['tags'] ?? null) ? array_slice(array_map('strval', $snippet['tags']), 0, 15) : [];

        return [
            'ok' => true,
            'item' => [
                'external_id' => $videoId,
                'title' => $this->cleanText($snippet['title'] ?? ''),
                'snippet' => $description,
                'channel' => $this->cleanText($snippet['channelTitle'] ?? ''),
                'published_at' => trim((string)($snippet['publishedAt'] ?? '')),
                'url' => 'https://www.youtube.com/watch?v=' . rawurlencode($videoId),
                'duration_seconds' => $durationSeconds,
                'first_chapter' => $firstChapter,
                'chapters' => $chapters,
                'transcript_excerpt' => $transcriptExcerpt,
                'transcript_source' => (string)($transcriptEvidence['source'] ?? 'none'),
                'view_count' => (int)($statistics['viewCount'] ?? 0),
                'like_count' => (int)($statistics['likeCount'] ?? 0),
                'comment_count' => (int)($statistics['commentCount'] ?? 0),
                'tags' => $tags,
                'category_id' => (int)($snippet['categoryId'] ?? 0),
            ],
            'http_status' => 200,
        ];
    }

    private function fetchVideoDetails(string $apiKey, array $videoIds, bool $includeTranscriptEvidence = false): array {
        $videoIds = array_values(array_unique(array_filter(array_map(
            static fn(mixed $id): string => trim((string)$id),
            $videoIds
        ))));
        if (empty($videoIds)) {
            return [];
        }

        $result = [];
        foreach (array_chunk($videoIds, 50) as $idChunk) {
            $params = [
                'part' => 'snippet,contentDetails,statistics',
                'id' => implode(',', $idChunk),
                'key' => $apiKey,
                'maxResults' => count($idChunk),
            ];

            $url = 'https://www.googleapis.com/youtube/v3/videos?' . http_build_query($params);
            $transport = $this->performRequest($url, self::API_TIMEOUT_SECONDS);
            if (!$transport['ok']) {
                continue;
            }

            $httpStatus = (int)($transport['http_status'] ?? 0);
            if ($httpStatus !== 200) {
                continue;
            }

            $decoded = json_decode((string)($transport['body'] ?? ''), true);
            if (!is_array($decoded)) {
                continue;
            }

            foreach (($decoded['items'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $videoId = trim((string)($item['id'] ?? ''));
                if ($videoId === '') {
                    continue;
                }

                $snippet = is_array($item['snippet'] ?? null) ? $item['snippet'] : [];
                $rawDescription = (string)($snippet['description'] ?? '');
                $description = $this->cleanMultilineText($rawDescription);
                $contentDetails = is_array($item['contentDetails'] ?? null) ? $item['contentDetails'] : [];
                $durationIso = trim((string)($contentDetails['duration'] ?? ''));
                $durationSeconds = $this->parseIso8601DurationSeconds($durationIso);

                $firstChapter = $this->extractFirstChapterLabel($description);
                $chapters = $this->extractChaptersFromDescription($description);
                $enrichedSnippet = $description;
                if ($firstChapter !== null) {
                    $enrichedSnippet = 'Eerste hoofdstuk: ' . $firstChapter . "\n" . $description;
                }

                $statistics = is_array($item['statistics'] ?? null) ? $item['statistics'] : [];
                $tags = is_array($snippet['tags'] ?? null) ? $snippet['tags'] : [];

                $result[$videoId] = [
                    'title' => $this->cleanText($snippet['title'] ?? ''),
                    'snippet' => $enrichedSnippet,
                    'duration_seconds' => $durationSeconds,
                    'first_chapter' => $firstChapter,
                    'chapters' => $chapters,
                    'view_count' => (int)($statistics['viewCount'] ?? 0),
                    'like_count' => (int)($statistics['likeCount'] ?? 0),
                    'comment_count' => (int)($statistics['commentCount'] ?? 0),
                    'tags' => array_map('strval', $tags),
                    'category_id' => (int)($snippet['categoryId'] ?? 0),
                ];
            }
        }

        if ($includeTranscriptEvidence) {
            foreach (array_keys($result) as $videoId) {
                if (!isset($result[$videoId]) || !is_array($result[$videoId])) {
                    continue;
                }

                $transcriptEvidence = $this->fetchTranscriptEvidence(
                    $videoId,
                    (string)($result[$videoId]['title'] ?? ''),
                    (string)($result[$videoId]['snippet'] ?? ''),
                    is_array($result[$videoId]['chapters'] ?? null) ? $result[$videoId]['chapters'] : []
                );
                $excerpt = trim((string)($transcriptEvidence['excerpt'] ?? ''));
                $result[$videoId]['transcript_source'] = (string)($transcriptEvidence['source'] ?? 'none');
                if ($excerpt === '') {
                    continue;
                }
                $result[$videoId]['transcript_excerpt'] = $excerpt;
            }
        }

        return $result;
    }

    private function extractFirstChapterLabel(string $description): ?string {
        $chapters = $this->extractChapterCandidates($description);
        if (empty($chapters)) {
            return null;
        }

        $firstLabel = null;
        foreach ($chapters as $chapter) {
            $label = trim((string)($chapter['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            if ($firstLabel === null) {
                $firstLabel = $label;
            }

            if (preg_match('/\b(oefening|drill|rondo|game|possession|passing|finishing)\b/i', $label) === 1) {
                return $label;
            }
        }

        return $firstLabel;
    }

    private function extractChaptersFromDescription(string $description): array {
        $chapters = [];
        foreach ($this->extractChapterCandidates($description) as $candidate) {
            $timestamp = trim((string)($candidate['timestamp'] ?? ''));
            $label = trim((string)($candidate['label'] ?? ''));
            $seconds = $this->parseTimestampToSeconds($timestamp);
            if ($timestamp === '' || $label === '' || $seconds === null) {
                continue;
            }

            $chapters[] = [
                'chapter_index' => count($chapters) + 1,
                'timestamp' => $timestamp,
                'seconds' => $seconds,
                'label' => $label,
            ];

            if (count($chapters) >= 10) {
                break;
            }
        }

        return $chapters;
    }

    private function extractChapterCandidates(string $description): array {
        $description = trim($description);
        if ($description === '') {
            return [];
        }

        $candidates = [];
        $seen = [];

        $lines = preg_split('/\R+/', $description) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^(\d{1,2}:\d{2}(?::\d{2})?)\s+(.+)$/u', $line, $matches) !== 1) {
                continue;
            }

            $timestamp = trim((string)($matches[1] ?? ''));
            $label = $this->sanitizeChapterLabel((string)($matches[2] ?? ''));
            if ($timestamp === '' || $label === '') {
                continue;
            }
            if (preg_match('/(?:^|\s)\d{1,2}:\d{2}(?::\d{2})?(?:\s|$)/', $label) === 1) {
                // Line appears flattened and contains multiple timestamps.
                continue;
            }

            $key = $timestamp . '|' . strtolower($label);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $candidates[] = ['timestamp' => $timestamp, 'label' => $label];
        }

        if (!empty($candidates)) {
            return $candidates;
        }

        // Fallback for descriptions where line breaks are lost.
        $flat = trim(preg_replace('/\s+/u', ' ', $description) ?? $description);
        if ($flat === '') {
            return [];
        }

        if (preg_match_all('/\d{1,2}:\d{2}(?::\d{2})?/', $flat, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return [];
        }

        $timestamps = is_array($matches[0] ?? null) ? $matches[0] : [];
        $total = count($timestamps);
        for ($i = 0; $i < $total; $i++) {
            $timestamp = trim((string)($timestamps[$i][0] ?? ''));
            $offset = isset($timestamps[$i][1]) ? (int)$timestamps[$i][1] : -1;
            if ($timestamp === '' || $offset < 0) {
                continue;
            }

            $labelStart = $offset + strlen($timestamp);
            $nextOffset = ($i + 1 < $total && isset($timestamps[$i + 1][1]))
                ? (int)$timestamps[$i + 1][1]
                : strlen($flat);
            if ($nextOffset < $labelStart) {
                continue;
            }

            $rawLabel = substr($flat, $labelStart, $nextOffset - $labelStart);
            $label = $this->sanitizeChapterLabel((string)$rawLabel);
            if ($timestamp === '' || $label === '') {
                continue;
            }

            $key = $timestamp . '|' . strtolower($label);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $candidates[] = ['timestamp' => $timestamp, 'label' => $label];
            if (count($candidates) >= 10) {
                break;
            }
        }

        return $candidates;
    }

    private function sanitizeChapterLabel(string $label): string {
        $label = $this->cleanText($label);
        $label = trim(preg_replace('/^[\-:|]+/u', '', $label) ?? $label);
        return $label;
    }

    private function parseIso8601DurationSeconds(string $durationIso): int {
        if ($durationIso === '') {
            return 0;
        }

        if (preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/i', $durationIso, $matches) !== 1) {
            return 0;
        }

        $hours = isset($matches[1]) && $matches[1] !== '' ? (int)$matches[1] : 0;
        $minutes = isset($matches[2]) && $matches[2] !== '' ? (int)$matches[2] : 0;
        $seconds = isset($matches[3]) && $matches[3] !== '' ? (int)$matches[3] : 0;

        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }

    private function parseTimestampToSeconds(string $timestamp): ?int {
        $parts = array_map('trim', explode(':', trim($timestamp)));
        if (count($parts) === 2) {
            if (!ctype_digit($parts[0]) || !ctype_digit($parts[1])) {
                return null;
            }
            return ((int)$parts[0] * 60) + (int)$parts[1];
        }
        if (count($parts) === 3) {
            if (!ctype_digit($parts[0]) || !ctype_digit($parts[1]) || !ctype_digit($parts[2])) {
                return null;
            }
            return ((int)$parts[0] * 3600) + ((int)$parts[1] * 60) + (int)$parts[2];
        }

        return null;
    }

    private function parseSubtitleTimestampToSeconds(string $timestamp): ?float {
        $timestamp = str_replace(',', '.', trim($timestamp));
        if ($timestamp === '') {
            return null;
        }

        if (preg_match('/^(\d+):(\d{2}):(\d{2})(?:\.(\d{1,3}))?$/', $timestamp, $matches) === 1) {
            $hours = (int)$matches[1];
            $minutes = (int)$matches[2];
            $seconds = (int)$matches[3];
            $millis = isset($matches[4]) && $matches[4] !== '' ? (int)str_pad($matches[4], 3, '0') : 0;
            return ($hours * 3600) + ($minutes * 60) + $seconds + ($millis / 1000);
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?:\.(\d{1,3}))?$/', $timestamp, $matches) === 1) {
            $minutes = (int)$matches[1];
            $seconds = (int)$matches[2];
            $millis = isset($matches[3]) && $matches[3] !== '' ? (int)str_pad($matches[3], 3, '0') : 0;
            return ($minutes * 60) + $seconds + ($millis / 1000);
        }

        return null;
    }

    private function fetchTranscriptEvidence(
        string $videoId,
        string $title = '',
        string $description = '',
        array $chapters = []
    ): array {
        $videoId = trim($videoId);
        if ($videoId === '') {
            return ['excerpt' => null, 'source' => 'none'];
        }

        $maxSeconds = 600;
        $maxChars = 3000;

        // Keep transcript probing bounded: a small fixed set of fast requests.
        foreach ($this->buildCaptionRequestCandidates($videoId) as $params) {
            $url = $this->buildTimedTextUrl($params);
            $transport = $this->performRequest($url, self::TRANSCRIPT_TIMEOUT_SECONDS);
            if (!$transport['ok']) {
                continue;
            }

            $body = trim((string)($transport['body'] ?? ''));
            if ($body === '') {
                continue;
            }

            $excerpt = $this->parseTranscriptExcerptFromPayload($body, $maxSeconds, $maxChars);
            if ($excerpt !== null && $excerpt !== '') {
                $isAsr = trim((string)($params['kind'] ?? '')) === 'asr';
                return ['excerpt' => $excerpt, 'source' => $isAsr ? 'captions_fallback' : 'captions'];
            }
        }

        $fallback = $this->buildTranscriptFallbackFromMetadata($title, $description, $chapters, $maxChars);
        if ($fallback !== null && $fallback !== '') {
            return ['excerpt' => $fallback, 'source' => 'metadata_fallback'];
        }

        return ['excerpt' => null, 'source' => 'none'];
    }

    private function discoverCaptionTracks(string $videoId): array {
        $url = $this->buildTimedTextUrl([
            'type' => 'list',
            'v' => $videoId,
        ]);
        $transport = $this->performRequest($url, self::TRANSCRIPT_TIMEOUT_SECONDS);
        if (!$transport['ok']) {
            return [];
        }

        $body = trim((string)($transport['body'] ?? ''));
        if ($body === '') {
            return [];
        }

        return $this->parseCaptionTrackListXml($body);
    }

    private function parseCaptionTrackListXml(string $xml): array {
        if (preg_match_all('/<track\b([^>]*)\/?>/i', $xml, $matches, PREG_SET_ORDER) < 1) {
            return [];
        }

        $tracks = [];
        $seen = [];
        foreach ($matches as $match) {
            $attrs = $this->parseXmlAttributes((string)($match[1] ?? ''));
            $lang = strtolower(trim((string)($attrs['lang_code'] ?? $attrs['lang'] ?? '')));
            if ($lang === '') {
                continue;
            }

            $kind = strtolower(trim((string)($attrs['kind'] ?? '')));
            $name = html_entity_decode((string)($attrs['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $name = trim($name);

            $key = $lang . '|' . $kind . '|' . $name;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $tracks[] = [
                'lang' => $lang,
                'kind' => $kind,
                'name' => $name,
            ];
        }

        return $tracks;
    }

    private function parseXmlAttributes(string $attributeString): array {
        $attributes = [];
        if (preg_match_all('/([a-zA-Z_:][a-zA-Z0-9_\-:.]*)\s*=\s*(["\'])(.*?)\2/s', $attributeString, $matches, PREG_SET_ORDER) < 1) {
            return $attributes;
        }

        foreach ($matches as $match) {
            $name = strtolower(trim((string)($match[1] ?? '')));
            if ($name === '') {
                continue;
            }
            $attributes[$name] = (string)($match[3] ?? '');
        }

        return $attributes;
    }

    private function sortCaptionTracksByPreference(array $tracks): array {
        usort($tracks, static function (array $a, array $b): int {
            $score = static function (array $track): int {
                $lang = strtolower(trim((string)($track['lang'] ?? '')));
                $kind = strtolower(trim((string)($track['kind'] ?? '')));
                $name = trim((string)($track['name'] ?? ''));

                $langScore = match ($lang) {
                    'nl' => 300,
                    'en' => 200,
                    default => 100,
                };
                $asrPenalty = ($kind === 'asr') ? -40 : 10;
                $nameBonus = ($name === '') ? 5 : 0;

                return $langScore + $asrPenalty + $nameBonus;
            };

            return $score($b) <=> $score($a);
        });

        return $tracks;
    }

    private function fetchTranscriptFromTrack(string $videoId, array $track, int $maxSeconds, int $maxChars): ?string {
        $params = [
            'v' => $videoId,
            'lang' => trim((string)($track['lang'] ?? '')),
        ];
        if ($params['lang'] === '') {
            return null;
        }

        $name = trim((string)($track['name'] ?? ''));
        if ($name !== '') {
            $params['name'] = $name;
        }

        $kind = trim((string)($track['kind'] ?? ''));
        if ($kind !== '') {
            $params['kind'] = $kind;
        }

        foreach (['srv3', 'json3', null, 'vtt'] as $fmt) {
            $requestParams = $params;
            if ($fmt !== null) {
                $requestParams['fmt'] = $fmt;
            }

            $url = $this->buildTimedTextUrl($requestParams);
            $transport = $this->performRequest($url, self::TRANSCRIPT_TIMEOUT_SECONDS);
            if (!$transport['ok']) {
                continue;
            }

            $body = trim((string)($transport['body'] ?? ''));
            if ($body === '') {
                continue;
            }

            $excerpt = $this->parseTranscriptExcerptFromPayload($body, $maxSeconds, $maxChars);
            if ($excerpt !== null && $excerpt !== '') {
                return $excerpt;
            }
        }

        return null;
    }

    private function buildCaptionRequestCandidates(string $videoId): array {
        return [
            ['v' => $videoId, 'lang' => 'en'],
            ['v' => $videoId, 'lang' => 'en', 'kind' => 'asr'],
            ['v' => $videoId, 'lang' => 'nl'],
            ['v' => $videoId, 'lang' => 'nl', 'kind' => 'asr'],
        ];
    }

    private function buildTimedTextUrl(array $params): string {
        return 'https://www.youtube.com/api/timedtext?' . http_build_query($params);
    }

    private function parseTranscriptExcerptFromPayload(string $body, int $maxSeconds, int $maxChars): ?string {
        $body = trim($body);
        if ($body === '') {
            return null;
        }

        if ($body[0] === '{' || $body[0] === '[') {
            $jsonResult = $this->parseTranscriptExcerptFromJson($body, $maxSeconds, $maxChars);
            if ($jsonResult !== null && $jsonResult !== '') {
                return $jsonResult;
            }
        }

        if (str_contains($body, '-->')) {
            $vttResult = $this->parseTranscriptExcerptFromVtt($body, $maxSeconds, $maxChars);
            if ($vttResult !== null && $vttResult !== '') {
                return $vttResult;
            }
        }

        if (str_contains($body, '<')) {
            $xmlResult = $this->parseTranscriptExcerptFromXml($body, $maxSeconds, $maxChars);
            if ($xmlResult !== null && $xmlResult !== '') {
                return $xmlResult;
            }
        }

        $plain = $this->normalizeTranscriptChunk($body);
        if ($plain === '') {
            return null;
        }
        if (strlen($plain) > $maxChars) {
            $plain = substr($plain, 0, $maxChars);
        }
        return trim($plain);
    }

    private function parseTranscriptExcerptFromXml(string $xml, int $maxSeconds, int $maxChars): ?string {
        $parts = [];

        if (preg_match_all('/<text\b([^>]*)>(.*?)<\/text>/si', $xml, $legacyMatches, PREG_SET_ORDER) >= 1) {
            foreach ($legacyMatches as $match) {
                $attrs = $this->parseXmlAttributes((string)($match[1] ?? ''));
                $start = isset($attrs['start']) && is_numeric((string)$attrs['start']) ? (float)$attrs['start'] : 0.0;
                if ($start > $maxSeconds) {
                    break;
                }

                $chunk = $this->normalizeTranscriptChunk((string)($match[2] ?? ''));
                if ($chunk === '') {
                    continue;
                }
                $parts[] = $chunk;
                if (strlen(implode(' ', $parts)) >= $maxChars) {
                    break;
                }
            }
        }

        if (empty($parts) && preg_match_all('/<p\b([^>]*)>(.*?)<\/p>/si', $xml, $srv3Matches, PREG_SET_ORDER) >= 1) {
            foreach ($srv3Matches as $match) {
                $attrs = $this->parseXmlAttributes((string)($match[1] ?? ''));
                $startMs = isset($attrs['t']) && is_numeric((string)$attrs['t']) ? (float)$attrs['t'] : 0.0;
                $startSeconds = $startMs / 1000.0;
                if ($startSeconds > $maxSeconds) {
                    break;
                }

                $inner = str_replace(['<br/>', '<br />', '<br>'], ' ', (string)($match[2] ?? ''));
                $segments = [];
                if (preg_match_all('/<s\b[^>]*>(.*?)<\/s>/si', $inner, $segmentMatches, PREG_SET_ORDER) >= 1) {
                    foreach ($segmentMatches as $segmentMatch) {
                        $segment = $this->normalizeTranscriptChunk((string)($segmentMatch[1] ?? ''));
                        if ($segment !== '') {
                            $segments[] = $segment;
                        }
                    }
                }

                $chunk = !empty($segments)
                    ? trim(implode(' ', $segments))
                    : $this->normalizeTranscriptChunk($inner);
                if ($chunk === '') {
                    continue;
                }

                $parts[] = $chunk;
                if (strlen(implode(' ', $parts)) >= $maxChars) {
                    break;
                }
            }
        }

        return $this->finalizeTranscriptParts($parts, $maxChars);
    }

    private function parseTranscriptExcerptFromJson(string $json, int $maxSeconds, int $maxChars): ?string {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }

        $events = [];
        if (is_array($decoded['events'] ?? null)) {
            $events = $decoded['events'];
        } elseif (is_array($decoded['body']['events'] ?? null)) {
            $events = $decoded['body']['events'];
        }
        if (empty($events)) {
            return null;
        }

        $parts = [];
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $startMs = null;
            if (isset($event['tStartMs']) && is_numeric((string)$event['tStartMs'])) {
                $startMs = (float)$event['tStartMs'];
            } elseif (isset($event['t']) && is_numeric((string)$event['t'])) {
                $startMs = (float)$event['t'];
            }
            if ($startMs !== null && ($startMs / 1000.0) > $maxSeconds) {
                break;
            }

            $rawParts = [];
            if (is_array($event['segs'] ?? null)) {
                foreach ($event['segs'] as $segment) {
                    if (!is_array($segment)) {
                        continue;
                    }
                    if (isset($segment['utf8']) && is_string($segment['utf8'])) {
                        $rawParts[] = $segment['utf8'];
                    }
                }
            } elseif (isset($event['utf8']) && is_string($event['utf8'])) {
                $rawParts[] = $event['utf8'];
            }
            if (empty($rawParts)) {
                continue;
            }

            $chunk = $this->normalizeTranscriptChunk(implode(' ', $rawParts));
            if ($chunk === '') {
                continue;
            }
            $parts[] = $chunk;
            if (strlen(implode(' ', $parts)) >= $maxChars) {
                break;
            }
        }

        return $this->finalizeTranscriptParts($parts, $maxChars);
    }

    private function parseTranscriptExcerptFromVtt(string $vtt, int $maxSeconds, int $maxChars): ?string {
        $lines = preg_split('/\R/u', $vtt) ?: [];
        $parts = [];
        $capture = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                $capture = false;
                continue;
            }
            if (stripos($line, 'WEBVTT') === 0) {
                continue;
            }
            if (preg_match('/^\d+$/', $line) === 1) {
                continue;
            }

            if (str_contains($line, '-->')) {
                [$startRaw] = explode('-->', $line, 2);
                $startSeconds = $this->parseSubtitleTimestampToSeconds((string)$startRaw);
                $capture = $startSeconds !== null && $startSeconds <= $maxSeconds;
                continue;
            }

            if (!$capture) {
                continue;
            }

            $chunk = $this->normalizeTranscriptChunk($line);
            if ($chunk === '') {
                continue;
            }

            $parts[] = $chunk;
            if (strlen(implode(' ', $parts)) >= $maxChars) {
                break;
            }
        }

        return $this->finalizeTranscriptParts($parts, $maxChars);
    }

    private function normalizeTranscriptChunk(string $value): string {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = str_replace(["\r", "\n", "\t"], ' ', $decoded);
        $decoded = trim(strip_tags($decoded));
        $decoded = trim(preg_replace('/\s+/u', ' ', $decoded) ?? $decoded);
        return $decoded;
    }

    private function finalizeTranscriptParts(array $parts, int $maxChars): ?string {
        if (empty($parts)) {
            return null;
        }

        $text = trim(implode(' ', $parts));
        if ($text === '') {
            return null;
        }

        if (strlen($text) > $maxChars) {
            $text = substr($text, 0, $maxChars);
        }

        return trim($text);
    }

    private function buildTranscriptFallbackFromMetadata(string $title, string $description, array $chapters, int $maxChars): ?string {
        $parts = [];
        $title = $this->cleanText($title);
        if ($title !== '') {
            $parts[] = 'Videofocus: ' . $title;
        }

        $chapterBits = [];
        foreach ($chapters as $chapter) {
            if (!is_array($chapter)) {
                continue;
            }
            $timestamp = trim((string)($chapter['timestamp'] ?? ''));
            $label = $this->cleanText((string)($chapter['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $chapterBits[] = ($timestamp !== '' ? ($timestamp . ' ') : '') . $label;
            if (count($chapterBits) >= 8) {
                break;
            }
        }
        if (!empty($chapterBits)) {
            $parts[] = 'Hoofdstukken: ' . implode(' | ', $chapterBits);
        }

        $description = $this->cleanMultilineText($description);
        $description = preg_replace('/https?:\/\/\S+/i', ' ', $description) ?? $description;
        $description = preg_replace('/(^|\s)#[\p{L}\p{N}_-]+/u', ' ', $description) ?? $description;
        $sentences = preg_split('/(?<=[.!?])\s+|\R+/u', trim($description)) ?: [];
        $sentenceParts = [];
        foreach ($sentences as $sentence) {
            $sentence = $this->normalizeTranscriptChunk($sentence);
            if ($sentence === '' || strlen($sentence) < 20) {
                continue;
            }
            $sentenceParts[] = $sentence;
            if (count($sentenceParts) >= 8) {
                break;
            }
        }
        if (!empty($sentenceParts)) {
            $parts[] = 'Beschrijving: ' . implode(' ', $sentenceParts);
        }

        if (empty($parts)) {
            return null;
        }

        $fallback = trim(implode("\n", $parts));
        if ($fallback === '') {
            return null;
        }

        if (strlen($fallback) > $maxChars) {
            $fallback = substr($fallback, 0, $maxChars);
        }

        return trim($fallback);
    }

    private function cleanText(mixed $value): string {
        $text = trim((string)$value);
        if ($text === '') {
            return '';
        }

        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $decoded) ?? $decoded);
    }

    private function cleanMultilineText(mixed $value): string {
        $text = trim((string)$value);
        if ($text === '') {
            return '';
        }

        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = preg_split('/\R/u', $decoded) ?: [];
        $cleanLines = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', $line) ?? $line);
            if ($line !== '') {
                $cleanLines[] = $line;
            }
        }

        if (empty($cleanLines)) {
            return '';
        }

        return implode("\n", $cleanLines);
    }

    private function performRequest(string $url, int $timeoutSeconds = self::API_TIMEOUT_SECONDS): array {
        return function_exists('curl_init')
            ? $this->requestViaCurl($url, $timeoutSeconds)
            : $this->requestViaStream($url, $timeoutSeconds);
    }

    private function requestViaCurl(string $url, int $timeoutSeconds = self::API_TIMEOUT_SECONDS): array {
        $ch = curl_init($url);
        if ($ch === false) {
            return [
                'ok' => false,
                'error' => 'Kon geen verbinding opzetten met YouTube.',
                'http_status' => 500,
            ];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => max(1, $timeoutSeconds),
            CURLOPT_HTTPHEADER => [
                'Accept: */*',
            ],
        ]);

        $responseBody = curl_exec($ch);
        $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            return [
                'ok' => false,
                'error' => $curlError !== '' ? $curlError : 'Onbekende netwerkfout.',
                'http_status' => $httpStatus > 0 ? $httpStatus : 503,
            ];
        }

        return [
            'ok' => true,
            'body' => (string)$responseBody,
            'http_status' => $httpStatus,
        ];
    }

    private function requestViaStream(string $url, int $timeoutSeconds = self::API_TIMEOUT_SECONDS): array {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: */*\r\n",
                'timeout' => max(1, $timeoutSeconds),
                'ignore_errors' => true,
            ],
        ]);

        $responseBody = @file_get_contents($url, false, $context);
        $responseHeaderLines = isset($http_response_header) && is_array($http_response_header) ? $http_response_header : [];
        $httpStatus = $this->extractHttpStatus($responseHeaderLines);

        if ($responseBody === false) {
            $lastError = error_get_last();
            return [
                'ok' => false,
                'error' => is_array($lastError) ? (string)($lastError['message'] ?? 'Onbekende netwerkfout.') : 'Onbekende netwerkfout.',
                'http_status' => $httpStatus > 0 ? $httpStatus : 503,
            ];
        }

        return [
            'ok' => true,
            'body' => (string)$responseBody,
            'http_status' => $httpStatus,
        ];
    }

    private function extractHttpStatus(array $headerLines): int {
        foreach ($headerLines as $line) {
            if (preg_match('/^HTTP\/\d+(?:\.\d+)?\s+(\d{3})/i', (string)$line, $matches) === 1) {
                return (int)$matches[1];
            }
        }

        return 0;
    }

    private function extractErrorMessage(array $payload, int $httpStatus): string {
        $apiError = trim((string)($payload['error']['message'] ?? ''));
        if ($apiError !== '') {
            return $apiError;
        }

        return $httpStatus === 403
            ? 'YouTube request geweigerd (controleer quota of API key).'
            : 'YouTube request is mislukt.';
    }

    private function normalizeLanguageCode(?string $code): ?string {
        if ($code === null) {
            return null;
        }

        $normalized = strtolower(trim($code));
        if ($normalized === '') {
            return null;
        }

        // YouTube accepts ISO 639-1 style language hints, keep a strict short format.
        if (!preg_match('/^[a-z]{2}$/', $normalized)) {
            return null;
        }

        return $normalized;
    }
}
