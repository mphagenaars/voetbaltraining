<?php
declare(strict_types=1);

/**
 * Segments a training video into separate drills and variations.
 *
 * Uses chapters, transcript content, and visual evidence to detect
 * segment boundaries. Each segment is classified as 'drill' or 'variation'.
 * Uncertainty in segment boundaries is explicitly reported.
 */
class VideoSegmentationService
{
    /** Merge boundaries closer than this many seconds */
    private const BOUNDARY_MERGE_THRESHOLD = 8;

    /** Keywords indicating a new drill/exercise */
    private const DRILL_KEYWORDS = [
        'drill', 'oefening', 'exercise', 'activiteit', 'spelvorm',
        'rondo', 'positiespel', 'partijvorm', 'keeperstraining',
    ];

    /** Keywords indicating a variation/progression */
    private const VARIATION_KEYWORDS = [
        'variatie', 'variation', 'progressie', 'progression',
        'uitbreiding', 'moeilijker', 'makkelijker', 'aanpassing',
        'level', 'niveau', 'fase',
    ];

    /** Keywords for structural chapters that continue the same drill */
    private const CONTINUATION_KEYWORDS = [
        'setup', 'opstelling', 'organisatie', 'organization',
        'uitleg', 'explanation', 'demo', 'demonstration',
        'uitvoering', 'coaching', 'coach', 'coaching points',
        'regels', 'rules', 'tips', 'punten', 'details',
        'voorbeeld', 'example', 'startpositie',
    ];

    /** Keywords for non-drill segments (intro/outro) */
    private const SKIP_KEYWORDS = [
        'intro', 'inleiding', 'outro', 'samenvatting', 'afsluiting',
        'warming up', 'warmup', 'warm-up', 'cooling down', 'cooldown',
        'cool-down', 'welkom', 'welcome', 'subscribe', 'abonneer',
    ];

    /**
     * Segment a video source into drills and variations.
     *
     * @param array      $source      Enriched video source (chapters, transcript, duration)
     * @param array|null $visualFacts Visual facts from VisualEvidenceService
     * @return array{segments: array, boundary_uncertainties: array, meta: array}
     */
    public function segment(array $source, ?array $visualFacts = null): array
    {
        $duration = max(0, (int)($source['duration_seconds'] ?? 0));
        $chapters = $this->normalizeChapters($source['chapters'] ?? [], $duration);
        $transcript = trim((string)($source['transcript_excerpt'] ?? ''));
        $sequence = ($visualFacts['sequence'] ?? []);

        $signalsUsed = [];

        // 1. Chapter-based segmentation (strongest signal)
        $chapterSegments = [];
        if (count($chapters) >= 2) {
            $chapterSegments = $this->segmentByChapters($chapters, $duration);
            $signalsUsed[] = 'chapters';
        }

        // 2. Visual shift detection
        $visualBoundaries = [];
        if (!empty($sequence)) {
            $visualBoundaries = $this->detectVisualShifts($sequence);
            if (!empty($visualBoundaries)) {
                $signalsUsed[] = 'visual';
            }
        }

        // 3. Transcript transition detection (supporting evidence, not boundaries by itself)
        $transcriptSignals = [];
        if ($transcript !== '') {
            $transcriptSignals = $this->detectTranscriptTransitions($transcript);
            if (!empty($transcriptSignals)) {
                $signalsUsed[] = 'transcript';
            }
        }

        // If chapters gave us segments, enrich them with visual/transcript evidence
        if (!empty($chapterSegments)) {
            $segments = $this->enrichSegmentsWithEvidence(
                $chapterSegments, $visualBoundaries, $transcriptSignals, $sequence
            );
        } elseif (!empty($visualBoundaries)) {
            // Fall back to visual-only segmentation
            $segments = $this->segmentByVisualShifts($visualBoundaries, $duration);
        } else {
            // No segmentation signals — single segment
            return $this->singleSegmentResult($source, $duration, $signalsUsed);
        }

        // Classify segments and assess uncertainties
        $segments = $this->classifySegments($segments);
        $uncertainties = $this->assessBoundaryUncertainties($segments);

        return [
            'segments' => array_values($segments),
            'boundary_uncertainties' => $uncertainties,
            'meta' => [
                'total_duration' => $duration,
                'segment_count' => count($segments),
                'signals_used' => $signalsUsed,
            ],
        ];
    }

