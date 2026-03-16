<?php
declare(strict_types=1);

class AiWorkflowService {
    private AiRetrievalService $retrievalService;
    private AiPromptBuilder $promptBuilder;
    private OpenRouterClient $openRouterClient;

    public function __construct(
        private PDO $pdo,
        ?AiRetrievalService $retrievalService = null,
        ?AiPromptBuilder $promptBuilder = null,
        ?OpenRouterClient $openRouterClient = null
    ) {
        $this->retrievalService = $retrievalService ?? new AiRetrievalService($pdo);
        $this->promptBuilder = $promptBuilder ?? new AiPromptBuilder($pdo);
        $this->openRouterClient = $openRouterClient ?? new OpenRouterClient($pdo);
    }

    /**
     * Handle the search phase: find candidate videos and rank them with LLM.
     *
     * @return array{ok: bool, phase?: string, video_choices?: array, warnings?: array, usage?: ?array, error?: string, code?: string, http_status?: int}
     */
    public function handleSearchPhase(
        string $message,
        ?array $formState,
        array $settings,
        int $userId,
        string $modelId,
        array $chatHistory = []
    ): array {
        $searchResult = $this->retrievalService->searchVideos($message, $formState, $settings, $userId, $modelId, $chatHistory);
        if (!$searchResult['ok']) {
            return $searchResult;
        }

        $candidates = $this->attachEvidencePreviewToCandidates($searchResult['candidates'], $settings);
        $warnings = $searchResult['warnings'] ?? [];

        // Rank candidates with LLM
        $videoChoices = null;
        $usage = null;
        $rankingPrompt = $this->promptBuilder->buildRankingPrompt($message, $formState, $candidates, $chatHistory);

        try {
            $response = $this->openRouterClient->chatCompletion(
                [['role' => 'user', 'content' => $rankingPrompt]],
                $modelId,
                $userId
            );

            if ($response['ok']) {
                $usage = is_array($response['usage'] ?? null) ? $response['usage'] : null;
                $selections = $this->parseRankingResponse(trim((string)($response['content'] ?? '')));
                if (!empty($selections)) {
                    $videoChoices = $this->buildRankedVideoChoices($candidates, $selections);
                }
            }
        } catch (Throwable $e) {
            error_log('[AI] handleSearchPhase ranking failed: ' . $e->getMessage());
        }

        // Fallback: unranked top 5
        if (empty($videoChoices)) {
            $warnings[] = 'Video\'s worden getoond zonder AI-ranking.';
            $videoChoices = $this->buildUnrankedVideoChoices(array_slice($candidates, 0, 5));
        }

        if (empty($videoChoices)) {
            return [
                'ok' => false,
                'error' => 'Geen passende video\'s gevonden.',
                'code' => 'no_results',
                'http_status' => 404,
            ];
        }

        return [
            'ok' => true,
            'phase' => 'search_results',
            'video_choices' => $videoChoices,
            'warnings' => $warnings,
            'usage' => $usage,
        ];
    }