    // ── Chapter normalization ───────────────────────────────────────

    private function normalizeChapters(array $chapters, int $duration): array
    {
        if (empty($chapters)) {
            return [];
        }

        $normalized = [];
        foreach ($chapters as $ch) {
            $startSeconds = $this->extractChapterSeconds($ch);
            if ($startSeconds === null) {
                continue;
            }
            $title = trim((string)($ch['title'] ?? $ch['label'] ?? ''));
            $normalized[] = [
                'start_seconds' => $startSeconds,
                'title' => $title,
            ];
        }

        usort($normalized, fn($a, $b) => $a['start_seconds'] <=> $b['start_seconds']);

        // Add end_seconds based on next chapter start
        $count = count($normalized);
        for ($i = 0; $i < $count; $i++) {
            $normalized[$i]['end_seconds'] = ($i + 1 < $count)
                ? $normalized[$i + 1]['start_seconds']
                : (float)$duration;
        }

        return $normalized;
    }

    private function extractChapterSeconds(array $ch): ?float
    {
        if (isset($ch['seconds'])) {
            return (float)$ch['seconds'];
        }
        if (isset($ch['start_seconds'])) {
            return (float)$ch['start_seconds'];
        }
        if (isset($ch['start'])) {
            return (float)$ch['start'];
        }
        if (isset($ch['timestamp'])) {
            return $this->parseTimestampToSeconds((string)$ch['timestamp']);
        }
        return null;
    }

    private function parseTimestampToSeconds(string $ts): ?float
    {
        $ts = trim($ts);
        if ($ts === '') {
            return null;
        }
        $parts = array_reverse(explode(':', $ts));
        $seconds = 0.0;
        foreach ($parts as $i => $part) {
            $val = (float)$part;
            if ($i === 0) $seconds += $val;
            elseif ($i === 1) $seconds += $val * 60;
            elseif ($i === 2) $seconds += $val * 3600;
        }
        return $seconds;
    }

    // ── Chapter-based segmentation ──────────────────────────────────

    /**
     * Group chapters into drill/variation segments based on title analysis.
     *
     * Adjacent chapters with non-boundary titles (setup, uitvoering, etc.) are
     * grouped into the same segment. Explicit drill/variation/skip keywords
     * start a new segment.
     */
    private function segmentByChapters(array $chapters, int $duration): array
    {
        if (empty($chapters)) {
            return [];
        }

        $segments = [];
        $segmentId = 0;
        $currentGroup = [];

        foreach ($chapters as $ch) {
            $titleLower = strtolower($ch['title']);
            $category = $this->categorizeChapterTitle($titleLower);

            if ($category === 'skip') {
                // Finalize any accumulated group
                if (!empty($currentGroup)) {
                    $segments[] = $this->buildSegmentFromChapterGroup($currentGroup, ++$segmentId);
                    $currentGroup = [];
                }
                // Skip chapters (intro/outro) become their own segment
                $segments[] = [
                    'id' => ++$segmentId,
                    'start_seconds' => $ch['start_seconds'],
                    'end_seconds' => $ch['end_seconds'],
                    'title' => $ch['title'],
                    'type' => 'skip',
                    'confidence' => 'high',
                    'evidence' => [['signal' => 'chapter', 'detail' => 'Skip-keyword in chaptertitel: "' . $ch['title'] . '"']],
                    'chapter_titles' => [$ch['title']],
                ];
                continue;
            }

            if ($category === 'continuation') {
                if (empty($currentGroup)) {
                    $ch['_category'] = 'drill';
                    $currentGroup = [$ch];
                } else {
                    $currentGroup[] = $ch;
                }
                continue;
            }

            if ($category === 'drill' || $category === 'variation' || $category === 'unknown') {
                // Explicit new segment — finalize previous group
                if (!empty($currentGroup)) {
                    $segments[] = $this->buildSegmentFromChapterGroup($currentGroup, ++$segmentId);
                    $currentGroup = [];
                }
                $ch['_category'] = $category === 'variation' ? 'variation' : 'drill';
                $currentGroup = [$ch];
                continue;
            }
        }

        // Finalize last group
        if (!empty($currentGroup)) {
            $segments[] = $this->buildSegmentFromChapterGroup($currentGroup, ++$segmentId);
        }

        return $segments;
    }