    public function assessTechnicalViability(array $source): array {
        $preflight = is_array($source['technical_preflight'] ?? null) ? $source['technical_preflight'] : [];
        $checked = !empty($preflight['checked']);
        $downloadable = $preflight['downloadable_via_ytdlp'] ?? null;
        $durationSeconds = max(0, (int)($preflight['duration_seconds'] ?? $source['duration_seconds'] ?? 0));
        $chapterCount = max(0, (int)($preflight['chapter_count'] ?? (is_array($source['chapters'] ?? null) ? count($source['chapters']) : 0)));
        $transcriptSource = trim((string)($preflight['transcript_source'] ?? $source['transcript_source'] ?? 'none'));
        $metadataOnly = array_key_exists('metadata_only', $preflight)
            ? (bool)$preflight['metadata_only']
            : ($chapterCount === 0 && in_array($transcriptSource, ['none', 'metadata_fallback'], true));
        $status = trim((string)($preflight['status'] ?? $preflight['error_code'] ?? ''));
        $errorCode = trim((string)($preflight['error_code'] ?? ''));
        $error = trim((string)($preflight['error'] ?? ''));
        $authRequired = !empty($preflight['auth_required']);
        $usedCookies = !empty($preflight['used_cookies']);
        $isShortClip = $durationSeconds > 0 && $durationSeconds < 30;
        $isVeryShortClip = $durationSeconds > 0 && $durationSeconds < 15;
        $hasTextStructure = $chapterCount > 0 || in_array($transcriptSource, ['captions', 'captions_fallback'], true);
        $hasSolidDuration = $durationSeconds >= 90 && $durationSeconds <= 900;
        $isProbeInconclusive = $downloadable !== true && $status === 'network_error';
        $isUnchecked = !$checked && $downloadable !== true && $status === '' && $errorCode === '';

        $isSelectable = $downloadable === true || $isProbeInconclusive || $isUnchecked;
        $isRecommended = $isSelectable && !$isShortClip && !$metadataOnly;
        if ($isProbeInconclusive) {
            $isRecommended = false;
        } elseif ($isUnchecked) {
            $isRecommended = false;
        }

        $label = 'Even checken';
        $summary = 'Kijk nog even of deze video past.';

        if ($downloadable === true) {
            if ($usedCookies) {
                $label = 'Extra toegang';
                $summary = 'Deze video is bruikbaar, maar vraagt extra toegang.';
            } elseif ($isShortClip) {
                $label = 'Korte video';
                $summary = 'Deze video is bruikbaar, maar wel erg kort.';
            } elseif ($metadataOnly) {
                $label = 'Beeld check';
                $summary = 'Deze video is bruikbaar, maar ik moet vooral op het beeld letten.';
            } elseif ($hasTextStructure) {
                $label = 'Klaar voor gebruik';
                $summary = 'Deze video is goed te gebruiken.';
            } else {
                $label = 'Klaar voor gebruik';
                $summary = 'Deze video is bruikbaar, maar ik zie nog weinig houvast.';
            }
        } elseif ($isUnchecked) {
            $label = 'Even checken';
            $summary = 'Ik heb deze video nog niet technisch gecontroleerd. Je kunt hem wel proberen.';
        } elseif ($isProbeInconclusive) {
            $label = 'Even checken';
            $summary = 'Ik kon dit nog niet goed controleren. Je kunt hem wel proberen.';
        } elseif ($status === 'cookies_invalid') {
            $label = 'Extra toegang lukt niet';
            $summary = 'Deze video vraagt extra toegang, maar dat lukt nu niet.';
        } elseif ($authRequired) {
            $label = 'Extra toegang nodig';
            $summary = 'Deze video vraagt extra toegang.';
        } elseif (in_array($status, ['unavailable', 'private_or_blocked', 'age_or_geo_restricted'], true)) {
            $label = 'Nu niet bruikbaar';
            $summary = 'Deze video is nu niet goed te gebruiken.';
        } elseif ($status === 'missing_ytdlp') {
            $label = 'Tijdelijk niet mogelijk';
            $summary = 'Ik kan deze video nu niet goed controleren.';
        } elseif ($status === 'network_error') {
            $label = 'Verbinding mislukt';
            $summary = 'De controle lukte nu niet door een verbindingsprobleem.';
        }

        $sortScore = 0;
        if ($downloadable === true) {
            $sortScore += 60;
        } elseif ($isUnchecked) {
            $sortScore += 0;
        } elseif ($isProbeInconclusive) {
            $sortScore += 5;
        } elseif ($authRequired) {
            $sortScore -= 20;
        } else {
            $sortScore -= 50;
        }
        if ($isRecommended) {
            $sortScore += 20;
        }
        if ($chapterCount > 0) {
            $sortScore += min(12, $chapterCount * 3);
        }
        if ($downloadable === true && $hasTextStructure) {
            $sortScore += 6;
        }
        if ($downloadable === true && $hasSolidDuration) {
            $sortScore += 10;
        } elseif ($downloadable === true && $durationSeconds >= 45) {
            $sortScore += 4;
        }
        if ($transcriptSource === 'captions') {
            $sortScore += 10;
        } elseif ($transcriptSource === 'captions_fallback') {
            $sortScore += 6;
        } elseif ($transcriptSource === 'metadata_fallback') {
            $sortScore -= 6;
        } elseif ($transcriptSource === 'none') {
            $sortScore -= 8;
        }
        if ($metadataOnly) {
            $sortScore -= 12;
        }
        if ($isShortClip) {
            $sortScore -= $isVeryShortClip ? 24 : 18;
        }

        return [
            'downloadable_via_ytdlp' => $downloadable,
            'auth_required' => $authRequired,
            'used_cookies' => $usedCookies,
            'status' => $status !== '' ? $status : ($downloadable === true ? 'ok' : 'unknown'),
            'error_code' => $errorCode,
            'error' => $error,
            'duration_seconds' => $durationSeconds,
            'chapter_count' => $chapterCount,
            'transcript_source' => $transcriptSource !== '' ? $transcriptSource : 'none',
            'metadata_only' => $metadataOnly,
            'is_short_clip' => $isShortClip,
            'is_very_short_clip' => $isVeryShortClip,
            'has_text_structure' => $hasTextStructure,
            'has_solid_duration' => $hasSolidDuration,
            'preflight_inconclusive' => $isProbeInconclusive,
            'is_selectable' => $isSelectable,
            'is_recommended' => $isRecommended,
            'label' => $label,
            'summary' => $summary,
            'sort_score' => $sortScore,
        ];
    }

    public function assessSourceEvidence(array $source, array $settings = []): array {
        $source = $this->ensureMetadataFallbackTranscriptExcerpt($source);
        $transcriptSource = trim((string)($source['transcript_source'] ?? 'none'));
        $snippetChars = $this->stringLength(trim((string)($source['snippet'] ?? '')));
        $chapterCount = is_array($source['chapters'] ?? null) ? count($source['chapters']) : 0;
        $transcriptCharsRaw = $this->stringLength(trim((string)($source['transcript_excerpt'] ?? '')));
        $transcriptChars = $transcriptSource === 'metadata_fallback'
            ? $this->estimateMetadataFallbackTranscriptChars($snippetChars, $chapterCount)
            : $transcriptCharsRaw;
        $durationSeconds = max(0, (int)($source['duration_seconds'] ?? 0));
        $visualStatus = trim((string)($source['visual_status'] ?? 'unknown'));
        $visualError = trim((string)($source['visual_error'] ?? ''));

        // Visual signals
        $visualFrameCount = max(0, (int)($source['visual_frame_count'] ?? 0));
        $visualFacts = is_array($source['visual_facts'] ?? null) ? $source['visual_facts'] : null;
        $visualConfidence = trim((string)($source['visual_confidence'] ?? 'none'));
        $usesUploadedScreenshots = $visualStatus === 'uploaded_screenshots_ok';
        $visualSetupDetected = $visualFacts !== null
            && is_array($visualFacts['setup'] ?? null)
            && trim((string)($visualFacts['setup']['starting_shape'] ?? '')) !== '';
        $visualSequenceDetected = $visualFacts !== null
            && is_array($visualFacts['sequence'] ?? null)
            && count($visualFacts['sequence']) >= 2;

        $score = 0.0;
        $signals = [];
        $blockingReasons = [];

        // --- Textual signals ---

        if ($transcriptSource === 'captions') {
            $score += 0.45;
            $signals[] = 'Ondertiteling gevonden.';
        } elseif ($transcriptSource === 'captions_fallback') {
            $score += 0.35;
            $signals[] = 'Een deel van de ondertiteling is gevonden.';
        } elseif ($transcriptSource === 'metadata_fallback') {
            $score += 0.12;
            $signals[] = 'Titel en beschrijving geven wat houvast.';
        } else {
            $blockingReasons[] = 'Ik vond weinig tekst uit de video.';
        }

        if ($transcriptChars >= 1400) {
            $score += 0.28;
            $signals[] = 'De video geeft veel duidelijke uitleg.';
        } elseif ($transcriptChars >= 700) {
            $score += 0.2;
            $signals[] = 'De video geeft bruikbare details.';
        } elseif ($transcriptChars >= 220) {
            $score += 0.12;
            $signals[] = 'De video geeft een beetje houvast.';
        } elseif ($transcriptSource !== 'none') {
            $blockingReasons[] = 'Ik heb te weinig duidelijke tekst uit de video.';
        }

        if ($chapterCount >= 3) {
            $score += 0.16;
            $signals[] = 'De video is duidelijk in delen opgebouwd.';
        } elseif ($chapterCount >= 1) {
            $score += 0.08;
            $signals[] = 'De video heeft hoofdstukken.';
        }

        if ($snippetChars >= 220) {
            $score += 0.08;
            $signals[] = 'De beschrijving helpt mee.';
        } elseif ($snippetChars >= 90) {
            $score += 0.04;
        }

        if ($transcriptSource === 'metadata_fallback' && $chapterCount >= 4 && $snippetChars >= 220) {
            $score += 0.06;
            $signals[] = 'Beschrijving en hoofdstukken geven samen een bruikbare kapstok.';
        }

        if ($durationSeconds >= 90 && $durationSeconds <= 1200) {
            $score += 0.03;
        }

        // --- Visual signals ---

        if ($visualFrameCount >= 6) {
            $score += 0.10;
            $signals[] = 'Ik kon genoeg beelden bekijken.';
        } elseif ($visualFrameCount >= 3) {
            $score += 0.05;
            $signals[] = 'Ik kon een paar beelden bekijken.';
        }

        if ($visualSetupDetected) {
            $score += 0.12;
            $signals[] = 'De opstelling is op beeld herkenbaar.';
        }

        if ($visualSequenceDetected) {
            $score += 0.10;
            $signals[] = 'Het verloop is op beeld herkenbaar.';
        }

        if ($visualConfidence === 'high') {
            $score += 0.08;
            $signals[] = 'Het beeld helpt sterk mee.';
        } elseif ($visualConfidence === 'medium') {
            $score += 0.04;
            $signals[] = 'Het beeld helpt mee.';
        }

        if ($usesUploadedScreenshots && $visualFrameCount >= 2) {
            $score += 0.08;
            $signals[] = 'Screenshots vullen de video goed aan.';
        }

        $score = min(1.0, round($score, 2));
        $minScore = (float)($settings['ai_source_min_evidence_score'] ?? 0.55);
        $isSufficient = $score >= $minScore;

        // --- Blocking rules ---
        // Visual evidence can lift blocks that were previously unavoidable for text-only sources.
        // A video with strong visual evidence (setup + sequence detected, medium+ confidence)
        // can pass even if text evidence alone would be insufficient.
        $hasStrongVisual = $visualSetupDetected && $visualSequenceDetected
            && in_array($visualConfidence, ['high', 'medium'], true);

        if ($transcriptSource === 'none' && $chapterCount === 0) {
            if (!$hasStrongVisual) {
                $isSufficient = false;
                $blockingReasons[] = 'Ik zie te weinig duidelijke info in de video.';
            } else {
                $signals[] = 'Het beeld vult de missende tekst goed aan.';
            }
        }

        if ($transcriptSource === 'metadata_fallback' && $chapterCount < 2) {
            if (!$hasStrongVisual) {
                $isSufficient = false;
                $blockingReasons[] = 'Ik moet hier te veel afgaan op titel en beschrijving.';
            } else {
                $signals[] = 'Het beeld vult de beperkte tekst goed aan.';
            }
        }

        if ($transcriptChars < 220 && $chapterCount < 2) {
            if (!$hasStrongVisual) {
                $isSufficient = false;
                $blockingReasons[] = 'Start, verloop en wissels zijn nog niet duidelijk genoeg.';
            } else {
                $signals[] = 'Het beeld vult de korte tekst goed aan.';
            }
        }

        $level = 'low';
        if ($score >= 0.72 && $isSufficient) {
            $level = 'high';
        } elseif ($score >= 0.55 && $isSufficient) {
            $level = 'medium';
        }

        return [
            'score' => $score,
            'level' => $level,
            'is_sufficient' => $isSufficient,
            'transcript_source' => $transcriptSource,
            'duration_seconds' => $durationSeconds,
            'transcript_chars' => $transcriptChars,
            'transcript_chars_raw' => $transcriptCharsRaw,
            'chapter_count' => $chapterCount,
            'snippet_chars' => $snippetChars,
            'visual_frame_count' => $visualFrameCount,
            'visual_status' => $visualStatus,
            'visual_error' => $visualError,
            'visual_setup_detected' => $visualSetupDetected,
            'visual_sequence_detected' => $visualSequenceDetected,
            'visual_confidence' => $visualConfidence,
            'signals' => array_values(array_unique($signals)),
            'blocking_reasons' => array_values(array_unique($blockingReasons)),
        ];
    }