    private function categorizeChapterTitle(string $titleLower): string
    {
        foreach (self::SKIP_KEYWORDS as $kw) {
            if (strpos($titleLower, $kw) !== false) {
                return 'skip';
            }
        }
        // Variation keywords first (more specific)
        foreach (self::VARIATION_KEYWORDS as $kw) {
            if (strpos($titleLower, $kw) !== false) {
                return 'variation';
            }
        }
        // Explicit drill keywords
        foreach (self::DRILL_KEYWORDS as $kw) {
            if (strpos($titleLower, $kw) !== false) {
                return 'drill';
            }
        }
        foreach (self::CONTINUATION_KEYWORDS as $kw) {
            if (strpos($titleLower, $kw) !== false) {
                return 'continuation';
            }
        }
        // Numbered pattern: "1.", "Deel 1", "Part 2"
        if (preg_match('/(?:^|\b)(?:deel|part|step|stap)\s*\d/i', $titleLower) ||
            preg_match('/^\d+[\.\):]/', $titleLower)) {
            return 'drill';
        }
        return 'unknown';
    }

    private function buildSegmentFromChapterGroup(array $group, int $segmentId): array
    {
        $firstChapter = $group[0];
        $lastChapter = end($group);
        $titles = array_map(fn($ch) => $ch['title'], $group);
        $category = $firstChapter['_category'] ?? 'drill';

        $title = count($titles) === 1
            ? $titles[0]
            : implode(' → ', array_slice($titles, 0, 3)) . (count($titles) > 3 ? '...' : '');

        return [
            'id' => $segmentId,
            'start_seconds' => $firstChapter['start_seconds'],
            'end_seconds' => $lastChapter['end_seconds'],
            'title' => $title,
            'type' => $category,
            'confidence' => 'high',
            'evidence' => [['signal' => 'chapter', 'detail' => 'Chaptergroep: ' . implode(', ', $titles)]],
            'chapter_titles' => $titles,
        ];
    }

    // ── Visual shift detection ──────────────────────────────────────

    /**
     * Detect visual transitions across the frame sequence.
     * Returns candidate boundary timestamps where significant changes occur.
     */
    private function detectVisualShifts(array $sequence): array
    {
        $boundaries = [];

        for ($i = 1; $i < count($sequence); $i++) {
            $curr = $sequence[$i];
            $action = strtolower(trim($curr['action'] ?? ''));
            $movement = strtolower(trim($curr['movement_patterns'] ?? ''));

            $isTransition = false;
            $detail = '';

            // Check for drill/variation keywords in the frame action
            $allKeywords = array_merge(self::DRILL_KEYWORDS, self::VARIATION_KEYWORDS);
            foreach ($allKeywords as $kw) {
                if (strpos($action, $kw) !== false) {
                    $isTransition = true;
                    $detail = "Keyword '{$kw}' in frame " . ($curr['frame'] ?? $i + 1);
                    break;
                }
            }

            // Check for setup reset indicators
            if (!$isTransition) {
                $setupIndicators = [
                    'nieuwe opstelling', 'andere opzet', 'opnieuw', 'reset',
                    'wisselen van veld', 'nieuw veld', 'ander veld', 'andere oefening',
                ];
                foreach ($setupIndicators as $indicator) {
                    if (strpos($action, $indicator) !== false || strpos($movement, $indicator) !== false) {
                        $isTransition = true;
                        $detail = "Setup-wijziging '{$indicator}' bij frame " . ($curr['frame'] ?? $i + 1);
                        break;
                    }
                }
            }

            if ($isTransition) {
                $timestamp = $this->parseTimestampToSeconds($curr['timestamp'] ?? '');
                if ($timestamp !== null) {
                    $boundaries[] = [
                        'timestamp' => $timestamp,
                        'signal' => 'visual_shift',
                        'strength' => 0.6,
                        'detail' => $detail,
                        'frame' => $curr['frame'] ?? $i + 1,
                    ];
                }
            }
        }

        return $boundaries;
    }

    // ── Transcript transition detection ─────────────────────────────