    public function describeSourceEvidence(array $profile): array {
        $level = strtolower(trim((string)($profile['level'] ?? 'low')));
        if (!in_array($level, ['low', 'medium', 'high'], true)) {
            $level = 'low';
        }

        $isSufficient = !empty($profile['is_sufficient']);

        $label = '';
        if ($isSufficient && $level === 'high') {
            $label = 'Goede bron';
        } elseif ($isSufficient) {
            $label = 'Bruikbare bron';
        }

        $warning = '';
        $blockingReasons = is_array($profile['blocking_reasons'] ?? null) ? $profile['blocking_reasons'] : [];
        if (!$isSufficient) {
            $warning = $this->chooseSourceEvidenceWarning($profile, $blockingReasons);
        }

        if ($isSufficient) {
            $summary = $level === 'high'
                ? 'De inhoud van de video is duidelijk herkenbaar.'
                : 'De kern van de oefening is herkenbaar.';
        } else {
            $summary = $warning !== '' ? $warning : 'De inhoud van deze video is nog te onzeker.';
        }

        return [
            'label' => $label,
            'summary' => $summary,
            'warning' => $warning,
        ];
    }

    public function buildTranslatabilityRating(array $profile): array
    {
        $score = max(0.0, min(1.0, (float)($profile['score'] ?? 0.0)));
        $isSufficient = !empty($profile['is_sufficient']);

        // Calibrated for coach-facing UX: 1 star only for clearly weak evidence,
        // while still reserving 5 stars for truly strong and sufficient sources.
        if (!$isSufficient && $score < 0.18) {
            return ['rating' => 1, 'label' => 'laag'];
        }
        if (!$isSufficient && $score < 0.35) {
            return ['rating' => 2, 'label' => 'beperkt'];
        }
        if (!$isSufficient) {
            return ['rating' => 3, 'label' => 'redelijk'];
        }

        if ($score < 0.72) {
            return ['rating' => 4, 'label' => 'goed'];
        }

        return ['rating' => 5, 'label' => 'hoog'];
    }

    public function buildSourceEvidenceWarning(array $profile): string {
        $reasons = is_array($profile['blocking_reasons'] ?? null) ? $profile['blocking_reasons'] : [];
        $transcriptSource = trim((string)($profile['transcript_source'] ?? 'none'));
        $chapterCount = max(0, (int)($profile['chapter_count'] ?? 0));
        $visualStatus = trim((string)($profile['visual_status'] ?? 'unknown'));
        $visualConfidence = trim((string)($profile['visual_confidence'] ?? 'none'));
        $visualError = trim((string)($profile['visual_error'] ?? ''));
        $hasVisualAnalysis = in_array($visualConfidence, ['high', 'medium', 'low'], true);
        $parts = [
            'Ik kan van deze video nog geen goede oefening maken.',
        ];

        if ($transcriptSource === 'metadata_fallback') {
            if ($chapterCount === 0) {
                $parts[] = 'Ik haal nu vooral info uit de titel en beschrijving.';
            } else {
                $parts[] = 'Ik zie wel hoofdstukken, maar nog te weinig duidelijke uitleg uit de video zelf.';
            }
            $visualExplanation = $this->describeVisualFailure($visualStatus, $visualError, $hasVisualAnalysis);
            if ($visualExplanation !== null) {
                $parts[] = $visualExplanation;
            } elseif (!$hasVisualAnalysis) {
                $parts[] = 'Een extra beeldcheck is nu niet beschikbaar.';
            } else {
                $parts[] = 'Ook met het beeld blijft het nog te onduidelijk.';
            }
        } elseif ($transcriptSource === 'none' && $chapterCount === 0) {
            $parts[] = 'Ik zie nu te weinig duidelijke info in deze video.';
            $visualExplanation = $this->describeVisualFailure($visualStatus, $visualError, $hasVisualAnalysis);
            if ($visualExplanation !== null) {
                $parts[] = $visualExplanation;
            } elseif ($hasVisualAnalysis) {
                $parts[] = 'Ook met het beeld blijft het nog te onduidelijk.';
            }
        } elseif ($transcriptSource === 'none') {
            $parts[] = 'Ik vond weinig tekst uit deze video.';
        }

        $warning = $this->chooseSourceEvidenceWarning($profile, $reasons);
        if ($warning !== '') {
            $parts[] = $warning;
        }

        $parts[] = 'Kies een andere video, maak eerst een opzet of voeg duidelijke screenshots toe.';

        return implode(' ', $parts);
    }

    public function buildTechnicalPreflightWarning(array $viability): string {
        $status = trim((string)($viability['status'] ?? 'unknown'));
        $error = trim((string)($viability['error'] ?? ''));
        $parts = [
            'Ik kan deze video nu niet gebruiken.',
        ];

        if (!empty($viability['auth_required'])) {
            if (($viability['used_cookies'] ?? false) === true) {
                $parts[] = 'Deze video vraagt extra toegang. Dat lukt nu nog niet goed genoeg.';
            } elseif ($status === 'cookies_invalid') {
                $parts[] = 'Deze video vraagt extra toegang, maar dat lukt nu niet.';
            } else {
                $parts[] = 'Deze video vraagt extra toegang en is hier niet goed te openen. In YouTube speelt hij soms wel.';
            }
        } elseif (in_array($status, ['unavailable', 'private_or_blocked', 'age_or_geo_restricted'], true)) {
            $parts[] = 'Deze video is nu niet goed beschikbaar.';
        } elseif ($status === 'missing_ytdlp') {
            $parts[] = 'Ik kan deze video nu niet goed controleren.';
        } elseif ($status === 'network_error') {
            $parts[] = 'De controle lukte nu niet door een verbindingsprobleem.';
        } else {
            $parts[] = 'Ik kan deze video nu niet goed controleren.';
        }

        $parts[] = 'Kies een andere video of probeer later opnieuw.';

        return implode(' ', $parts);
    }

    private function describeVisualFailure(string $visualStatus, string $visualError, bool $hasVisualAnalysis): ?string {
        if ($visualStatus === 'disabled_no_model') {
            return 'Extra beeldcheck is nu niet beschikbaar.';
        }

        $error = strtolower($visualError);

        if ($visualStatus === 'frame_extraction_failed') {
            if ($error !== '') {
                if (str_contains($error, 'this video is not available') || str_contains($error, 'video is unavailable')) {
                    return 'Ik kon de beelden van deze video niet goed openen.';
                }
                if (str_contains($error, 'temporary failure in name resolution') || str_contains($error, 'name resolution')) {
                    return 'Ik kon de video nu niet goed laden.';
                }
                if (str_contains($error, 'yt-dlp of ffmpeg niet gevonden')) {
                    return 'Ik kon de video nu niet goed controleren.';
                }
                if (str_contains($error, 'te lang voor frame-extractie') || str_contains($error, 'video is te lang')) {
                    return 'De video is te lang om snel te controleren.';
                }
            }

            return 'Ik kon geen bruikbare beelden uit de video halen.';
        }

        if ($visualStatus === 'analysis_failed') {
            return 'Ik kon uit de beelden nog niet genoeg duidelijke info halen.';
        }

        if ($visualStatus === 'uploaded_screenshots_failed') {
            return 'De screenshots laten nog niet duidelijk genoeg zien hoe de oefening loopt.';
        }

        if ($hasVisualAnalysis) {
            return null;
        }

        return null;
    }