    /**
     * Detect transition language in the transcript.
     * Since transcript has no timestamps, this provides supporting evidence only.
     */
    private function detectTranscriptTransitions(string $transcript): array
    {
        $transcriptLower = strtolower($transcript);
        $signals = [];

        $transitionPhrases = [
            'nu gaan we' => 'drill_transition',
            'volgende oefening' => 'drill_transition',
            'volgende drill' => 'drill_transition',
            'andere oefening' => 'drill_transition',
            'tweede oefening' => 'drill_transition',
            'derde oefening' => 'drill_transition',
            'variatie' => 'variation_marker',
            'progressie' => 'variation_marker',
            'moeilijker maken' => 'variation_marker',
            'makkelijker maken' => 'variation_marker',
            'uitbreiding' => 'variation_marker',
        ];

        foreach ($transitionPhrases as $phrase => $type) {
            if (strpos($transcriptLower, $phrase) !== false) {
                $signals[] = [
                    'phrase' => $phrase,
                    'type' => $type,
                ];
            }
        }

        return $signals;
    }

    // ── Visual-only segment building ────────────────────────────────

    /**
     * Build segments from visual shift boundaries when no chapters are available.
     */
    private function segmentByVisualShifts(array $boundaries, int $duration): array
    {
        if (empty($boundaries)) {
            return [];
        }

        usort($boundaries, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);
        $merged = $this->mergeBoundaries($boundaries);

        $segments = [];
        $segmentId = 0;
        $prevEnd = 0.0;

        foreach ($merged as $boundary) {
            if ($boundary['timestamp'] > $prevEnd + self::BOUNDARY_MERGE_THRESHOLD) {
                $segments[] = [
                    'id' => ++$segmentId,
                    'start_seconds' => $prevEnd,
                    'end_seconds' => $boundary['timestamp'],
                    'title' => 'Segment ' . $segmentId,
                    'type' => 'drill',
                    'confidence' => 'medium',
                    'evidence' => [['signal' => 'visual_shift', 'detail' => $boundary['detail']]],
                    'chapter_titles' => [],
                ];
            }
            $prevEnd = $boundary['timestamp'];
        }

        // Final segment
        if ($prevEnd < $duration) {
            $segments[] = [
                'id' => ++$segmentId,
                'start_seconds' => $prevEnd,
                'end_seconds' => (float)$duration,
                'title' => 'Segment ' . $segmentId,
                'type' => 'drill',
                'confidence' => 'medium',
                'evidence' => [],
                'chapter_titles' => [],
            ];
        }

        return $segments;
    }

    private function mergeBoundaries(array $boundaries): array
    {
        if (empty($boundaries)) {
            return [];
        }

        $merged = [$boundaries[0]];
        for ($i = 1; $i < count($boundaries); $i++) {
            $last = $merged[count($merged) - 1];
            $curr = $boundaries[$i];

            if (($curr['timestamp'] - $last['timestamp']) < self::BOUNDARY_MERGE_THRESHOLD) {
                // Keep the stronger boundary
                if (($curr['strength'] ?? 0) > ($last['strength'] ?? 0)) {
                    $merged[count($merged) - 1] = $curr;
                }
            } else {
                $merged[] = $curr;
            }
        }

        return $merged;
    }

    // ── Evidence enrichment ─────────────────────────────────────────