    private function parseRankingResponse(string $content): array {
        if (preg_match('/\{.*\}/s', $content, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded['selections'] ?? null)) {
                $result = [];
                foreach ($decoded['selections'] as $sel) {
                    if (!is_array($sel)) {
                        continue;
                    }
                    $index = (int)($sel['candidate_index'] ?? 0);
                    $reason = trim((string)($sel['reason'] ?? ''));
                    if ($index >= 1 && $reason !== '') {
                        $result[] = ['candidate_index' => $index, 'reason' => $reason];
                    }
                }
                return $result;
            }
        }
        return [];
    }

    private function buildRankedVideoChoices(array $candidates, array $selections): array {
        $choices = [];
        foreach ($selections as $sel) {
            $index = $sel['candidate_index'] - 1;
            if (!isset($candidates[$index])) {
                continue;
            }
            $choices[] = $this->buildVideoChoice($candidates[$index], $sel['reason']);
        }
        return $this->sortVideoChoicesByTechnicalViability($choices);
    }

    private function buildUnrankedVideoChoices(array $candidates): array {
        $choices = [];
        foreach ($candidates as $video) {
            $choices[] = $this->buildVideoChoice($video, '');
        }
        return $this->sortVideoChoicesByTechnicalViability($choices);
    }

    private function buildVideoChoice(array $video, string $aiReason): array {
        $videoId = (string)($video['external_id'] ?? '');
        $durationSecs = (int)($video['duration_seconds'] ?? 0);
        $durationFormatted = $durationSecs > 0
            ? sprintf('%d:%02d', intdiv($durationSecs, 60), $durationSecs % 60)
            : '';
        $viability = $this->assessTechnicalViability($video);
        $sourceEvidence = is_array($video['source_evidence_preview'] ?? null)
            ? $video['source_evidence_preview']
            : $this->assessSourceEvidence($video);
        $evidenceDisplay = $this->describeSourceEvidence($sourceEvidence);
        $translatability = $this->buildTranslatabilityRating($sourceEvidence);
        $coachReason = $this->normalizeCoachFacingReason($aiReason);
        if ($coachReason === '') {
            $coachReason = 'Deze video lijkt inhoudelijk goed aan te sluiten op je vraag.';
        }

        $choice = [
            'video_id' => $videoId,
            'title' => (string)($video['title'] ?? ''),
            'channel' => (string)($video['channel'] ?? ''),
            'duration_formatted' => $durationFormatted,
            'url' => 'https://www.youtube.com/watch?v=' . rawurlencode($videoId),
            'ai_reason' => $coachReason,
            'technical_preflight' => is_array($video['technical_preflight'] ?? null) ? $video['technical_preflight'] : null,
            'technical_label' => $viability['label'],
            'technical_summary' => $viability['summary'],
            'is_selectable' => $viability['is_selectable'],
            'is_recommended' => $viability['is_recommended'],
            'availability_status' => $viability['status'],
            'availability_error' => $viability['error'],
            'technical_viability' => $viability,
            'source_evidence' => $sourceEvidence,
            'source_evidence_score' => (float)($sourceEvidence['score'] ?? 0.0),
            'source_evidence_level' => trim((string)($sourceEvidence['level'] ?? 'low')),
            'source_evidence_sufficient' => !empty($sourceEvidence['is_sufficient']),
            'source_evidence_label' => $evidenceDisplay['label'],
            'source_evidence_summary' => $evidenceDisplay['summary'],
            'source_evidence_warning' => $evidenceDisplay['warning'],
            'translatability_rating' => (int)($translatability['rating'] ?? 0),
            'translatability_label' => (string)($translatability['label'] ?? ''),
            '_technical_sort_score' => $viability['sort_score'] + $this->buildEvidenceSortScore($sourceEvidence),
        ];

        $viewCount = (int)($video['view_count'] ?? 0);
        $likeCount = (int)($video['like_count'] ?? 0);
        if ($viewCount > 0) {
            $choice['view_count'] = $viewCount;
        }
        if ($likeCount > 0) {
            $choice['like_count'] = $likeCount;
        }

        return $choice;
    }

    private function normalizeCoachFacingReason(string $reason): string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $reason) ?? $reason);
        if ($normalized === '') {
            return '';
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', $normalized);
        if (!is_array($sentences) || empty($sentences)) {
            $sentences = [$normalized];
        }

        $technicalPattern = '/\b(?:yt-?dlp|metadata(?:_fallback|_only)?|transcript(?:_source)?|auth_required|cookies?|preflight|downloadbaar|api|chapter_count|evidence(?:-readiness)?|status=)\b/i';
        $kept = [];
        foreach ($sentences as $sentence) {
            $sentence = trim((string)$sentence);
            if ($sentence === '') {
                continue;
            }
            if (preg_match($technicalPattern, $sentence) === 1) {
                continue;
            }

            $kept[] = $sentence;
            if (count($kept) >= 2) {
                break;
            }
        }

        if (empty($kept)) {
            return '';
        }

        return implode(' ', $kept);
    }

    private function attachEvidencePreviewToCandidates(array $candidates, array $settings): array {
        $enriched = [];

        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $candidate = $this->ensureMetadataFallbackTranscriptExcerpt($candidate);
            $profile = $this->assessSourceEvidence($candidate, $settings);
            $candidate['source_evidence_preview'] = $profile;
            $enriched[] = $candidate;
        }

        return $enriched;
    }

    private function ensureMetadataFallbackTranscriptExcerpt(array $source): array
    {
        $transcriptSource = trim((string)($source['transcript_source'] ?? 'none'));
        $transcriptExcerpt = trim((string)($source['transcript_excerpt'] ?? ''));
        if ($transcriptSource !== 'metadata_fallback' || $transcriptExcerpt !== '') {
            return $source;
        }

        $parts = [];
        $title = trim((string)($source['title'] ?? ''));
        if ($title !== '') {
            $parts[] = 'Videofocus: ' . $title;
        }

        $chapterBits = [];
        foreach (is_array($source['chapters'] ?? null) ? $source['chapters'] : [] as $chapter) {
            if (!is_array($chapter)) {
                continue;
            }

            $timestamp = trim((string)($chapter['timestamp'] ?? ''));
            $label = trim((string)($chapter['label'] ?? $chapter['title'] ?? ''));
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

        $snippet = trim((string)($source['snippet'] ?? ''));
        if ($snippet !== '') {
            $snippet = preg_replace('/https?:\/\/\S+/i', ' ', $snippet) ?? $snippet;
            $snippet = preg_replace('/(^|\s)#[\p{L}\p{N}_-]+/u', ' ', $snippet) ?? $snippet;
            $snippet = trim(preg_replace('/\s+/u', ' ', $snippet) ?? $snippet);
            if ($snippet !== '') {
                $parts[] = 'Beschrijving: ' . $snippet;
            }
        }

        $fallback = trim(implode("\n", $parts));
        if ($fallback === '') {
            return $source;
        }

        $source['transcript_excerpt'] = function_exists('mb_substr')
            ? mb_substr($fallback, 0, 3000, 'UTF-8')
            : substr($fallback, 0, 3000);
        return $source;
    }

    private function estimateMetadataFallbackTranscriptChars(int $snippetChars, int $chapterCount): int
    {
        $estimatedChars = 0;

        if ($chapterCount >= 6) {
            $estimatedChars += 180;
        } elseif ($chapterCount >= 4) {
            $estimatedChars += 150;
        } elseif ($chapterCount >= 2) {
            $estimatedChars += 110;
        } elseif ($chapterCount >= 1) {
            $estimatedChars += 60;
        }

        if ($snippetChars >= 1200) {
            $estimatedChars += 120;
        } elseif ($snippetChars >= 600) {
            $estimatedChars += 90;
        } elseif ($snippetChars >= 220) {
            $estimatedChars += 60;
        } elseif ($snippetChars >= 90) {
            $estimatedChars += 30;
        }

        return min(260, $estimatedChars);
    }

    private function chooseSourceEvidenceWarning(array $profile, array $blockingReasons): string
    {
        $transcriptSource = trim((string)($profile['transcript_source'] ?? 'none'));
        $chapterCount = max(0, (int)($profile['chapter_count'] ?? 0));
        $durationSeconds = max(0, (int)($profile['duration_seconds'] ?? 0));
        $transcriptChars = max(0, (int)($profile['transcript_chars'] ?? 0));

        if ($durationSeconds > 0 && $durationSeconds < 30) {
            return 'Deze video is waarschijnlijk te kort om de oefening goed te herkennen.';
        }

        if ($transcriptSource === 'metadata_fallback' && $chapterCount >= 3 && $transcriptChars >= 220) {
            return 'Ik kan hier iets mee, maar check zelf nog even de opstelling en het doorwisselen.';
        }

        if ($transcriptSource === 'metadata_fallback' && $chapterCount >= 1) {
            return 'Ik baseer dit vooral op titel, beschrijving en hoofdstukken. Check zelf nog even de details.';
        }

        $warning = trim((string)($blockingReasons[0] ?? ''));
        if ($warning !== '') {
            return $warning;
        }

        if ($transcriptSource === 'metadata_fallback') {
            return 'Ik baseer dit vooral op titel en beschrijving. Check zelf nog even de details.';
        }

        return 'Ik heb nog te weinig duidelijke info uit de video.';
    }

    private function buildEvidenceSortScore(array $profile): int {
        $score = (float)($profile['score'] ?? 0.0);
        $isSufficient = !empty($profile['is_sufficient']);

        if ($isSufficient && $score >= 0.72) {
            return 12;
        }
        if ($isSufficient && $score >= 0.55) {
            return 7;
        }
        if ($isSufficient) {
            return 3;
        }

        return $score > 0.0 ? -4 : -6;
    }

    private function sortVideoChoicesByTechnicalViability(array $choices): array {
        usort($choices, function (array $a, array $b): int {
            $recommendedDiff = ((int)!empty($b['is_recommended'])) <=> ((int)!empty($a['is_recommended']));
            if ($recommendedDiff !== 0) {
                return $recommendedDiff;
            }

            $selectableDiff = ((int)!empty($b['is_selectable'])) <=> ((int)!empty($a['is_selectable']));
            if ($selectableDiff !== 0) {
                return $selectableDiff;
            }

            return ((int)($b['_technical_sort_score'] ?? 0)) <=> ((int)($a['_technical_sort_score'] ?? 0));
        });

        foreach ($choices as &$choice) {
            unset($choice['_technical_sort_score']);
        }
        unset($choice);

        return $choices;
    }

    public function applySourceConstraintAlignment(?array $textSuggestion, array $sourceContext, ?array $formState, string $latestUserMessage): ?array {
        if (!is_array($textSuggestion)) {
            return $textSuggestion;
        }

        $formStateValues = is_array($formState) ? $formState : [];
        $fields = is_array($textSuggestion['fields'] ?? null) ? $textSuggestion['fields'] : [];
        $warnings = is_array($textSuggestion['warnings'] ?? null) ? $textSuggestion['warnings'] : [];
        $constraints = $this->extractSourceConstraints($sourceContext);

        $hasSourcePlayerConstraint = ($constraints['min_players'] ?? null) !== null && ($constraints['max_players'] ?? null) !== null;
        $hasSourceDurationConstraint = ($constraints['duration'] ?? null) !== null;

        $userHasPlayers = $this->safeConstraintInt($formStateValues['min_players'] ?? null, 1, 30) !== null
            || $this->safeConstraintInt($formStateValues['max_players'] ?? null, 1, 30) !== null
            || $this->hasPlayerConstraintInText($latestUserMessage);
        $userHasDuration = $this->safeConstraintInt($formStateValues['duration'] ?? null, 5, 90) !== null
            || $this->hasDurationConstraintInText($latestUserMessage);

        if ($hasSourcePlayerConstraint && !$userHasPlayers) {
            $targetMin = (int)$constraints['min_players'];
            $targetMax = (int)$constraints['max_players'];
            $currentMin = isset($fields['min_players']) ? (int)$fields['min_players'] : null;
            $currentMax = isset($fields['max_players']) ? (int)$fields['max_players'] : null;
            if ($currentMin !== $targetMin || $currentMax !== $targetMax) {
                $fields['min_players'] = $targetMin;
                $fields['max_players'] = $targetMax;
                $warnings[] = sprintf(
                    'Spelersaantal afgestemd op gekozen bron (%d-%d).',
                    $targetMin,
                    $targetMax
                );
            }
        }

        if ($hasSourceDurationConstraint && !$userHasDuration) {
            $targetDuration = (int)$constraints['duration'];
            $currentDuration = isset($fields['duration']) ? (int)$fields['duration'] : null;
            if ($currentDuration !== $targetDuration) {
                $fields['duration'] = $targetDuration;
                $warnings[] = sprintf(
                    'Duur afgestemd op gekozen bron (%d min).',
                    $targetDuration
                );
            }
        }

        if ((string)($fields['source'] ?? '') === '' && !empty($sourceContext[0]['url'])) {
            $fields['source'] = trim((string)$sourceContext[0]['url']);
        }

        $textSuggestion['fields'] = $fields;
        $textSuggestion['warnings'] = array_values(array_unique($warnings));

        return $textSuggestion;
    }

    public function extractSourceConstraints(array $sourceContext): array {
        $constraints = [
            'min_players' => null,
            'max_players' => null,
            'duration' => null,
            'area_width_m' => null,
            'area_height_m' => null,
            'player_pattern' => null,
            'evidence' => [],
        ];

        foreach ($sourceContext as $source) {
            if (!is_array($source)) {
                continue;
            }

            $minPlayers = $this->safeConstraintInt($source['source_min_players'] ?? null, 1, 30);
            $maxPlayers = $this->safeConstraintInt($source['source_max_players'] ?? null, 1, 30);
            $duration = $this->safeConstraintInt($source['source_duration'] ?? null, 5, 90);
            $width = $this->safeConstraintInt($source['source_area_width_m'] ?? null, 3, 120);
            $height = $this->safeConstraintInt($source['source_area_height_m'] ?? null, 3, 120);
            $pattern = trim((string)($source['source_player_pattern'] ?? ''));
            $evidence = trim((string)($source['source_constraint_evidence'] ?? ''));

            if ($minPlayers === null || $maxPlayers === null || $duration === null || $width === null || $height === null || $pattern === '') {
                $parsed = $this->parseSourceConstraintsFromText(
                    trim((string)($source['title'] ?? ''))
                    . ' ' . trim((string)($source['summary'] ?? ''))
                    . ' ' . trim((string)($source['transcript_excerpt'] ?? ''))
                );
                $minPlayers ??= $parsed['min_players'];
                $maxPlayers ??= $parsed['max_players'];
                $duration ??= $parsed['duration'];
                $width ??= $parsed['area_width_m'];
                $height ??= $parsed['area_height_m'];
                if ($pattern === '' && ($parsed['player_pattern'] ?? '') !== '') {
                    $pattern = (string)$parsed['player_pattern'];
                }
                if ($evidence === '' && ($parsed['evidence'] ?? '') !== '') {
                    $evidence = (string)$parsed['evidence'];
                }
            }

            if ($constraints['min_players'] === null && $minPlayers !== null) {
                $constraints['min_players'] = $minPlayers;
            }
            if ($constraints['max_players'] === null && $maxPlayers !== null) {
                $constraints['max_players'] = $maxPlayers;
            }
            if ($constraints['duration'] === null && $duration !== null) {
                $constraints['duration'] = $duration;
            }
            if ($constraints['area_width_m'] === null && $width !== null) {
                $constraints['area_width_m'] = $width;
            }
            if ($constraints['area_height_m'] === null && $height !== null) {
                $constraints['area_height_m'] = $height;
            }
            if ($constraints['player_pattern'] === null && $pattern !== '') {
                $constraints['player_pattern'] = $pattern;
            }
            if ($evidence !== '') {
                $constraints['evidence'][] = $evidence;
            }
        }

        $constraints['evidence'] = array_values(array_unique(array_filter($constraints['evidence'])));
        return $constraints;
    }

    public function safeConstraintInt(mixed $value, int $min, int $max): ?int {
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

    public function hasPlayerConstraintInText(string $text): bool {
        return preg_match('/\b\d{1,2}\s*(?:-|tot|to)\s*\d{1,2}\s*spelers?\b/i', $text) === 1
            || preg_match('/\b\d{1,2}\s*spelers?\b/i', $text) === 1
            || preg_match('/\b\d{1,2}\s*(?:v|vs|x|tegen)\s*\d{1,2}\b/i', $text) === 1;
    }

    public function hasDurationConstraintInText(string $text): bool {
        return preg_match('/\b\d{1,2}\s*(?:min(?:uten)?|minutes?)\b/i', $text) === 1;
    }

    private function stringLength(string $value): int {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        return strlen($value);
    }

    private function parseSourceConstraintsFromText(string $text): array {
        $text = trim($text);
        if ($text === '') {
            return [
                'min_players' => null,
                'max_players' => null,
                'duration' => null,
                'area_width_m' => null,
                'area_height_m' => null,
                'player_pattern' => null,
                'evidence' => null,
            ];
        }

        $minPlayers = null;
        $maxPlayers = null;
        $duration = null;
        $areaWidth = null;
        $areaHeight = null;
        $playerPattern = null;
        $evidence = [];

        if (preg_match('/\b(\d{1,2})\s*(?:v|vs|x|tegen)\s*(\d{1,2})\b/u', $text, $matches) === 1) {
            $a = (int)$matches[1];
            $b = (int)$matches[2];
            if ($a > 0 && $b > 0) {
                $total = $a + $b;
                if ($total >= 1 && $total <= 30) {
                    $minPlayers = $total;
                    $maxPlayers = $total;
                    $playerPattern = $a . 'v' . $b;
                    $evidence[] = $playerPattern;
                }
            }
        }

        if ($minPlayers === null || $maxPlayers === null) {
            if (preg_match('/\b(\d{1,2})\s*(?:-|tot|to)\s*(\d{1,2})\s*(?:spelers?|players?)\b/u', $text, $matches) === 1) {
                $a = (int)$matches[1];
                $b = (int)$matches[2];
                $minPlayers = min($a, $b);
                $maxPlayers = max($a, $b);
                $evidence[] = $a . '-' . $b . ' spelers';
            } elseif (preg_match('/\b(\d{1,2})\s*(?:spelers?|players?)\b/u', $text, $matches) === 1) {
                $value = (int)$matches[1];
                if ($value >= 1 && $value <= 30) {
                    $minPlayers = $value;
                    $maxPlayers = $value;
                    $evidence[] = $value . ' spelers';
                }
            }
        }

        if (preg_match('/\b(\d{1,3})\s*[x×]\s*(\d{1,3})\s*(?:m|meter|meters|yd|yards)\b/u', $text, $matches) === 1) {
            $w = (int)$matches[1];
            $h = (int)$matches[2];
            if ($w >= 3 && $w <= 120 && $h >= 3 && $h <= 120) {
                $areaWidth = $w;
                $areaHeight = $h;
                $evidence[] = $w . 'x' . $h . 'm';
            }
        }

        if (preg_match('/\b(\d{1,2})\s*(?:min(?:uten)?|minutes?)\b/u', $text, $matches) === 1) {
            $duration = $this->safeConstraintInt((int)$matches[1], 5, 90);
            if ($duration !== null) {
                $duration = (int)round($duration / 5) * 5;
                $duration = max(5, min(90, $duration));
                $evidence[] = $duration . ' min';
            }
        }

        return [
            'min_players' => $minPlayers,
            'max_players' => $maxPlayers,
            'duration' => $duration,
            'area_width_m' => $areaWidth,
            'area_height_m' => $areaHeight,
            'player_pattern' => $playerPattern,
            'evidence' => !empty($evidence) ? implode('; ', $evidence) : null,
        ];
    }
}