    /**
     * Enrich chapter-based segments with visual and transcript evidence.
     */
    private function enrichSegmentsWithEvidence(
        array $segments,
        array $visualBoundaries,
        array $transcriptSignals,
        array $sequence
    ): array {
        // Visual boundary evidence
        foreach ($visualBoundaries as $vb) {
            foreach ($segments as &$segment) {
                $ts = $vb['timestamp'];
                // Visual boundary near segment start confirms the boundary
                if (abs($ts - $segment['start_seconds']) < self::BOUNDARY_MERGE_THRESHOLD) {
                    $segment['confidence'] = 'high';
                    $segment['evidence'][] = [
                        'signal' => 'visual_confirmation',
                        'detail' => "Visuele shift bij {$ts}s bevestigt segmentgrens",
                    ];
                }
                // Visual boundary falls within segment — internal transition
                elseif ($ts > $segment['start_seconds'] && $ts < $segment['end_seconds']) {
                    $segment['evidence'][] = [
                        'signal' => 'visual_shift',
                        'detail' => $vb['detail'],
                    ];
                }
            }
            unset($segment);
        }

        // Transcript signals as supporting evidence
        foreach ($transcriptSignals as $ts) {
            foreach ($segments as &$segment) {
                if ($segment['type'] === 'skip') continue;
                if ($ts['type'] === 'variation_marker' && $segment['type'] === 'variation') {
                    $segment['evidence'][] = [
                        'signal' => 'transcript',
                        'detail' => 'Transcript bevat variatie-taal: "' . $ts['phrase'] . '"',
                    ];
                    break;
                }
                if ($ts['type'] === 'drill_transition' && $segment['type'] !== 'variation') {
                    $segment['evidence'][] = [
                        'signal' => 'transcript',
                        'detail' => 'Transcript bevat overgang: "' . $ts['phrase'] . '"',
                    ];
                    break;
                }
            }
            unset($segment);
        }

        // Attach visual sequence entries to segments by timestamp
        foreach ($segments as &$segment) {
            $segSequence = [];
            foreach ($sequence as $entry) {
                $ts = $this->parseTimestampToSeconds($entry['timestamp'] ?? '');
                if ($ts !== null && $ts >= $segment['start_seconds'] && $ts < $segment['end_seconds']) {
                    $segSequence[] = $entry;
                }
            }
            if (!empty($segSequence)) {
                $segment['visual_sequence'] = $segSequence;
            }
        }
        unset($segment);

        return $segments;
    }

    // ── Classification ──────────────────────────────────────────────

    /**
     * Classify segments as drill or variation based on position and content.
     * Segments already classified from chapter keywords are preserved.
     */
    private function classifySegments(array $segments): array
    {
        if (empty($segments)) {
            return [];
        }

        $firstDrillFound = false;

        foreach ($segments as &$segment) {
            if ($segment['type'] === 'skip') {
                continue;
            }

            // Already classified from chapter keywords
            if ($segment['type'] === 'drill' || $segment['type'] === 'variation') {
                $firstDrillFound = true;
                continue;
            }

            // Unknown type: first non-skip is drill, rest are variations
            if (!$firstDrillFound) {
                $segment['type'] = 'drill';
                $firstDrillFound = true;
            } else {
                $segment['type'] = 'variation';
            }
        }
        unset($segment);

        return $segments;
    }

    // ── Boundary uncertainty ────────────────────────────────────────

    /**
     * Assess boundary confidence between consecutive segments.
     * Reports uncertainties where evidence is thin or ambiguous.
     */
    private function assessBoundaryUncertainties(array $segments): array
    {
        $uncertainties = [];

        for ($i = 1; $i < count($segments); $i++) {
            $prev = $segments[$i - 1];
            $curr = $segments[$i];

            $evidenceCount = count($curr['evidence'] ?? []);
            $confidence = $curr['confidence'] ?? 'medium';

            if ($confidence !== 'high' || $evidenceCount <= 1) {
                $reason = 'Slechts ' . $evidenceCount . ' signaal(en) voor deze segmentgrens';
                if ($confidence === 'low') {
                    $reason = 'Lage betrouwbaarheid: geen duidelijke visuele of tekstuele overgang';
                }
                $uncertainties[] = [
                    'between_segments' => [$prev['id'], $curr['id']],
                    'boundary_seconds' => $curr['start_seconds'],
                    'reason' => $reason,
                ];
            }
        }

        return $uncertainties;
    }

    // ── Single segment fallback ─────────────────────────────────────

    private function singleSegmentResult(array $source, int $duration, array $signalsUsed): array
    {
        $title = trim((string)($source['title'] ?? 'Onbekende oefening'));

        return [
            'segments' => [
                [
                    'id' => 1,
                    'start_seconds' => 0,
                    'end_seconds' => (float)$duration,
                    'title' => $title,
                    'type' => 'drill',
                    'confidence' => empty($signalsUsed) ? 'low' : 'medium',
                    'evidence' => empty($signalsUsed)
                        ? [['signal' => 'fallback', 'detail' => 'Geen segmentatie-signalen gedetecteerd']]
                        : [['signal' => 'analysis', 'detail' => 'Eén doorlopende oefening gedetecteerd']],
                    'chapter_titles' => [],
                ],
            ],
            'boundary_uncertainties' => [],
            'meta' => [
                'total_duration' => $duration,
                'segment_count' => 1,
                'signals_used' => $signalsUsed,
            ],
        ];
    }
}
