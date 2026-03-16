<?php
declare(strict_types=1);

class AiController extends BaseController {
    private AppSetting $settings;
    private OpenRouterClient $openRouterClient;
    private AiPricingEngine $pricingEngine;
    private KonvaSanitizer $konvaSanitizer;
    private AiStructuredOutputParser $outputParser;
    private AiExerciseOutputValidator $exerciseValidator;
    private AiRetrievalService $retrievalService;
    private AiUsageService $usageService;
    private AiQualityEventService $qualityEventService;
    private AiPromptBuilder $promptBuilder;
    private AiWorkflowService $workflowService;
    private AiSessionService $sessionService;
    private AiAccessService $accessService;
    private VideoFrameExtractor $frameExtractor;
    private VisualEvidenceService $visualEvidenceService;
    private SourceEvidenceFusionService $fusionService;
    private VideoSegmentationService $segmentationService;

    public function __construct(PDO $pdo, ?OpenRouterClient $openRouterClient = null) {
        parent::__construct($pdo);
        $this->settings = new AppSetting($pdo);
        $this->openRouterClient = $openRouterClient ?? new OpenRouterClient($pdo);
        $this->pricingEngine = new AiPricingEngine();
        $this->konvaSanitizer = new KonvaSanitizer();
        $this->outputParser = new AiStructuredOutputParser();
        $this->exerciseValidator = new AiExerciseOutputValidator();
        $this->retrievalService = new AiRetrievalService($pdo, null, $this->openRouterClient);
        $this->usageService = new AiUsageService($pdo);
        $this->qualityEventService = new AiQualityEventService($pdo);
        $this->promptBuilder = new AiPromptBuilder($pdo);
        $this->workflowService = new AiWorkflowService($pdo, $this->retrievalService, $this->promptBuilder, $this->openRouterClient);
        $this->sessionService = new AiSessionService($pdo);
        $this->accessService = new AiAccessService($pdo, $this->settings, $this->usageService);
        $this->frameExtractor = new VideoFrameExtractor();
        $this->visualEvidenceService = new VisualEvidenceService($this->openRouterClient, $this->promptBuilder);
        $this->fusionService = new SourceEvidenceFusionService();
        $this->segmentationService = new VideoSegmentationService();
    }

    public function sendMessage(): void {
        $this->requireAuth();
        $this->requireApiCsrf();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['ok' => false, 'error' => 'Deze actie mag nu niet.'], 405);
        }

        $userId = (int)Session::get('user_id');
        $teamId = $this->currentTeamId();

        $message = trim((string)($_POST['message'] ?? ''));
        $selectedVideoId = trim((string)($_POST['selected_video_id'] ?? ''));
        $selectedSegmentId = (int)($_POST['selected_segment_id'] ?? 0);
        $selectionOrigin = trim((string)($_POST['selection_origin'] ?? ''));
        $recoveryTriggerCode = trim((string)($_POST['recovery_trigger_code'] ?? ''));
        $conceptModeRequested = !empty($_POST['concept_mode']);
        $requestedMode = strtolower(trim((string)($_POST['mode'] ?? '')));
        $uploadedScreenshots = $this->parseUploadedScreenshotFrames($_FILES['screenshot_files'] ?? null);

        if (!($uploadedScreenshots['ok'] ?? false)) {
            $this->jsonResponse([
                'ok' => false,
                'error' => $uploadedScreenshots['error'] ?? 'Uploaden van beelden is mislukt.',
                'code' => $uploadedScreenshots['code'] ?? 'screenshot_upload_invalid',
            ], 422);
        }

        $screenshotFrames = is_array($uploadedScreenshots['frames'] ?? null) ? $uploadedScreenshots['frames'] : [];
        $hasScreenshotFrames = !empty($screenshotFrames);
        $mode = $this->resolveChatMode($requestedMode, $selectedVideoId, $selectedSegmentId, $hasScreenshotFrames);
        if ($mode === null) {
            $this->jsonResponse([
                'ok' => false,
                'error' => 'Deze chat-actie wordt niet herkend.',
                'code' => 'invalid_mode',
            ], 422);
        }

        if ($mode === 'search' && $message === '') {
            $this->jsonResponse([
                'ok' => false,
                'error' => 'Typ eerst een bericht.',
                'code' => 'message_required',
            ], 422);
        }

        if ($mode === 'generate' && $selectedVideoId === '') {
            $this->jsonResponse([
                'ok' => false,
                'error' => 'Kies eerst een video.',
                'code' => 'video_required',
            ], 422);
        }

        if ($mode === 'generate_segment' && ($selectedVideoId === '' || $selectedSegmentId <= 0)) {
            $this->jsonResponse([
                'ok' => false,
                'error' => 'Kies een video en segment om verder te gaan.',
                'code' => 'segment_required',
            ], 422);
        }

        if ($mode === 'screenshot_recovery' && !$hasScreenshotFrames) {
            $this->jsonResponse([
                'ok' => false,
                'error' => 'Voeg 2 tot 4 screenshots toe.',
                'code' => 'screenshot_upload_empty',
            ], 422);
        }

        if ($mode === 'refine_text' && $message === '') {
            $this->jsonResponse([
                'ok' => false,
                'error' => 'Typ eerst wat je wilt aanpassen.',
                'code' => 'refine_message_required',
            ], 422);
        }

        if ($mode === 'refine_drawing' && $message === '') {
            $this->jsonResponse([
                'ok' => false,
                'error' => 'Typ wat je in de tekening wilt aanpassen.',
                'code' => 'refine_drawing_input_required',
            ], 422);
        }

        $requestedModelId = trim((string)($_POST['model_id'] ?? ''));
        $access = $this->accessService->checkAiAccess($userId, $requestedModelId, true, true, true);

        if (!$access['ok']) {
            $this->usageService->logBlocked(
                $userId,
                $teamId,
                $access['model_id'] ?? ($requestedModelId !== '' ? $requestedModelId : 'unknown'),
                (string)($access['error_code'] ?? 'blocked')
            );

            $this->jsonResponse([
                'ok' => false,
                'error' => $access['error'],
                'code' => $access['error_code'] ?? 'blocked',
            ], (int)$access['status']);
        }

        $model = $access['model'];
        $settings = $access['settings'];

        $exerciseId = (int)($_POST['exercise_id'] ?? 0);
        $fieldTypeHint = trim((string)($_POST['field_type'] ?? 'portrait'));
        $fieldTypeHint = in_array($fieldTypeHint, ['portrait', 'landscape', 'square'], true) ? $fieldTypeHint : 'portrait';
        $formState = $this->parseFormState();

        try {
            if ($mode === 'generate' || $mode === 'generate_segment') {
                if (preg_match('/^[A-Za-z0-9_-]{6,20}$/', $selectedVideoId) !== 1) {
                    $this->jsonResponse(['ok' => false, 'error' => 'Deze video klopt niet.'], 422);
                }

                $userMessage = $message !== '' ? $message : 'Video geselecteerd';
                $maxSessions = max(1, (int)($settings['ai_max_sessions_per_user'] ?? 50));
                $sessionId = $this->sessionService->resolveSession((int)($_POST['session_id'] ?? 0), $userId, $teamId, $exerciseId, $userMessage, $maxSessions);
                $this->sessionService->insertChatMessage($sessionId, 'user', $userMessage, null, null);

                $coachRequest = $this->getOriginalCoachRequest($sessionId, $message);
                $segmentIdForGeneration = $mode === 'generate_segment' ? $selectedSegmentId : 0;

                $this->handleGenerationPhase(
                    $sessionId, $userId, $teamId, $exerciseId, $model, $settings,
                    $fieldTypeHint, $formState, $selectedVideoId, $coachRequest, $segmentIdForGeneration,
                    $selectionOrigin,
                    $recoveryTriggerCode,
                    $conceptModeRequested
                );
            } elseif ($mode === 'screenshot_recovery') {
                $maxSessions = max(1, (int)($settings['ai_max_sessions_per_user'] ?? 50));
                $userMessage = $message !== ''
                    ? $message
                    : ($hasScreenshotFrames ? $this->buildScreenshotUploadUserMessage($screenshotFrames) : '');
                $sessionId = $this->sessionService->resolveSession((int)($_POST['session_id'] ?? 0), $userId, $teamId, $exerciseId, $userMessage, $maxSessions);
                $this->sessionService->insertChatMessage($sessionId, 'user', $userMessage, null, null);

                $pendingScreenshot = $this->loadPendingScreenshotContext($sessionId);
                if ($pendingScreenshot !== null) {
                    $this->handlePendingScreenshotReply(
                        $sessionId,
                        $userId,
                        $teamId,
                        $exerciseId,
                        $model,
                        $settings,
                        $fieldTypeHint,
                        $formState,
                        $message,
                        $pendingScreenshot,
                        $screenshotFrames
                    );
                }

                $this->jsonResponse([
                    'ok' => false,
                    'session_id' => $sessionId,
                    'error' => 'Kies eerst een video. Daarna kun je screenshots toevoegen.',
                    'code' => 'screenshots_without_recovery_context',
                ], 422);
            } elseif ($mode === 'refine_text' || $mode === 'refine_drawing') {
                $maxSessions = max(1, (int)($settings['ai_max_sessions_per_user'] ?? 50));
                $userMessage = $message !== ''
                    ? $message
                    : ($hasScreenshotFrames ? $this->buildScreenshotUploadUserMessage($screenshotFrames) : '');
                $sessionId = $this->sessionService->resolveSession((int)($_POST['session_id'] ?? 0), $userId, $teamId, $exerciseId, $userMessage, $maxSessions);
                $this->sessionService->insertChatMessage($sessionId, 'user', $userMessage, null, null);

                $this->handleRefinementPhase(
                    $sessionId,
                    $userId,
                    $teamId,
                    $exerciseId,
                    $model,
                    $settings,
                    $fieldTypeHint,
                    $formState,
                    $message,
                    $mode,
                    $screenshotFrames
                );
            } else {
                $maxSessions = max(1, (int)($settings['ai_max_sessions_per_user'] ?? 50));
                $sessionId = $this->sessionService->resolveSession((int)($_POST['session_id'] ?? 0), $userId, $teamId, $exerciseId, $message, $maxSessions);
                $this->sessionService->insertChatMessage($sessionId, 'user', $message, null, null);

                if ($hasScreenshotFrames) {
                    $this->jsonResponse([
                        'ok' => false,
                        'session_id' => $sessionId,
                        'error' => 'Gebruik screenshots pas nadat je een video hebt gekozen.',
                        'code' => 'screenshots_outside_recovery_mode',
                    ], 422);
                }

                $directVideoIds = $this->retrievalService->extractYouTubeVideoIds($message);
                if (!empty($directVideoIds)) {
                    $this->handleGenerationPhase(
                        $sessionId, $userId, $teamId, $exerciseId, $model, $settings,
                        $fieldTypeHint, $formState, $directVideoIds[0], $message
                    );
                } elseif (($pendingConcept = $this->loadPendingConceptContext($sessionId)) !== null) {
                    $this->handlePendingConceptReply(
                        $sessionId,
                        $userId,
                        $teamId,
                        $exerciseId,
                        $model,
                        $settings,
                        $fieldTypeHint,
                        $formState,
                        $message,
                        $pendingConcept
                    );
                } else {
                    // Two-step flow: search and present video choices
                    $this->handleSearchResponse(
                        $sessionId, $userId, $teamId, $model, $settings, $formState, $message
                    );
                }
            }
        } catch (Throwable $e) {
            error_log(sprintf(
                '[AI] sendMessage failed: %s in %s:%d',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));

            $this->jsonResponse([
                'ok' => false,
                'error' => 'Het lukt nu even niet. Probeer opnieuw.',
                'code' => 'internal_error',
            ], 500);
        }
    }

    private function resolveChatMode(
        string $requestedMode,
        string $selectedVideoId,
        int $selectedSegmentId,
        bool $hasScreenshotFrames
    ): ?string {
        $allowedModes = ['search', 'generate', 'generate_segment', 'screenshot_recovery', 'refine_text', 'refine_drawing'];
        if ($requestedMode !== '') {
            return in_array($requestedMode, $allowedModes, true) ? $requestedMode : null;
        }

        if ($selectedVideoId !== '' && $selectedSegmentId > 0) {
            return 'generate_segment';
        }
        if ($selectedVideoId !== '') {
            return 'generate';
        }
        if ($hasScreenshotFrames) {
            return 'screenshot_recovery';
        }

        return 'search';
    }

    private function handleSearchResponse(
        int $sessionId,
        int $userId,
        ?int $teamId,
        array $model,
        array $settings,
        ?array $formState,
        string $message
    ): void {
        $chatHistory = $this->sessionService->getSessionMessagesForPrompt($sessionId);

        $result = $this->workflowService->handleSearchPhase(
            $message, $formState, $settings, $userId, (string)$model['model_id'], $chatHistory
        );

        if (!$result['ok']) {
            if (($result['code'] ?? '') === 'youtube_api_key_missing') {
                $this->usageService->logBlocked($userId, $teamId, (string)$model['model_id'], 'youtube_api_key_missing');
            }
            $this->jsonResponse([
                'ok' => false,
                'error' => $result['error'] ?? 'Zoeken is mislukt.',
                'code' => $result['code'] ?? 'search_failed',
            ], (int)($result['http_status'] ?? 503));
        }

        $videoChoices = $result['video_choices'];
        $warnings = $result['warnings'] ?? [];
        $usage = $result['usage'] ?? null;
        $count = count($videoChoices);
        $assistantText = $count === 1
            ? 'Ik vond 1 video die past:'
            : 'Ik vond ' . $count . ' video\'s die passen:';

        $metadata = [
            'video_choices' => $videoChoices,
            'phase' => 'search_results',
        ];
        if (!empty($warnings)) {
            $metadata['warnings'] = $warnings;
        }

        // Track ranking usage
        $usagePayload = null;
        if ($usage !== null) {
            $inputTokens = max(0, (int)($usage['prompt_tokens'] ?? 0));
            $outputTokens = max(0, (int)($usage['completion_tokens'] ?? 0));
            $totalTokens = $inputTokens + $outputTokens;
            $supplierCostUsd = round(max(0.0, (float)($usage['supplier_cost_usd'] ?? 0.0)), 6);

            $pricingBreakdown = $this->pricingEngine->calculate(
                is_array($model['pricing'] ?? null) ? $model['pricing'] : [],
                $inputTokens, $outputTokens
            );
            $billableCostEur = round(max(0.0, (float)($pricingBreakdown['billable_cost_eur'] ?? 0.0)), 6);

            $usageEventId = $this->usageService->createEvent(
                $userId, $teamId, $sessionId, null,
                (string)$model['model_id'], 'success', null
            );
            $this->usageService->updateEvent($usageEventId, [
                'status' => 'success',
                'error_code' => null,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $totalTokens,
                'supplier_cost_usd' => $supplierCostUsd,
                'billable_cost_eur' => $billableCostEur,
            ]);

            $usagePayload = [
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $totalTokens,
                'supplier_cost_usd' => $supplierCostUsd,
                'billable_cost_eur' => $billableCostEur,
            ];
        }

        $this->sessionService->insertChatMessage(
            $sessionId, 'assistant', $assistantText,
            (string)$model['model_id'],
            $this->encodeJson($metadata, '{}')
        );
        $this->logSearchQualityEvents($userId, $teamId, $sessionId, $videoChoices);

        $responsePayload = [
            'ok' => true,
            'session_id' => $sessionId,
            'phase' => 'search_results',
            'message' => [
                'role' => 'assistant',
                'content' => $assistantText,
                'created_at' => date('Y-m-d H:i:s'),
            ],
            'video_choices' => $videoChoices,
            'summary' => $this->usageService->buildSummary($userId, $this->accessService->getSettingsWithDefaults()),
        ];
        if ($usagePayload !== null) {
            $responsePayload['usage'] = $usagePayload;
        }

        $this->jsonResponse($responsePayload);
    }

    private function handleGenerationPhase(
        int $sessionId,
        int $userId,
        ?int $teamId,
        int $exerciseId,
        array $model,
        array $settings,
        string $fieldTypeHint,
        ?array $formState,
        string $videoId,
        string $message,
        int $selectedSegmentId = 0,
        string $selectionOrigin = '',
        string $recoveryTriggerCode = '',
        bool $conceptModeRequested = false
    ): void {
        $this->logVideoChoiceSelectionEvent(
            $userId,
            $teamId,
            $sessionId,
            $videoId,
            $selectionOrigin,
            $recoveryTriggerCode,
            $selectedSegmentId
        );

        // If a segment was selected, try to use cached source from the segment_choices response
        if ($selectedSegmentId > 0) {
            $cached = $this->loadCachedSegmentSource($sessionId);
            if ($cached !== null) {
                $source = $cached['source'];
                $sourcesUsed = $cached['sources_used'];
                $warnings = $cached['warnings'];
                $allSegments = $cached['segments'];

                $source = $this->scopeSourceToSegment($source, $allSegments, $selectedSegmentId);

                if ($conceptModeRequested) {
                    $this->startConceptMode(
                        $sessionId,
                        $userId,
                        $teamId,
                    $model,
                    $settings,
                    $formState,
                    $source,
                    $sourcesUsed,
                    $warnings,
                        $message,
                        $selectedSegmentId,
                        $recoveryTriggerCode
                    );
                    return;
                }

                $this->generateExercise(
                    $sessionId, $userId, $teamId, $exerciseId, $model,
                    $settings, $fieldTypeHint, $formState,
                    $source, $sourcesUsed, $warnings, $message
                );
                return; // Cache hit — skip full pipeline
            }
        }

        $retrieval = $this->retrievalService->enrichSelectedVideo($videoId, $settings);

        if (!$retrieval['ok']) {
            $this->jsonResponse([
                'ok' => false,
                'error' => $retrieval['error'] ?? 'Ik kan deze video nu niet openen.',
                'code' => 'retrieval_failed',
            ], (int)($retrieval['http_status'] ?? 503));
        }

        $source = $retrieval['source'];
        $sourcesUsed = $retrieval['sources_used'] ?? [];
        $warnings = $retrieval['warnings'] ?? [];

        if ($conceptModeRequested) {
            $this->startConceptMode(
                $sessionId,
                $userId,
                $teamId,
                $model,
                $settings,
                $formState,
                $source,
                $sourcesUsed,
                $warnings,
                $message,
                $selectedSegmentId,
                $recoveryTriggerCode
            );
            return;
        }

        $technicalViability = $this->workflowService->assessTechnicalViability($source);
        $this->logSelectedVideoPreflightEvent(
            $userId,
            $teamId,
            $sessionId,
            $videoId,
            $source,
            $technicalViability,
            $selectedSegmentId
        );

        if (!($technicalViability['is_selectable'] ?? false)) {
            $assistantText = $this->workflowService->buildTechnicalPreflightWarning($technicalViability);
            $recoveryPayload = $this->buildRecoveryPayload($sessionId, $videoId);
            $conceptRecovery = $this->buildConceptRecoveryPayload($source, 'video_preflight_failed');
            $screenshotRecovery = $this->buildScreenshotRecoveryPayload($source, $settings, 'video_preflight_failed');
            $assistantText = $this->augmentFailureMessageWithRecovery($assistantText, $recoveryPayload, $conceptRecovery, $screenshotRecovery);
            $this->logGenerationBlockerQualityEvents(
                $userId,
                $teamId,
                $sessionId,
                'video_preflight_failed',
                $videoId,
                [
                    'phase' => 'generation_preflight',
                    'selected_segment_id' => $selectedSegmentId > 0 ? $selectedSegmentId : null,
                    'sources_used_count' => count($sourcesUsed),
                ] + $this->buildTechnicalPreflightEventPayload(
                    is_array($source['technical_preflight'] ?? null) ? $source['technical_preflight'] : [],
                    $technicalViability
                ),
                $recoveryPayload,
                $conceptRecovery,
                $screenshotRecovery
            );
            $metadata = [
                'sources_used' => $sourcesUsed,
                'technical_preflight' => $source['technical_preflight'] ?? null,
                'technical_viability' => $technicalViability,
            ];
            if (!empty($recoveryPayload['recovery_video_choices'])) {
                $metadata['recovery_video_choices'] = $recoveryPayload['recovery_video_choices'];
            }
            if (!empty($conceptRecovery['concept_recovery'])) {
                $metadata['concept_recovery'] = $conceptRecovery['concept_recovery'];
            }
            if (!empty($screenshotRecovery['screenshot_recovery'])) {
                $metadata['screenshot_recovery'] = $screenshotRecovery['screenshot_recovery'];
                $metadata['screenshot_context'] = $this->buildScreenshotRecoveryContext(
                    $source,
                    $sourcesUsed,
                    $warnings,
                    $message,
                    $selectedSegmentId,
                    'video_preflight_failed'
                );
            }
            $this->sessionService->insertChatMessage(
                $sessionId,
                'assistant',
                $assistantText,
                (string)$model['model_id'],
                $this->encodeJson($metadata, '{}')
            );

            $this->jsonResponse([
                'ok' => false,
                'session_id' => $sessionId,
                'error' => $assistantText,
                'code' => 'video_preflight_failed',
                'sources_used' => $sourcesUsed,
                'technical_preflight' => $source['technical_preflight'] ?? null,
                'technical_viability' => $technicalViability,
            ] + $recoveryPayload + $conceptRecovery + $screenshotRecovery, 422);
        }

        // Extract keyframes for visual evidence (non-blocking: failures add warning but don't stop generation)
        $visionModel = $this->accessService->resolveVisionModel();
        if ($visionModel !== null) {
            $duration = (int)($source['duration_seconds'] ?? 0);
            $chapters = is_array($source['chapters'] ?? null) ? $source['chapters'] : [];
            $cookiesPath = trim((string)($settings['ai_ytdlp_cookies_path'] ?? ''));
            $frameResult = $this->frameExtractor->extractFrames(
                $videoId,
                $duration,
                $chapters,
                10,
                $cookiesPath !== '' ? $cookiesPath : null
            );
            $this->logFrameDownloadQualityEvent(
                $userId,
                $teamId,
                $sessionId,
                $videoId,
                $frameResult,
                $selectedSegmentId
            );

            if ($frameResult['ok'] && !empty($frameResult['frames'])) {
                $source['visual_frames'] = $frameResult['frames'];
                $source['visual_frame_count'] = count($frameResult['frames']);
                $source['visual_status'] = 'frames_ready';
                $source['visual_error'] = null;

                // Run vision analysis on extracted frames
                $visionResult = $this->visualEvidenceService->analyseFrames(
                    $frameResult['frames'],
                    $source,
                    $message,
                    (string)$visionModel['model_id'],
                    $userId
                );

                if ($visionResult['ok'] && !empty($visionResult['visual_facts'])) {
                    $source['visual_facts'] = $visionResult['visual_facts'];
                    $source['visual_confidence'] = $visionResult['visual_facts']['confidence'] ?? 'low';
                    $source['visual_usage'] = $visionResult['usage'] ?? [];
                    $source['visual_status'] = 'ok';
                    $source['visual_error'] = null;
                } else {
                    $source['visual_facts'] = null;
                    $source['visual_confidence'] = 'none';
                    $source['visual_status'] = 'analysis_failed';
                    $source['visual_error'] = trim((string)($visionResult['error'] ?? ''));
                    $warnings[] = 'De beeldcheck lukte niet, dus ik werk vooral met de tekst van de video.';
                }
            } else {
                $source['visual_frames'] = [];
                $source['visual_frame_count'] = 0;
                $source['visual_facts'] = null;
                $source['visual_confidence'] = 'none';
                $source['visual_status'] = 'frame_extraction_failed';
                $source['visual_error'] = trim((string)($frameResult['error'] ?? ''));
                $warnings[] = 'Ik kon geen bruikbare beelden uit de video halen, dus ik werk vooral met de tekst.';
            }
        } else {
            $source['visual_frames'] = [];
            $source['visual_frame_count'] = 0;
            $source['visual_facts'] = null;
            $source['visual_confidence'] = 'none';
            $source['visual_status'] = 'disabled_no_model';
            $source['visual_error'] = null;
        }

        // Segmentation: detect multiple drills/variations
        $segmentation = $this->segmentationService->segment($source, $source['visual_facts'] ?? null);
        $contentSegments = array_values(array_filter(
            $segmentation['segments'],
            fn(array $seg) => ($seg['type'] ?? '') !== 'skip'
        ));

        // Fallback: if segmentation collapses to 1 segment while chapters exist, force chapter-level choices.
        if (count($contentSegments) <= 1) {
            $chapterFallbackSegments = $this->buildChapterFallbackSegments($source);
            if (count($chapterFallbackSegments) > 1) {
                $contentSegments = $chapterFallbackSegments;
                $segmentation['segments'] = $chapterFallbackSegments;
                $segmentation['boundary_uncertainties'] = [];
                $meta = is_array($segmentation['meta'] ?? null) ? $segmentation['meta'] : [];
                $signalsUsed = is_array($meta['signals_used'] ?? null) ? $meta['signals_used'] : [];
                if (!in_array('chapter_fallback', $signalsUsed, true)) {
                    $signalsUsed[] = 'chapter_fallback';
                }
                $segmentation['meta'] = [
                    'total_duration' => max(0, (int)($source['duration_seconds'] ?? 0)),
                    'segment_count' => count($chapterFallbackSegments),
                    'signals_used' => $signalsUsed,
                ];
            }
        }

        // If multiple content segments and no segment selected yet → return segment choices
        if (count($contentSegments) > 1 && $selectedSegmentId === 0) {
            $this->returnSegmentChoices(
                $sessionId, $model, $videoId, $segmentation, $contentSegments,
                $source, $sourcesUsed, $warnings
            );
        }

        // Scope source data to selected segment (if applicable)
        if ($selectedSegmentId > 0 && count($contentSegments) > 1) {
            $source = $this->scopeSourceToSegment($source, $segmentation['segments'], $selectedSegmentId);
        }

        $this->generateExercise(
            $sessionId, $userId, $teamId, $exerciseId, $model,
            $settings, $fieldTypeHint, $formState,
            $source, $sourcesUsed, $warnings, $message
        );
    }

    /**
     * Get the original coach request from the session for generation context.
     * When user selects a video (step 2), the original request from step 1 is more useful.
     */
    private function getOriginalCoachRequest(int $sessionId, string $currentMessage): string {
        $currentMessage = trim($currentMessage);
        if ($currentMessage !== '' && !$this->isVideoSelectionPlaceholder($currentMessage)) {
            return $currentMessage;
        }

        $stmt = $this->pdo->prepare(
            "SELECT content FROM ai_chat_messages
             WHERE session_id = :session_id AND role = 'user'
             ORDER BY created_at ASC, id ASC LIMIT 20"
        );
        $stmt->execute([':session_id' => $sessionId]);
        while ($value = $stmt->fetchColumn()) {
            $candidate = trim((string)$value);
            if ($candidate === '' || $this->isVideoSelectionPlaceholder($candidate)) {
                continue;
            }
            return $candidate;
        }

        return 'Genereer een oefening op basis van de geselecteerde video.';
    }

    private function isVideoSelectionPlaceholder(string $content): bool
    {
        $normalized = strtolower(trim($content));
        if ($normalized === '' || $normalized === 'video geselecteerd') {
            return true;
        }

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $content) === 1) {
            return true;
        }

        if (preg_match('/https?:\/\/(?:www\.)?(?:youtube\.com|youtu\.be)\//i', $content) === 1) {
            return true;
        }

        return false;
    }

    /**
     * Return segment choices to the user when a video contains multiple drills/variations.
     */
    private function returnSegmentChoices(
        int $sessionId,
        array $model,
        string $videoId,
        array $segmentation,
        array $contentSegments,
        array $source,
        array $sourcesUsed,
        array $warnings
    ): void {
        $choices = [];
        foreach ($contentSegments as $seg) {
            $startFormatted = $this->formatSeconds((float)($seg['start_seconds'] ?? 0));
            $endFormatted = $this->formatSeconds((float)($seg['end_seconds'] ?? 0));
            $durationSec = max(0, (int)(($seg['end_seconds'] ?? 0) - ($seg['start_seconds'] ?? 0)));
            $evidenceSummary = [];
            foreach (($seg['evidence'] ?? []) as $ev) {
                $evidenceSummary[] = (string)($ev['detail'] ?? '');
            }

            $choices[] = [
                'segment_id' => (int)($seg['id'] ?? 0),
                'title' => (string)($seg['title'] ?? 'Segment'),
                'type' => (string)($seg['type'] ?? 'drill'),
                'start_seconds' => (float)($seg['start_seconds'] ?? 0),
                'end_seconds' => (float)($seg['end_seconds'] ?? 0),
                'start_formatted' => $startFormatted,
                'end_formatted' => $endFormatted,
                'duration_seconds' => $durationSec,
                'confidence' => (string)($seg['confidence'] ?? 'medium'),
                'evidence' => $evidenceSummary,
                'chapter_titles' => $seg['chapter_titles'] ?? [],
            ];
        }

        $segmentCount = count($choices);
        $drillCount = count(array_filter($choices, fn($c) => $c['type'] === 'drill'));
        $variationCount = $segmentCount - $drillCount;

        $assistantText = 'Deze video bevat ' . $segmentCount . ' onderdelen';
        if ($drillCount > 0 && $variationCount > 0) {
            $assistantText .= ' (' . $drillCount . ' oefening' . ($drillCount > 1 ? 'en' : '')
                . ' en ' . $variationCount . ' variatie' . ($variationCount > 1 ? 's' : '') . ')';
        }
        $assistantText .= '. Kies welk onderdeel je wilt omzetten:';

        $metadata = [
            'segment_choices' => $choices,
            'video_id' => $videoId,
            'phase' => 'segment_choices',
            'segmentation_meta' => $segmentation['meta'] ?? [],
            'boundary_uncertainties' => $segmentation['boundary_uncertainties'] ?? [],
            'cached_source' => $this->buildCacheableSource($source),
            'cached_sources_used' => $sourcesUsed,
            'cached_warnings' => $warnings,
            'cached_segments' => $segmentation['segments'],
        ];

        $this->sessionService->insertChatMessage(
            $sessionId,
            'assistant',
            $assistantText,
            (string)$model['model_id'],
            $this->encodeJson($metadata, '{}')
        );

        $this->jsonResponse([
            'ok' => true,
            'session_id' => $sessionId,
            'phase' => 'segment_choices',
            'message' => [
                'role' => 'assistant',
                'content' => $assistantText,
                'created_at' => date('Y-m-d H:i:s'),
            ],
            'segment_choices' => $choices,
            'video_id' => $videoId,
        ]);
    }

    /**
     * Build deterministic chapter-based segments when automatic segmentation is too conservative.
     */
    private function buildChapterFallbackSegments(array $source): array
    {
        $normalized = [];
        $chapters = is_array($source['chapters'] ?? null) ? $source['chapters'] : [];
        foreach ($chapters as $chapter) {
            if (!is_array($chapter)) {
                continue;
            }

            $seconds = null;
            if (isset($chapter['seconds']) && is_numeric((string)$chapter['seconds'])) {
                $seconds = (float)$chapter['seconds'];
            } elseif (isset($chapter['start_seconds']) && is_numeric((string)$chapter['start_seconds'])) {
                $seconds = (float)$chapter['start_seconds'];
            } elseif (isset($chapter['start']) && is_numeric((string)$chapter['start'])) {
                $seconds = (float)$chapter['start'];
            } else {
                $seconds = $this->parseTimestampToSeconds((string)($chapter['timestamp'] ?? ''));
            }

            if ($seconds === null || $seconds < 0) {
                continue;
            }

            $label = trim((string)($chapter['label'] ?? $chapter['title'] ?? ''));
            $normalized[] = [
                'seconds' => $seconds,
                'title' => $label,
            ];
        }

        if (count($normalized) < 2) {
            $textCandidates = [
                trim((string)($source['transcript_excerpt'] ?? '')),
                trim((string)($source['snippet'] ?? '')),
            ];
            foreach ($textCandidates as $text) {
                if ($text === '') {
                    continue;
                }
                $normalized = array_merge($normalized, $this->extractChapterCandidatesFromText($text));
            }
        }

        if (count($normalized) < 2) {
            $chapterCountHint = max(0, (int)($source['technical_preflight']['chapter_count'] ?? 0));
            if ($chapterCountHint >= 2) {
                return $this->buildSyntheticChapterFallbackSegments($source, $chapterCountHint);
            }
            return [];
        }

        $deduplicated = [];
        foreach ($normalized as $entry) {
            $seconds = max(0.0, (float)($entry['seconds'] ?? 0.0));
            $title = trim((string)($entry['title'] ?? ''));
            $key = ((string)(int)round($seconds)) . '|' . strtolower($title);
            $deduplicated[$key] = [
                'seconds' => $seconds,
                'title' => $title,
            ];
        }
        $normalized = array_values($deduplicated);

        if (count($normalized) < 2) {
            return [];
        }

        usort($normalized, static fn(array $a, array $b): int => $a['seconds'] <=> $b['seconds']);

        $duration = max(0.0, (float)($source['duration_seconds'] ?? 0));
        if ($duration <= 0.0) {
            $duration = (float)$normalized[count($normalized) - 1]['seconds'] + 30.0;
        }

        $segments = [];
        $segmentIndex = 0;
        $chapterCount = count($normalized);
        for ($i = 0; $i < $chapterCount; $i++) {
            $start = (float)$normalized[$i]['seconds'];
            $end = $i + 1 < $chapterCount ? (float)$normalized[$i + 1]['seconds'] : $duration;
            if ($end <= $start) {
                continue;
            }

            $segmentIndex++;
            $title = trim((string)($normalized[$i]['title'] ?? ''));
            if ($title === '') {
                $title = 'Hoofdstuk ' . $segmentIndex;
            }

            $segments[] = [
                'id' => 9000 + $segmentIndex,
                'start_seconds' => $start,
                'end_seconds' => $end,
                'title' => $title,
                'type' => 'drill',
                'confidence' => 'medium',
                'evidence' => [
                    ['signal' => 'chapter_fallback', 'detail' => 'Segment op basis van videohoofdstuk.'],
                ],
                'chapter_titles' => [$title],
            ];
        }

        return count($segments) > 1 ? $segments : [];
    }

    private function extractChapterCandidatesFromText(string $text): array
    {
        $parts = preg_split('/\s*\|\s*|\R+/u', trim($text)) ?: [];
        $candidates = [];

        foreach ($parts as $part) {
            $part = trim((string)$part);
            if ($part === '') {
                continue;
            }

            if (preg_match('/\b(\d{1,2}:\d{2}(?::\d{2})?)\s+(.+)$/u', $part, $matches) !== 1) {
                continue;
            }

            $seconds = $this->parseTimestampToSeconds((string)($matches[1] ?? ''));
            if ($seconds === null || $seconds < 0) {
                continue;
            }

            $title = trim((string)($matches[2] ?? ''));
            if ($title === '') {
                continue;
            }
            $title = preg_replace('/^[\-\u2022•]+/u', '', $title) ?? $title;
            $title = trim($title);
            if ($title === '') {
                continue;
            }

            $candidates[] = [
                'seconds' => (float)$seconds,
                'title' => $title,
            ];
        }

        return $candidates;
    }

    private function buildSyntheticChapterFallbackSegments(array $source, int $chapterCountHint): array
    {
        $chapterCount = max(2, min(10, $chapterCountHint));
        $duration = max(0.0, (float)($source['duration_seconds'] ?? 0));
        if ($duration < 10.0) {
            return [];
        }

        $step = $duration / $chapterCount;
        if ($step <= 1.0) {
            return [];
        }

        $segments = [];
        for ($index = 0; $index < $chapterCount; $index++) {
            $start = $step * $index;
            $end = $index === $chapterCount - 1 ? $duration : ($step * ($index + 1));
            if ($end <= $start) {
                continue;
            }

            $chapterNumber = $index + 1;
            $title = 'Hoofdstuk ' . $chapterNumber;
            $segments[] = [
                'id' => 9500 + $chapterNumber,
                'start_seconds' => $start,
                'end_seconds' => $end,
                'title' => $title,
                'type' => 'drill',
                'confidence' => 'low',
                'evidence' => [
                    ['signal' => 'chapter_fallback', 'detail' => 'Segment op basis van hoofdstuk-aantal in metadata.'],
                ],
                'chapter_titles' => [$title],
            ];
        }

        return count($segments) > 1 ? $segments : [];
    }

    /**
     * Scope a video source to a specific segment's time range.
     * Filters visual sequence entries and adjusts chapter data.
     */
    private function scopeSourceToSegment(array $source, array $allSegments, int $segmentId): array
    {
        $segment = null;
        foreach ($allSegments as $seg) {
            if (($seg['id'] ?? 0) === $segmentId) {
                $segment = $seg;
                break;
            }
        }
        if ($segment === null) {
            return $source;
        }

        $start = (float)($segment['start_seconds'] ?? 0);
        $end = (float)($segment['end_seconds'] ?? 0);

        $source['segment'] = $segment;
        $source['duration_seconds'] = max(1, (int)round($end - $start));

        // Filter chapters to the segment range
        if (!empty($source['chapters'])) {
            $filtered = [];
            foreach ($source['chapters'] as $ch) {
                $chSec = null;
                if (isset($ch['seconds']) && is_numeric((string)$ch['seconds'])) {
                    $chSec = (float)$ch['seconds'];
                } elseif (isset($ch['start_seconds']) && is_numeric((string)$ch['start_seconds'])) {
                    $chSec = (float)$ch['start_seconds'];
                } elseif (isset($ch['start']) && is_numeric((string)$ch['start'])) {
                    $chSec = (float)$ch['start'];
                } else {
                    $chSec = $this->parseTimestampToSeconds((string)($ch['timestamp'] ?? ''));
                }
                if ($chSec === null) {
                    continue;
                }
                if ($chSec >= $start && $chSec < $end) {
                    $filtered[] = $ch;
                }
            }
            $source['chapters'] = $filtered;
        }

        // Scope visual_facts sequence to segment range
        if (!empty($source['visual_facts']['sequence'])) {
            $filtered = [];
            foreach ($source['visual_facts']['sequence'] as $entry) {
                $ts = $this->parseTimestampToSeconds($entry['timestamp'] ?? '');
                if ($ts !== null && $ts >= $start && $ts < $end) {
                    $filtered[] = $entry;
                }
            }
            $source['visual_facts']['sequence'] = $filtered;
        }

        // Scope visual frames to segment range
        if (!empty($source['visual_frames'])) {
            $filtered = [];
            foreach ($source['visual_frames'] as $frame) {
                $ts = (float)($frame['timestamp'] ?? 0);
                if ($ts >= $start && $ts < $end) {
                    $filtered[] = $frame;
                }
            }
            $source['visual_frames'] = $filtered;
            $source['visual_frame_count'] = count($filtered);
        }

        $source = $this->scopeMetadataFallbackToSegment($source);

        return $source;
    }

    private function scopeMetadataFallbackToSegment(array $source): array
    {
        if (trim((string)($source['transcript_source'] ?? 'none')) !== 'metadata_fallback') {
            return $source;
        }

        $segment = is_array($source['segment'] ?? null) ? $source['segment'] : [];
        $segmentTitle = trim((string)($segment['title'] ?? ''));
        $chapterTitles = [];
        foreach (is_array($source['chapters'] ?? null) ? $source['chapters'] : [] as $chapter) {
            if (!is_array($chapter)) {
                continue;
            }

            $timestamp = trim((string)($chapter['timestamp'] ?? ''));
            $label = trim((string)($chapter['label'] ?? $chapter['title'] ?? ''));
            if ($label === '') {
                continue;
            }

            $chapterTitles[] = ($timestamp !== '' ? ($timestamp . ' ') : '') . $label;
        }
        if (empty($chapterTitles)) {
            $chapterTitles = array_values(array_filter(array_map(
                static fn(mixed $value): string => trim((string)$value),
                is_array($segment['chapter_titles'] ?? null) ? $segment['chapter_titles'] : []
            )));
        }

        $snippetParts = [];
        if ($segmentTitle !== '') {
            $snippetParts[] = 'Gekozen videodeel: ' . $segmentTitle;
        }
        if (!empty($chapterTitles)) {
            $snippetParts[] = 'Hoofdstuk: ' . implode(' | ', array_slice($chapterTitles, 0, 4));
        }

        if (!empty($snippetParts)) {
            $source['snippet'] = implode("\n", $snippetParts);
        }
        $source['transcript_excerpt'] = '';

        return $source;
    }

    private function formatSeconds(float $seconds): string
    {
        $seconds = max(0, (int)$seconds);
        $minutes = intdiv($seconds, 60);
        $secs = $seconds % 60;
        return sprintf('%d:%02d', $minutes, $secs);
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

    /**
     * Strip heavy binary data (base64 frames) from source so it can be stored as JSON in metadata.
     */
    private function buildCacheableSource(array $source): array
    {
        unset($source['visual_frames'], $source['visual_usage']);

        // Strip base64 from any remaining frame references
        if (isset($source['visual_frame_count'])) {
            $source['visual_frame_count'] = (int)$source['visual_frame_count'];
        }

        return $source;
    }

    private function persistSourceEvidenceToCache(array $source, array $sourceEvidence): void
    {
        // Never overwrite full-video cache with a segment-scoped source.
        if (is_array($source['segment'] ?? null)) {
            return;
        }
        $snippet = strtolower(trim((string)($source['snippet'] ?? '')));
        if ($snippet !== '' && str_starts_with($snippet, 'gekozen videodeel:')) {
            return;
        }

        $cacheable = $this->buildCacheableSource($source);
        $cacheable['provider'] = trim((string)($cacheable['provider'] ?? '')) !== ''
            ? trim((string)$cacheable['provider'])
            : 'youtube';
        $cacheable['source_evidence'] = $sourceEvidence;
        $this->retrievalService->persistSourceCache($cacheable);
    }

    private function buildSourceReviewPayload(array $sourceEvidence, ?array $sourceFacts = null): array
    {
        $display = $this->workflowService->describeSourceEvidence($sourceEvidence);
        $rating = $this->workflowService->buildTranslatabilityRating($sourceEvidence);
        $recognitionPoints = [];
        $evidenceItems = [];

        if (is_array($sourceFacts)) {
            foreach (is_array($sourceFacts['recognition_points'] ?? null) ? $sourceFacts['recognition_points'] : [] as $point) {
                $text = trim((string)$point);
                if ($text !== '') {
                    $recognitionPoints[] = $text;
                }
            }

            foreach (is_array($sourceFacts['evidence_items'] ?? null) ? $sourceFacts['evidence_items'] : [] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $fact = trim((string)($item['fact'] ?? ''));
                if ($fact === '') {
                    continue;
                }

                $sourceLabel = trim((string)($item['source'] ?? ''));
                $snippet = trim((string)($item['snippet'] ?? ''));
                $evidenceItems[] = [
                    'fact' => $fact,
                    'source' => $sourceLabel !== '' ? $sourceLabel : 'unknown',
                    'snippet' => $snippet,
                ];
            }
        }

        return [
            'label' => $display['label'],
            'summary' => $display['summary'],
            'warning' => $display['warning'],
            'score' => round((float)($sourceEvidence['score'] ?? 0.0), 2),
            'level' => trim((string)($sourceEvidence['level'] ?? 'low')),
            'translatability_rating' => (int)($rating['rating'] ?? 0),
            'translatability_label' => trim((string)($rating['label'] ?? '')),
            'recognition_points' => array_slice(array_values(array_unique($recognitionPoints)), 0, 4),
            'evidence_items' => array_slice($evidenceItems, 0, 3),
        ];
    }

    /**
     * Load cached source data from the segment_choices message in the session.
     * Returns null if no cache is found — caller should fall back to full pipeline.
     */
    private function loadCachedSegmentSource(int $sessionId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT metadata_json FROM ai_chat_messages
             WHERE session_id = :session_id AND role = 'assistant'
             ORDER BY created_at DESC, id DESC LIMIT 5"
        );
        $stmt->execute([':session_id' => $sessionId]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $metaJson = (string)($row['metadata_json'] ?? '');
            if ($metaJson === '') {
                continue;
            }
            $meta = json_decode($metaJson, true);
            if (!is_array($meta)) {
                continue;
            }
            if (($meta['phase'] ?? '') === 'segment_choices' && !empty($meta['cached_source'])) {
                return [
                    'source' => $meta['cached_source'],
                    'sources_used' => $meta['cached_sources_used'] ?? [],
                    'warnings' => $meta['cached_warnings'] ?? [],
                    'segments' => $meta['cached_segments'] ?? [],
                ];
            }
        }

        return null;
    }

    private function buildRecoveryPayload(int $sessionId, string $excludeVideoId, int $limit = 3): array
    {
        $choices = $this->loadRecoveryVideoChoices($sessionId, $excludeVideoId, $limit);
        if (empty($choices)) {
            return [];
        }

        return [
            'recovery_video_choices' => $choices,
            'recovery_message' => 'Kies een andere video om verder te gaan.',
        ];
    }

    private function buildScreenshotRecoveryPayload(array $source, array $settings, string $triggerCode = ''): array
    {
        if (!$this->canOfferScreenshotRecovery($source, $settings)) {
            return [];
        }

        return [
            'screenshot_recovery' => [
                'video_id' => trim((string)($source['external_id'] ?? '')),
                'video_title' => trim((string)($source['title'] ?? '')),
                'trigger_code' => trim($triggerCode),
                'message' => 'Speelt deze video bij jou wel? Upload dan 2 tot 4 screenshots van begin, actie en wissel.',
                'upload_hint' => 'Gebruik 2 tot 4 duidelijke screenshots uit de video. Daarna kijk ik opnieuw.',
            ],
            'screenshot_message' => 'Upload 2 tot 4 screenshots uit de video om verder te gaan.',
        ];
    }

    private function buildScreenshotRecoveryContext(
        array $source,
        array $sourcesUsed,
        array $warnings,
        string $coachRequest,
        int $selectedSegmentId = 0,
        string $triggerCode = ''
    ): array {
        return [
            'source' => $this->buildCacheableSource($source),
            'sources_used' => $sourcesUsed,
            'warnings' => $warnings,
            'coach_request' => $coachRequest,
            'selected_segment_id' => $selectedSegmentId > 0 ? $selectedSegmentId : null,
            'trigger_code' => trim($triggerCode),
        ];
    }

    private function buildConceptRecoveryPayload(array $source, string $triggerCode = ''): array
    {
        if (!$this->canOfferConceptRecovery($source)) {
            return [];
        }

        return [
            'concept_recovery' => [
                'video_id' => trim((string)($source['external_id'] ?? '')),
                'video_title' => trim((string)($source['title'] ?? '')),
                'trigger_code' => trim($triggerCode),
                'message' => 'Ik kan ook alvast een eerste opzet voor je maken.',
            ],
            'concept_message' => 'Ik stel eerst 1 of 2 korte vragen. Daarna maak ik een eerste opzet.',
        ];
    }

    private function canOfferConceptRecovery(array $source): bool
    {
        if (trim((string)($source['external_id'] ?? '')) === '') {
            return false;
        }

        if (trim((string)($source['title'] ?? '')) !== '') {
            return true;
        }

        if (trim((string)($source['snippet'] ?? '')) !== '') {
            return true;
        }

        if (trim((string)($source['transcript_excerpt'] ?? '')) !== '') {
            return true;
        }

        return !empty($source['chapters']) && is_array($source['chapters']);
    }

    private function canOfferScreenshotRecovery(array $source, array $settings): bool
    {
        if (trim((string)($source['external_id'] ?? '')) === '') {
            return false;
        }

        return $this->accessService->resolveVisionModel($settings) !== null;
    }

    private function augmentFailureMessageWithRecovery(
        string $assistantText,
        array $recoveryPayload,
        array $conceptRecovery = [],
        array $screenshotRecovery = []
    ): string
    {
        $parts = [rtrim($assistantText)];

        if (!empty($recoveryPayload['recovery_video_choices'])) {
            $parts[] = 'Hieronder staan andere video\'s die je kunt proberen.';
        }

        if (!empty($conceptRecovery['concept_recovery'])) {
            $parts[] = 'Ik kan ook eerst een eerste opzet voor je maken.';
        }

        if (!empty($screenshotRecovery['screenshot_recovery'])) {
            $parts[] = 'Speelt de video bij jou wel? Dan kun je ook screenshots uploaden.';
        }

        return implode(' ', $parts);
    }

    private function loadPendingConceptContext(int $sessionId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT metadata_json
             FROM ai_chat_messages
             WHERE session_id = :session_id AND role = 'assistant'
             ORDER BY created_at DESC, id DESC LIMIT 1"
        );
        $stmt->execute([':session_id' => $sessionId]);
        $metaJson = trim((string)($stmt->fetchColumn() ?: ''));
        if ($metaJson === '') {
            return null;
        }

        $meta = json_decode($metaJson, true);
        if (!is_array($meta) || ($meta['phase'] ?? '') !== 'concept_questions') {
            return null;
        }

        $context = is_array($meta['concept_context'] ?? null) ? $meta['concept_context'] : [];
        if (empty($context['source']) || !is_array($context['source'])) {
            return null;
        }

        return $context;
    }

    private function loadPendingScreenshotContext(int $sessionId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT metadata_json
             FROM ai_chat_messages
             WHERE session_id = :session_id AND role = 'assistant'
             ORDER BY created_at DESC, id DESC LIMIT 5"
        );
        $stmt->execute([':session_id' => $sessionId]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $metaJson = trim((string)($row['metadata_json'] ?? ''));
            if ($metaJson === '') {
                continue;
            }

            $meta = json_decode($metaJson, true);
            if (!is_array($meta)) {
                continue;
            }

            $context = is_array($meta['screenshot_context'] ?? null) ? $meta['screenshot_context'] : [];
            if (empty($context['source']) || !is_array($context['source'])) {
                continue;
            }

            return $context;
        }

        return null;
    }

    private function loadRecoveryVideoChoices(int $sessionId, string $excludeVideoId, int $limit = 3): array
    {
        $limit = max(1, min(5, $limit));
        $excludeVideoId = trim($excludeVideoId);
        $stmt = $this->pdo->prepare(
            "SELECT metadata_json FROM ai_chat_messages
             WHERE session_id = :session_id AND role = 'assistant'
             ORDER BY created_at DESC, id DESC LIMIT 12"
        );
        $stmt->execute([':session_id' => $sessionId]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $metaJson = trim((string)($row['metadata_json'] ?? ''));
            if ($metaJson === '') {
                continue;
            }

            $meta = json_decode($metaJson, true);
            if (!is_array($meta) || ($meta['phase'] ?? '') !== 'search_results') {
                continue;
            }

            $videoChoices = is_array($meta['video_choices'] ?? null) ? $meta['video_choices'] : [];
            if (empty($videoChoices)) {
                continue;
            }

            $result = [];
            $seen = [];
            foreach ($videoChoices as $choice) {
                if (!is_array($choice)) {
                    continue;
                }

                $videoId = trim((string)($choice['video_id'] ?? ''));
                if ($videoId === '' || $videoId === $excludeVideoId || isset($seen[$videoId])) {
                    continue;
                }

                if (($choice['is_selectable'] ?? false) !== true) {
                    continue;
                }

                $seen[$videoId] = true;
                $result[] = $choice;
                if (count($result) >= $limit) {
                    break;
                }
            }

            if (!empty($result)) {
                return $result;
            }
        }

        return [];
    }

    private function startConceptMode(
        int $sessionId,
        int $userId,
        ?int $teamId,
        array $model,
        array $settings,
        ?array $formState,
        array $source,
        array $sourcesUsed,
        array $warnings,
        string $coachRequest,
        int $selectedSegmentId = 0,
        string $triggerCode = ''
    ): void {
        if (!$this->canOfferConceptRecovery($source)) {
            $this->jsonResponse([
                'ok' => false,
                'session_id' => $sessionId,
                'error' => 'Ik kan van deze video ook geen eerste opzet maken.',
                'code' => 'concept_mode_unavailable',
            ], 422);
        }

        $questions = $this->buildConceptQuestions($coachRequest, $formState);
        $assistantText = 'Ik kan hier wel een eerste opzet van maken.';
        if (!empty($questions)) {
            $assistantText .= ' Beantwoord eerst kort deze vragen in 1 bericht:';
            foreach ($questions as $index => $question) {
                $assistantText .= ' ' . ($index + 1) . '. ' . $question;
            }
        } else {
            $assistantText .= ' Stuur in 1 bericht nog even leeftijd of niveau en het belangrijkste doel. Dan maak ik een eerste opzet.';
        }
        $assistantText .= ' Ik zet er duidelijk bij dat dit een eerste opzet is.';

        $sourceEvidence = $this->workflowService->assessSourceEvidence($source, $settings);
        $metadata = [
            'phase' => 'concept_questions',
            'concept_mode' => [
                'status' => 'awaiting_answers',
                'trigger_code' => trim($triggerCode),
            ],
            'concept_context' => [
                'source' => $this->buildCacheableSource($source),
                'sources_used' => $sourcesUsed,
                'warnings' => $warnings,
                'coach_request' => $coachRequest,
                'selected_segment_id' => $selectedSegmentId > 0 ? $selectedSegmentId : null,
                'questions' => $questions,
            ],
            'source_evidence' => $sourceEvidence,
        ];

        $this->sessionService->insertChatMessage(
            $sessionId,
            'assistant',
            $assistantText,
            (string)$model['model_id'],
            $this->encodeJson($metadata, '{}')
        );

        $this->safeLogQualityEvent(
            $userId,
            $teamId,
            $sessionId,
            'concept_mode_started',
            'pending',
            [
                'trigger_code' => trim($triggerCode),
                'selected_segment_id' => $selectedSegmentId > 0 ? $selectedSegmentId : null,
                'question_count' => count($questions),
                'source_evidence_level' => trim((string)($sourceEvidence['level'] ?? 'low')),
                'source_evidence_score' => (float)($sourceEvidence['score'] ?? 0.0),
            ],
            trim((string)($source['external_id'] ?? '')) !== '' ? trim((string)($source['external_id'] ?? '')) : null
        );

        $this->jsonResponse([
            'ok' => true,
            'session_id' => $sessionId,
            'phase' => 'concept_questions',
            'message' => [
                'role' => 'assistant',
                'content' => $assistantText,
                'created_at' => date('Y-m-d H:i:s'),
            ],
            'concept_mode' => [
                'active' => true,
                'status' => 'awaiting_answers',
                'questions' => $questions,
            ],
        ]);
    }

    private function handlePendingConceptReply(
        int $sessionId,
        int $userId,
        ?int $teamId,
        int $exerciseId,
        array $model,
        array $settings,
        string $fieldTypeHint,
        ?array $formState,
        string $message,
        array $context
    ): void {
        $source = is_array($context['source'] ?? null) ? $context['source'] : [];
        $sourcesUsed = is_array($context['sources_used'] ?? null) ? $context['sources_used'] : [];
        $warnings = is_array($context['warnings'] ?? null) ? $context['warnings'] : [];
        $baseCoachRequest = trim((string)($context['coach_request'] ?? ''));
        $combinedCoachRequest = $baseCoachRequest !== ''
            ? $baseCoachRequest . "\nAanvullende coachinput: " . $message
            : $message;

        $this->generateConceptExercise(
            $sessionId,
            $userId,
            $teamId,
            $exerciseId,
            $model,
            $settings,
            $fieldTypeHint,
            $formState,
            $source,
            $sourcesUsed,
            $warnings,
            $combinedCoachRequest
        );
    }

    private function handlePendingScreenshotReply(
        int $sessionId,
        int $userId,
        ?int $teamId,
        int $exerciseId,
        array $model,
        array $settings,
        string $fieldTypeHint,
        ?array $formState,
        string $message,
        array $context,
        array $screenshotFrames
    ): void {
        $source = is_array($context['source'] ?? null) ? $context['source'] : [];
        $sourcesUsed = is_array($context['sources_used'] ?? null) ? $context['sources_used'] : [];
        $warnings = is_array($context['warnings'] ?? null) ? $context['warnings'] : [];
        $baseCoachRequest = trim((string)($context['coach_request'] ?? ''));
        $selectedSegmentId = max(0, (int)($context['selected_segment_id'] ?? 0));
        $triggerCode = trim((string)($context['trigger_code'] ?? ''));

        $combinedCoachRequest = trim($message);
        if ($baseCoachRequest !== '' && $combinedCoachRequest !== '') {
            $combinedCoachRequest = $baseCoachRequest . "\nAanvullende coachinput: " . $combinedCoachRequest;
        } elseif ($combinedCoachRequest === '') {
            $combinedCoachRequest = $baseCoachRequest !== ''
                ? $baseCoachRequest
                : 'Genereer een oefening op basis van de gekozen video en geüploade screenshots.';
        }

        $visionModel = $this->accessService->resolveVisionModel($settings);
        if ($visionModel === null) {
            $assistantText = 'Met screenshots verder gaan lukt nu even niet.';
            $this->sessionService->insertChatMessage(
                $sessionId,
                'assistant',
                $assistantText,
                (string)$model['model_id'],
                $this->encodeJson([], '{}')
            );

            $this->jsonResponse([
                'ok' => false,
                'session_id' => $sessionId,
                'error' => $assistantText,
                'code' => 'screenshot_recovery_unavailable',
            ], 422);
        }

        $source['visual_frames'] = $screenshotFrames;
        $source['visual_frame_count'] = count($screenshotFrames);
        $source['visual_status'] = 'uploaded_screenshots_ready';
        $source['visual_error'] = null;

        $visionResult = $this->visualEvidenceService->analyseFrames(
            $screenshotFrames,
            $source,
            $combinedCoachRequest,
            (string)$visionModel['model_id'],
            $userId
        );

        if (!($visionResult['ok'] ?? false) || empty($visionResult['visual_facts'])) {
            $source['visual_facts'] = null;
            $source['visual_confidence'] = 'none';
            $source['visual_status'] = 'uploaded_screenshots_failed';
            $source['visual_error'] = trim((string)($visionResult['error'] ?? ''));
            $sourceEvidence = $this->workflowService->assessSourceEvidence($source, $settings);
            $sourceReview = $this->buildSourceReviewPayload($sourceEvidence);
            $conceptRecovery = $this->buildConceptRecoveryPayload($source, 'screenshot_analysis_failed');
            $screenshotRecovery = $this->buildScreenshotRecoveryPayload($source, $settings, 'screenshot_analysis_failed');
            $assistantText = 'Ik zie in deze screenshots nog niet duidelijk genoeg hoe de oefening loopt. Upload 2 tot 4 scherpere beelden van begin, actie en wissel.';
            $assistantText = $this->augmentFailureMessageWithRecovery($assistantText, [], $conceptRecovery, $screenshotRecovery);

            $metadata = [
                'source_evidence' => $sourceEvidence,
                'source_review' => $sourceReview,
            ];
            if (!empty($conceptRecovery['concept_recovery'])) {
                $metadata['concept_recovery'] = $conceptRecovery['concept_recovery'];
            }
            if (!empty($screenshotRecovery['screenshot_recovery'])) {
                $metadata['screenshot_recovery'] = $screenshotRecovery['screenshot_recovery'];
                $metadata['screenshot_context'] = $this->buildScreenshotRecoveryContext(
                    $source,
                    $sourcesUsed,
                    $warnings,
                    $baseCoachRequest,
                    $selectedSegmentId,
                    'screenshot_analysis_failed'
                );
            }

            $this->safeLogQualityEvent(
                $userId,
                $teamId,
                $sessionId,
                'screenshot_recovery_submitted',
                'failed',
                [
                    'trigger_code' => $triggerCode !== '' ? $triggerCode : null,
                    'selected_segment_id' => $selectedSegmentId > 0 ? $selectedSegmentId : null,
                    'frame_count' => count($screenshotFrames),
                    'error' => trim((string)($visionResult['error'] ?? '')),
                ],
                trim((string)($source['external_id'] ?? '')) !== '' ? trim((string)($source['external_id'] ?? '')) : null
            );

            $this->sessionService->insertChatMessage(
                $sessionId,
                'assistant',
                $assistantText,
                (string)$model['model_id'],
                $this->encodeJson($metadata, '{}')
            );

            $this->jsonResponse([
                'ok' => false,
                'session_id' => $sessionId,
                'error' => $assistantText,
                'code' => 'screenshot_analysis_failed',
                'source_review' => $sourceReview,
            ] + $conceptRecovery + $screenshotRecovery, 422);
        }

        $source['visual_facts'] = $visionResult['visual_facts'];
        $source['visual_confidence'] = $visionResult['visual_facts']['confidence'] ?? 'low';
        $source['visual_usage'] = $visionResult['usage'] ?? [];
        $source['visual_status'] = 'uploaded_screenshots_ok';
        $source['visual_error'] = null;

        $warnings[] = 'Ik heb ook naar je screenshots gekeken.';
        $warnings = array_values(array_unique(array_filter(array_map(
            static fn(mixed $item): string => trim((string)$item),
            $warnings
        ))));
        $sourcesUsed = $this->appendCoachScreenshotSource($sourcesUsed, count($screenshotFrames));

        $this->safeLogQualityEvent(
            $userId,
            $teamId,
            $sessionId,
            'screenshot_recovery_submitted',
            'success',
            [
                'trigger_code' => $triggerCode !== '' ? $triggerCode : null,
                'selected_segment_id' => $selectedSegmentId > 0 ? $selectedSegmentId : null,
                'frame_count' => count($screenshotFrames),
                'visual_confidence' => trim((string)($source['visual_confidence'] ?? 'low')),
            ],
            trim((string)($source['external_id'] ?? '')) !== '' ? trim((string)($source['external_id'] ?? '')) : null
        );

        $this->generateExercise(
            $sessionId,
            $userId,
            $teamId,
            $exerciseId,
            $model,
            $settings,
            $fieldTypeHint,
            $formState,
            $source,
            $sourcesUsed,
            $warnings,
            $combinedCoachRequest
        );
    }

    private function appendCoachScreenshotSource(array $sourcesUsed, int $frameCount): array
    {
        foreach ($sourcesUsed as $source) {
            if (is_array($source) && trim((string)($source['provider'] ?? '')) === 'coach_upload') {
                return $sourcesUsed;
            }
        }

        $sourcesUsed[] = [
            'provider' => 'coach_upload',
            'title' => 'Coachscreenshots (' . $frameCount . ')',
            'url' => '',
        ];

        return $sourcesUsed;
    }

    private function buildScreenshotUploadUserMessage(array $frames): string
    {
        $count = count($frames);
        return 'Coachscreenshots geüpload (' . $count . ')';
    }

    private function parseUploadedScreenshotFrames(mixed $files): array
    {
        $normalizedFiles = $this->normalizeUploadedFiles($files);
        if (empty($normalizedFiles)) {
            return [
                'ok' => true,
                'frames' => [],
            ];
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $frames = [];

        foreach (array_slice($normalizedFiles, 0, 6) as $index => $file) {
            $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($error !== UPLOAD_ERR_OK) {
                return [
                    'ok' => false,
                    'code' => 'screenshot_upload_invalid',
                    'error' => $this->mapUploadErrorToMessage($error),
                ];
            }

            $tmpName = (string)($file['tmp_name'] ?? '');
            if ($tmpName === '' || !is_file($tmpName) || !is_readable($tmpName)) {
                return [
                    'ok' => false,
                    'code' => 'screenshot_upload_invalid',
                    'error' => 'Een van de screenshots kon ik niet openen.',
                ];
            }

            $size = max(0, (int)($file['size'] ?? 0));
            if ($size > 8 * 1024 * 1024) {
                return [
                    'ok' => false,
                    'code' => 'screenshot_upload_too_large',
                    'error' => 'Gebruik screenshots van maximaal 8 MB.',
                ];
            }

            $imageInfo = @getimagesize($tmpName);
            if (!is_array($imageInfo)) {
                return [
                    'ok' => false,
                    'code' => 'screenshot_upload_invalid',
                    'error' => 'Een van de bestanden is geen geldige afbeelding.',
                ];
            }

            $mimeType = (string)($imageInfo['mime'] ?? '');
            if (!in_array($mimeType, $allowedMimeTypes, true)) {
                return [
                    'ok' => false,
                    'code' => 'screenshot_upload_invalid_type',
                    'error' => 'Gebruik PNG, JPG of WebP.',
                ];
            }

            $contents = @file_get_contents($tmpName);
            if (!is_string($contents) || $contents === '') {
                return [
                    'ok' => false,
                    'code' => 'screenshot_upload_invalid',
                    'error' => 'Een van de screenshots kon ik niet lezen.',
                ];
            }

            $frames[] = [
                'data_uri' => 'data:' . $mimeType . ';base64,' . base64_encode($contents),
                'timestamp' => (float)$index,
                'timestamp_formatted' => 'Screenshot ' . ($index + 1),
                'source' => 'upload',
                'name' => trim((string)($file['name'] ?? '')),
            ];
        }

        if (empty($frames)) {
            return [
                'ok' => false,
                'code' => 'screenshot_upload_empty',
                'error' => 'Voeg 2 tot 4 screenshots toe.',
            ];
        }

        return [
            'ok' => true,
            'frames' => $frames,
        ];
    }

    private function normalizeUploadedFiles(mixed $files): array
    {
        if (!is_array($files) || !array_key_exists('name', $files)) {
            return [];
        }

        if (!is_array($files['name'])) {
            return [$files];
        }

        $normalized = [];
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            $normalized[] = [
                'name' => $files['name'][$i] ?? '',
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$i] ?? 0,
            ];
        }

        return $normalized;
    }

    private function mapUploadErrorToMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Een van de screenshots is te groot.',
            UPLOAD_ERR_PARTIAL => 'Een van de screenshots is niet helemaal geüpload.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'Opslaan van de screenshots lukte niet.',
            default => 'Uploaden van screenshots is mislukt.',
        };
    }

    private function buildConceptQuestions(string $coachRequest, ?array $formState): array
    {
        $questions = [];
        if (!$this->hasAgeOrLevelContext($coachRequest, $formState)) {
            $questions[] = 'Voor welke leeftijd of welk niveau wil je dit gebruiken?';
        }
        if (!$this->workflowService->hasPlayerConstraintInText($coachRequest)) {
            $minPlayers = $this->workflowService->safeConstraintInt($formState['min_players'] ?? null, 1, 30);
            $maxPlayers = $this->workflowService->safeConstraintInt($formState['max_players'] ?? null, 1, 30);
            if ($minPlayers === null && $maxPlayers === null) {
                $questions[] = 'Met hoeveel spelers wil je dit ongeveer draaien?';
            }
        }
        if (!$this->hasPrimaryGoalContext($coachRequest, $formState)) {
            $questions[] = 'Wat is je hoofdaccent: passen, vrijlopen, afwerken, omschakelen of iets anders?';
        }

        return array_slice($questions, 0, 2);
    }

    private function hasAgeOrLevelContext(string $coachRequest, ?array $formState): bool
    {
        if (preg_match('/\b(?:jo|u)\d{1,2}\b/i', $coachRequest) === 1) {
            return true;
        }

        if (preg_match('/\b(?:onder\s*\d{1,2}|o\d{1,2}|u\d{1,2}|jeugd|kind(?:eren)?|senior(?:en)?|beginner(?:s)?|gevorderd(?:en)?)\b/ui', $coachRequest) === 1) {
            return true;
        }

        return false;
    }

    private function hasPrimaryGoalContext(string $coachRequest, ?array $formState): bool
    {
        if (preg_match('/\b(?:passen|pass(?:ing)?|vrijlopen|scannen|afwerken|schieten|dribbelen|omschakel(?:en|ing)|drukzetten|opbouw|aanvallen|verdedigen)\b/ui', $coachRequest) === 1) {
            return true;
        }

        if (!empty($formState['team_task'])) {
            return true;
        }

        if (!empty($formState['objectives']) && is_array($formState['objectives'])) {
            return true;
        }

        return !empty($formState['actions']) && is_array($formState['actions']);
    }

    private function logSearchQualityEvents(int $userId, ?int $teamId, int $sessionId, array $videoChoices): void
    {
        foreach ($videoChoices as $index => $choice) {
            if (!is_array($choice)) {
                continue;
            }

            $videoId = trim((string)($choice['video_id'] ?? ''));
            if ($videoId === '') {
                continue;
            }

            $technicalViability = is_array($choice['technical_viability'] ?? null) ? $choice['technical_viability'] : [];
            $payload = [
                'phase' => 'search_results',
                'rank' => $index + 1,
                'recommended' => !empty($choice['is_recommended']),
                'selectable' => !empty($choice['is_selectable']),
                'technical_label' => trim((string)($choice['technical_label'] ?? ($technicalViability['label'] ?? ''))),
                'technical_summary' => trim((string)($choice['technical_summary'] ?? ($technicalViability['summary'] ?? ''))),
            ] + $this->buildTechnicalPreflightEventPayload(
                is_array($choice['technical_preflight'] ?? null) ? $choice['technical_preflight'] : [],
                $technicalViability
            );

            $status = !empty($choice['is_recommended'])
                ? 'recommended'
                : (!empty($choice['is_selectable']) ? 'selectable' : 'blocked');

            $this->safeLogQualityEvent(
                $userId,
                $teamId,
                $sessionId,
                'preflight_result',
                $status,
                $payload,
                $videoId
            );
        }
    }

    private function logVideoChoiceSelectionEvent(
        int $userId,
        ?int $teamId,
        int $sessionId,
        string $videoId,
        string $selectionOrigin,
        string $recoveryTriggerCode = '',
        int $selectedSegmentId = 0
    ): void {
        $videoId = trim($videoId);
        if ($videoId === '') {
            return;
        }

        $selectionOrigin = trim($selectionOrigin);
        if ($selectionOrigin === '') {
            return;
        }

        $eventType = $selectionOrigin === 'recovery'
            ? 'recovery_choice_selected'
            : 'video_choice_selected';

        $payload = [
            'selection_origin' => $selectionOrigin,
            'recovery_trigger_code' => $selectionOrigin === 'recovery' && trim($recoveryTriggerCode) !== ''
                ? trim($recoveryTriggerCode)
                : null,
            'selected_segment_id' => $selectedSegmentId > 0 ? $selectedSegmentId : null,
        ];

        $this->safeLogQualityEvent($userId, $teamId, $sessionId, $eventType, 'selected', $payload, $videoId);
    }

    private function logSelectedVideoPreflightEvent(
        int $userId,
        ?int $teamId,
        int $sessionId,
        string $videoId,
        array $source,
        array $technicalViability,
        int $selectedSegmentId = 0
    ): void {
        $payload = [
            'phase' => 'generation_selected',
            'selected_segment_id' => $selectedSegmentId > 0 ? $selectedSegmentId : null,
            'source_has_chapters' => is_array($source['chapters'] ?? null) && !empty($source['chapters']),
        ] + $this->buildTechnicalPreflightEventPayload(
            is_array($source['technical_preflight'] ?? null) ? $source['technical_preflight'] : [],
            $technicalViability
        );

        $status = ($technicalViability['is_selectable'] ?? false) ? 'selectable' : 'blocked';
        $this->safeLogQualityEvent($userId, $teamId, $sessionId, 'preflight_result', $status, $payload, $videoId);
    }

    private function logGenerationBlockerQualityEvents(
        int $userId,
        ?int $teamId,
        int $sessionId,
        string $eventType,
        string $videoId,
        array $payload,
        array $recoveryPayload,
        array $conceptRecovery = [],
        array $screenshotRecovery = []
    ): void {
        $videoId = trim($videoId);
        $this->safeLogQualityEvent(
            $userId,
            $teamId,
            $sessionId,
            $eventType,
            'blocked',
            $payload,
            $videoId !== '' ? $videoId : null
        );

        $choices = is_array($recoveryPayload['recovery_video_choices'] ?? null)
            ? $recoveryPayload['recovery_video_choices']
            : [];
        $recoveryVideoIds = [];
        foreach ($choices as $choice) {
            if (!is_array($choice)) {
                continue;
            }

            $choiceVideoId = trim((string)($choice['video_id'] ?? ''));
            if ($choiceVideoId === '') {
                continue;
            }

            $recoveryVideoIds[] = $choiceVideoId;
        }

        $conceptData = is_array($conceptRecovery['concept_recovery'] ?? null)
            ? $conceptRecovery['concept_recovery']
            : [];
        $conceptVideoId = trim((string)($conceptData['video_id'] ?? ''));
        $screenshotData = is_array($screenshotRecovery['screenshot_recovery'] ?? null)
            ? $screenshotRecovery['screenshot_recovery']
            : [];
        $screenshotVideoId = trim((string)($screenshotData['video_id'] ?? ''));
        $recoveryAvailable = !empty($recoveryVideoIds) || $conceptVideoId !== '' || $screenshotVideoId !== '';

        $this->safeLogQualityEvent(
            $userId,
            $teamId,
            $sessionId,
            'recovery_offered',
            $recoveryAvailable ? 'offered' : 'none',
            [
                'trigger_event' => $eventType,
                'recovery_available' => $recoveryAvailable,
                'recovery_count' => count($recoveryVideoIds),
                'recovery_video_ids' => $recoveryVideoIds,
                'concept_available' => $conceptVideoId !== '',
                'concept_video_id' => $conceptVideoId !== '' ? $conceptVideoId : null,
                'screenshot_available' => $screenshotVideoId !== '',
                'screenshot_video_id' => $screenshotVideoId !== '' ? $screenshotVideoId : null,
            ],
            $videoId !== '' ? $videoId : null
        );
    }

    private function buildTechnicalPreflightEventPayload(array $preflight, array $technicalViability = []): array
    {
        $attempts = $this->normalizeAvailabilityAttemptsForEvent(
            is_array($preflight['attempts'] ?? null) ? $preflight['attempts'] : []
        );
        $payload = [
            'downloadable_via_ytdlp' => $preflight['downloadable_via_ytdlp'] ?? ($technicalViability['downloadable_via_ytdlp'] ?? null),
            'auth_required' => array_key_exists('auth_required', $preflight)
                ? (bool)$preflight['auth_required']
                : (bool)($technicalViability['auth_required'] ?? false),
            'used_cookies' => array_key_exists('used_cookies', $preflight)
                ? (bool)$preflight['used_cookies']
                : (bool)($technicalViability['used_cookies'] ?? false),
            'status' => trim((string)($preflight['status'] ?? $technicalViability['status'] ?? $preflight['error_code'] ?? 'unknown')),
            'error_code' => trim((string)($preflight['error_code'] ?? $technicalViability['error_code'] ?? '')),
            'duration_seconds' => max(0, (int)($preflight['duration_seconds'] ?? $technicalViability['duration_seconds'] ?? 0)),
            'chapter_count' => max(0, (int)($preflight['chapter_count'] ?? $technicalViability['chapter_count'] ?? 0)),
            'transcript_source' => trim((string)($preflight['transcript_source'] ?? $technicalViability['transcript_source'] ?? 'none')),
            'metadata_only' => array_key_exists('metadata_only', $preflight)
                ? (bool)$preflight['metadata_only']
                : (bool)($technicalViability['metadata_only'] ?? false),
            'is_short_clip' => (bool)($technicalViability['is_short_clip'] ?? false),
            'preflight_inconclusive' => (bool)($technicalViability['preflight_inconclusive'] ?? false),
            'availability_attempts' => $attempts,
        ];

        $payload['status'] = $payload['status'] !== '' ? $payload['status'] : 'unknown';
        $payload['transcript_source'] = $payload['transcript_source'] !== '' ? $payload['transcript_source'] : 'none';
        $payload['availability_mode'] = $this->buildAvailabilityMode($payload);
        $payload += $this->summarizeAvailabilityAttempts($attempts);

        return $payload;
    }

    private function buildAvailabilityMode(array $payload): string
    {
        if (($payload['downloadable_via_ytdlp'] ?? null) === true) {
            return !empty($payload['used_cookies']) ? 'cookie_recovered' : 'anonymous_ok';
        }

        if (!empty($payload['auth_required'])) {
            return ($payload['status'] ?? '') === 'cookies_invalid' ? 'cookies_invalid' : 'auth_required';
        }

        $status = trim((string)($payload['status'] ?? ''));
        return $status !== '' ? $status : 'unknown';
    }

    private function normalizeAvailabilityAttemptsForEvent(array $attempts): array
    {
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

    private function summarizeAvailabilityAttempts(array $attempts): array
    {
        $summary = [
            'attempt_count' => count($attempts),
            'anonymous_attempted' => false,
            'anonymous_attempt_failed' => false,
            'cookie_attempted' => false,
            'cookie_attempt_success' => false,
            'cookies_invalid' => false,
            'last_attempt_stage' => '',
            'last_attempt_mode' => '',
            'last_attempt_error_code' => null,
        ];

        foreach ($attempts as $attempt) {
            if (!is_array($attempt)) {
                continue;
            }

            $mode = trim((string)($attempt['mode'] ?? ''));
            $stage = trim((string)($attempt['stage'] ?? ''));
            $attempted = !array_key_exists('attempted', $attempt) || (bool)$attempt['attempted'];
            $ok = !empty($attempt['ok']);
            $errorCode = $attempt['error_code'] ?? null;
            $errorCode = is_string($errorCode) && trim($errorCode) !== '' ? trim($errorCode) : null;

            if ($mode === 'anonymous') {
                $summary['anonymous_attempted'] = $summary['anonymous_attempted'] || $attempted;
                $summary['anonymous_attempt_failed'] = $summary['anonymous_attempt_failed'] || ($attempted && !$ok);
            }
            if ($mode === 'cookies') {
                $summary['cookie_attempted'] = $summary['cookie_attempted'] || $attempted;
                $summary['cookie_attempt_success'] = $summary['cookie_attempt_success'] || ($attempted && $ok);
                $summary['cookies_invalid'] = $summary['cookies_invalid'] || $errorCode === 'cookies_invalid';
            }

            $summary['last_attempt_stage'] = $stage;
            $summary['last_attempt_mode'] = $mode;
            $summary['last_attempt_error_code'] = $errorCode;
        }

        return $summary;
    }

    private function logFrameDownloadQualityEvent(
        int $userId,
        ?int $teamId,
        int $sessionId,
        string $videoId,
        array $frameResult,
        int $selectedSegmentId = 0
    ): void {
        $attempts = $this->normalizeAvailabilityAttemptsForEvent(
            is_array($frameResult['download_attempts'] ?? null) ? $frameResult['download_attempts'] : []
        );
        if (empty($attempts)) {
            return;
        }

        $summary = $this->summarizeAvailabilityAttempts($attempts);
        $status = 'failed';
        if (!empty($frameResult['ok'])) {
            $status = !empty($summary['cookie_attempt_success']) && !empty($summary['anonymous_attempt_failed'])
                ? 'cookie_recovered'
                : 'success';
        } elseif (!empty($summary['cookies_invalid'])) {
            $status = 'cookies_invalid';
        } elseif (!empty($summary['cookie_attempted'])) {
            $status = 'cookie_failed';
        }

        $payload = [
            'phase' => 'frame_extraction',
            'selected_segment_id' => $selectedSegmentId > 0 ? $selectedSegmentId : null,
            'frame_count' => is_array($frameResult['frames'] ?? null) ? count($frameResult['frames']) : 0,
            'duration_seconds' => max(0, (int)($frameResult['duration'] ?? 0)),
            'error' => trim((string)($frameResult['error'] ?? '')),
            'availability_attempts' => $attempts,
        ] + $summary;

        $this->safeLogQualityEvent($userId, $teamId, $sessionId, 'frame_download_result', $status, $payload, $videoId);
    }

    private function buildSourceEvidenceEventPayload(array $source, array $sourceEvidence): array
    {
        return [
            'phase' => 'generation_evidence_gate',
            'evidence_score' => (float)($sourceEvidence['score'] ?? 0.0),
            'evidence_level' => trim((string)($sourceEvidence['level'] ?? 'low')),
            'transcript_chars' => max(0, (int)($sourceEvidence['transcript_chars'] ?? 0)),
            'chapter_count' => max(0, (int)($sourceEvidence['chapter_count'] ?? 0)),
            'visual_status' => trim((string)($sourceEvidence['visual_status'] ?? ($source['visual_status'] ?? 'unknown'))),
            'visual_confidence' => trim((string)($sourceEvidence['visual_confidence'] ?? ($source['visual_confidence'] ?? 'none'))),
            'blocking_reasons' => is_array($sourceEvidence['blocking_reasons'] ?? null) ? $sourceEvidence['blocking_reasons'] : [],
        ] + $this->buildTechnicalPreflightEventPayload(
            is_array($source['technical_preflight'] ?? null) ? $source['technical_preflight'] : []
        );
    }

    private function buildSourceFactsFailureEventPayload(array $source, array $sourceEvidence, array $sourceFactsResult): array
    {
        return [
            'phase' => 'generation_source_facts',
            'evidence_score' => (float)($sourceEvidence['score'] ?? 0.0),
            'evidence_level' => trim((string)($sourceEvidence['level'] ?? 'low')),
            'source_facts_code' => trim((string)($sourceFactsResult['code'] ?? 'source_facts_failed')),
            'source_facts_error' => trim((string)($sourceFactsResult['error'] ?? '')),
            'blocking_reasons' => is_array($sourceEvidence['blocking_reasons'] ?? null) ? $sourceEvidence['blocking_reasons'] : [],
        ] + $this->buildTechnicalPreflightEventPayload(
            is_array($source['technical_preflight'] ?? null) ? $source['technical_preflight'] : []
        );
    }

    private function safeLogQualityEvent(
        ?int $userId,
        ?int $teamId,
        ?int $sessionId,
        string $eventType,
        string $status,
        array $payload = [],
        ?string $externalId = null
    ): void {
        try {
            $this->qualityEventService->logEvent($userId, $teamId, $sessionId, $eventType, $status, $payload, $externalId);
        } catch (Throwable $e) {
            error_log(sprintf(
                '[AI] quality event logging failed (%s/%s): %s',
                $eventType,
                $status,
                $e->getMessage()
            ));
        }
    }

    private function generateConceptExercise(
        int $sessionId,
        int $userId,
        ?int $teamId,
        int $exerciseId,
        array $model,
        array $settings,
        string $fieldTypeHint,
        ?array $formState,
        array $source,
        array $sourcesUsed,
        array $retrievalWarnings,
        string $coachRequest
    ): void {
        $usageEventId = 0;

        try {
            if (!$this->canOfferConceptRecovery($source)) {
                $this->jsonResponse([
                    'ok' => false,
                    'session_id' => $sessionId,
                    'error' => 'Ik kan van deze video geen eerste opzet maken.',
                    'code' => 'concept_mode_unavailable',
                ], 422);
            }

            $sourceEvidence = $this->workflowService->assessSourceEvidence($source, $settings);
            $this->persistSourceEvidenceToCache($source, $sourceEvidence);
            $sourceReview = $this->buildSourceReviewPayload($sourceEvidence);
            $sourceContext = [$source];
            unset($sourceContext[0]['visual_frames'], $sourceContext[0]['visual_usage']);

            $usageEventId = $this->usageService->createEvent(
                $userId,
                $teamId,
                $sessionId,
                $exerciseId > 0 ? $exerciseId : null,
                (string)$model['model_id'],
                'in_progress',
                null
            );

            $usageTotals = [
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
                'supplier_cost_usd' => 0.0,
                'generation_id' => null,
            ];

            $response = $this->runModelCall(
                [
                    [
                        'role' => 'system',
                        'content' => $this->promptBuilder->buildSystemPrompt(
                            $fieldTypeHint,
                            $formState,
                            $sourceContext,
                            $exerciseId > 0
                        ),
                    ],
                    [
                        'role' => 'user',
                        'content' => $this->promptBuilder->buildConceptGenerationInstruction(
                            $source,
                            $sourceEvidence,
                            $coachRequest,
                            $formState,
                            $exerciseId > 0
                        ),
                    ],
                ],
                (string)$model['model_id'],
                $userId,
                $usageTotals
            );

            if (!($response['ok'] ?? false)) {
                $pricing = $this->buildUsageUpdatePayload($usageTotals, $model, $settings, 'failed', 'concept_generation_failed');
                $this->usageService->updateEvent($usageEventId, $pricing);
                $this->safeLogQualityEvent(
                    $userId,
                    $teamId,
                    $sessionId,
                    'concept_mode_result',
                    'failed',
                    [
                        'error_code' => 'concept_generation_failed',
                        'source_evidence_level' => trim((string)($sourceEvidence['level'] ?? 'low')),
                    ],
                    trim((string)($source['external_id'] ?? '')) !== '' ? trim((string)($source['external_id'] ?? '')) : null
                );

                if (isset($response['provider_response']) && is_array($response['provider_response'])) {
                    $providerFailure = $this->buildProviderFailurePayload($response['provider_response']);
                    $this->jsonResponse($providerFailure['payload'], $providerFailure['status']);
                }

                $providerFailure = $this->buildProviderFailurePayload($response);
                $this->jsonResponse($providerFailure['payload'], $providerFailure['status']);
            }

            $output = $this->parseAndValidateOutput(
                (string)($response['content'] ?? ''),
                $fieldTypeHint,
                $formState,
                $sourceContext,
                $coachRequest
            );

            if ($output['text_suggestion'] === null && $output['drawing_suggestion'] === null) {
                $pricing = $this->buildUsageUpdatePayload($usageTotals, $model, $settings, 'failed', 'concept_output_empty');
                $this->usageService->updateEvent($usageEventId, $pricing);
                $this->safeLogQualityEvent(
                    $userId,
                    $teamId,
                    $sessionId,
                    'concept_mode_result',
                    'failed',
                    [
                        'error_code' => 'concept_output_empty',
                        'source_evidence_level' => trim((string)($sourceEvidence['level'] ?? 'low')),
                    ],
                    trim((string)($source['external_id'] ?? '')) !== '' ? trim((string)($source['external_id'] ?? '')) : null
                );

                $this->jsonResponse([
                    'ok' => false,
                    'session_id' => $sessionId,
                    'error' => 'De eerste opzet lukte nog niet.',
                    'code' => 'concept_output_empty',
                ], 422);
            }

            if (is_array($output['text_suggestion'] ?? null)) {
                $fields = is_array($output['text_suggestion']['fields'] ?? null) ? $output['text_suggestion']['fields'] : [];
                $conceptNotice = 'Let op: dit is een eerste opzet. Check aantallen, opstelling en wissels.';
                $existingCoachInstructions = trim((string)($fields['coach_instructions'] ?? ''));
                $fields['coach_instructions'] = $existingCoachInstructions !== ''
                    ? $conceptNotice . "\n\n" . $existingCoachInstructions
                    : $conceptNotice;
                if (trim((string)($fields['source'] ?? '')) === '') {
                    $fields['source'] = trim((string)($source['url'] ?? ''));
                }
                $output['text_suggestion']['fields'] = $fields;
            }

            $conceptWarnings = [
                'Dit is een eerste opzet. Check aantallen, opstelling en wissels zelf nog even.',
            ];
            if (($sourceEvidence['transcript_source'] ?? 'none') === 'metadata_fallback') {
                $conceptWarnings[] = 'Ik baseer deze eerste opzet vooral op titel en beschrijving van de video.';
            }
            if (!empty($retrievalWarnings)) {
                $conceptWarnings = array_merge($conceptWarnings, $retrievalWarnings);
            }
            if (!empty($output['warnings'])) {
                $conceptWarnings = array_merge($conceptWarnings, $output['warnings']);
            }
            $output['warnings'] = array_values(array_unique(array_filter(array_map(
                static fn(mixed $warning): string => trim((string)$warning),
                $conceptWarnings
            ))));

            $pricing = $this->buildUsageUpdatePayload($usageTotals, $model, $settings, 'success', null);
            $assistantText = trim((string)($output['chat_text'] ?? ''));
            if ($assistantText === '') {
                $assistantText = 'Ik heb een eerste opzet voor je gemaakt.';
            }

            $suggestionsPayload = [];
            if ($output['text_suggestion'] !== null) {
                $suggestionsPayload['text'] = $output['text_suggestion'];
            }
            if ($output['drawing_suggestion'] !== null) {
                $suggestionsPayload['drawing'] = $output['drawing_suggestion'];
            }
            if (!empty($output['warnings'])) {
                $suggestionsPayload['warnings'] = $output['warnings'];
            }

            $metadata = [
                'phase' => 'exercise_generated',
                'sources_used' => $sourcesUsed,
                'source_evidence' => $sourceEvidence,
                'source_review' => $sourceReview,
                'concept_mode' => [
                    'active' => true,
                    'status' => 'generated',
                ],
            ];
            if (!empty($suggestionsPayload)) {
                $metadata['suggestions'] = $suggestionsPayload;
            }

            $this->pdo->beginTransaction();
            try {
                $this->usageService->updateEvent($usageEventId, $pricing);
                $this->sessionService->insertChatMessage(
                    $sessionId,
                    'assistant',
                    $assistantText,
                    (string)$model['model_id'],
                    $this->encodeJson($metadata, '{}')
                );
                $this->pdo->commit();
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $e;
            }

            $this->safeLogQualityEvent(
                $userId,
                $teamId,
                $sessionId,
                'concept_mode_result',
                'success',
                [
                    'source_evidence_level' => trim((string)($sourceEvidence['level'] ?? 'low')),
                    'source_evidence_score' => (float)($sourceEvidence['score'] ?? 0.0),
                    'has_text_suggestion' => $output['text_suggestion'] !== null,
                    'has_drawing_suggestion' => $output['drawing_suggestion'] !== null,
                ],
                trim((string)($source['external_id'] ?? '')) !== '' ? trim((string)($source['external_id'] ?? '')) : null
            );

            $responsePayload = [
                'ok' => true,
                'session_id' => $sessionId,
                'phase' => 'exercise_generated',
                'model_id' => (string)$model['model_id'],
                'message' => [
                    'role' => 'assistant',
                    'content' => $assistantText,
                    'created_at' => date('Y-m-d H:i:s'),
                ],
                'sources_used' => $sourcesUsed,
                'usage' => [
                    'input_tokens' => (int)$pricing['input_tokens'],
                    'output_tokens' => (int)$pricing['output_tokens'],
                    'total_tokens' => (int)$pricing['total_tokens'],
                    'supplier_cost_usd' => (float)$pricing['supplier_cost_usd'],
                    'billable_cost_eur' => (float)$pricing['billable_cost_eur'],
                ],
                'source_evidence' => $sourceEvidence,
                'source_review' => $sourceReview,
                'concept_mode' => [
                    'active' => true,
                    'status' => 'generated',
                ],
                'summary' => $this->usageService->buildSummary($userId, $this->accessService->getSettingsWithDefaults()),
            ];
            if (!empty($suggestionsPayload)) {
                $responsePayload['suggestions'] = $suggestionsPayload;
            }

            $this->jsonResponse($responsePayload);
        } catch (Throwable $e) {
            if ($usageEventId > 0) {
                try {
                    $this->usageService->failEvent($usageEventId, 'internal_error');
                } catch (Throwable) {
                    // Avoid masking the original exception.
                }
            }
            throw $e;
        }
    }

    private function generateExercise(
        int $sessionId,
        int $userId,
        ?int $teamId,
        int $exerciseId,
        array $model,
        array $settings,
        string $fieldTypeHint,
        ?array $formState,
        array $source,
        array $sourcesUsed,
        array $retrievalWarnings,
        string $latestUserMessage
    ): void {
        $usageEventId = 0;

        try {
            $qualityGateMode = $this->resolveQualityGateMode($settings);
            $qualityWarnings = [];
            $sourceContext = [$source];
            // Strip heavy binary data from source context to avoid bloating the system prompt
            unset($sourceContext[0]['visual_frames'], $sourceContext[0]['visual_usage']);
            $sourceEvidence = $this->workflowService->assessSourceEvidence($source, $settings);
            $this->persistSourceEvidenceToCache($source, $sourceEvidence);
            $sourceReview = $this->buildSourceReviewPayload($sourceEvidence);
            $isExistingExercise = $exerciseId > 0;

            if (!($sourceEvidence['is_sufficient'] ?? false)) {
                if ($qualityGateMode !== 'hard') {
                    $qualityWarnings[] = $this->workflowService->buildSourceEvidenceWarning($sourceEvidence);
                } else {
                    $assistantText = $this->workflowService->buildSourceEvidenceWarning($sourceEvidence);
                    $recoveryPayload = $this->buildRecoveryPayload($sessionId, (string)($source['external_id'] ?? ''));
                    $conceptRecovery = $this->buildConceptRecoveryPayload($source, 'source_evidence_too_low');
                    $selectedSegmentId = max(0, (int)($source['segment']['id'] ?? 0));
                    $screenshotRecovery = $this->buildScreenshotRecoveryPayload($source, $settings, 'source_evidence_too_low');
                    $assistantText = $this->augmentFailureMessageWithRecovery($assistantText, $recoveryPayload, $conceptRecovery, $screenshotRecovery);
                    $this->logGenerationBlockerQualityEvents(
                        $userId,
                        $teamId,
                        $sessionId,
                        'source_evidence_too_low',
                        (string)($source['external_id'] ?? ''),
                        $this->buildSourceEvidenceEventPayload($source, $sourceEvidence),
                        $recoveryPayload,
                        $conceptRecovery,
                        $screenshotRecovery
                    );
                    $metadata = [
                        'sources_used' => $sourcesUsed,
                        'source_evidence' => $sourceEvidence,
                        'source_review' => $sourceReview,
                    ];
                    if (!empty($recoveryPayload['recovery_video_choices'])) {
                        $metadata['recovery_video_choices'] = $recoveryPayload['recovery_video_choices'];
                    }
                    if (!empty($conceptRecovery['concept_recovery'])) {
                        $metadata['concept_recovery'] = $conceptRecovery['concept_recovery'];
                    }
                    if (!empty($screenshotRecovery['screenshot_recovery'])) {
                        $metadata['screenshot_recovery'] = $screenshotRecovery['screenshot_recovery'];
                        $metadata['screenshot_context'] = $this->buildScreenshotRecoveryContext(
                            $source,
                            $sourcesUsed,
                            $retrievalWarnings,
                            $latestUserMessage,
                            $selectedSegmentId,
                            'source_evidence_too_low'
                        );
                    }
                    $this->sessionService->insertChatMessage(
                        $sessionId,
                        'assistant',
                        $assistantText,
                        (string)$model['model_id'],
                        $this->encodeJson($metadata, '{}')
                    );

                    $this->jsonResponse([
                        'ok' => false,
                        'session_id' => $sessionId,
                        'error' => $assistantText,
                        'code' => 'source_evidence_too_low',
                        'sources_used' => $sourcesUsed,
                        'source_evidence' => $sourceEvidence,
                        'source_review' => $sourceReview,
                    ] + $recoveryPayload + $conceptRecovery + $screenshotRecovery, 422);
                }
            }

            $usageEventId = $this->usageService->createEvent(
                $userId, $teamId, $sessionId,
                $exerciseId > 0 ? $exerciseId : null,
                (string)$model['model_id'], 'in_progress', null
            );

            $usageTotals = [
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
                'supplier_cost_usd' => 0.0,
                'generation_id' => null,
            ];

            // Include vision analysis usage if available
            $visionUsage = $source['visual_usage'] ?? [];
            if (!empty($visionUsage)) {
                $usageTotals['prompt_tokens'] += max(0, (int)($visionUsage['prompt_tokens'] ?? 0));
                $usageTotals['completion_tokens'] += max(0, (int)($visionUsage['completion_tokens'] ?? 0));
                $usageTotals['total_tokens'] += max(0, (int)($visionUsage['total_tokens'] ?? 0));
                $usageTotals['supplier_cost_usd'] = round(
                    (float)$usageTotals['supplier_cost_usd'] + max(0.0, (float)($visionUsage['supplier_cost_usd'] ?? 0.0)),
                    10
                );
            }

            $sourceFactsResult = $this->extractSourceFacts(
                $source,
                $sourceEvidence,
                $latestUserMessage,
                (string)$model['model_id'],
                $userId,
                $usageTotals
            );

            $sourceFacts = is_array($sourceFactsResult['source_facts'] ?? null)
                ? $sourceFactsResult['source_facts']
                : null;

            if (!($sourceFactsResult['ok'] ?? false)) {
                if (isset($sourceFactsResult['provider_response']) && is_array($sourceFactsResult['provider_response'])) {
                    $pricing = $this->buildUsageUpdatePayload($usageTotals, $model, $settings, 'failed', $sourceFactsResult['code'] ?? 'source_facts_failed');
                    $this->usageService->updateEvent($usageEventId, $pricing);
                    $providerFailure = $this->buildProviderFailurePayload($sourceFactsResult['provider_response']);
                    $this->jsonResponse($providerFailure['payload'], $providerFailure['status']);
                }

                if ($qualityGateMode === 'hard' || $sourceFacts === null) {
                    $pricing = $this->buildUsageUpdatePayload($usageTotals, $model, $settings, 'failed', $sourceFactsResult['code'] ?? 'source_facts_failed');
                    $this->usageService->updateEvent($usageEventId, $pricing);

                    $assistantText = $sourceFactsResult['error'] ?? 'Ik zie nog niet duidelijk genoeg hoe de oefening loopt.';
                    $recoveryPayload = $this->buildRecoveryPayload($sessionId, (string)($source['external_id'] ?? ''));
                    $conceptRecovery = $this->buildConceptRecoveryPayload($source, 'source_facts_failed');
                    $selectedSegmentId = max(0, (int)($source['segment']['id'] ?? 0));
                    $screenshotRecovery = $this->buildScreenshotRecoveryPayload($source, $settings, 'source_facts_failed');
                    $assistantText = $this->augmentFailureMessageWithRecovery($assistantText, $recoveryPayload, $conceptRecovery, $screenshotRecovery);
                    $this->logGenerationBlockerQualityEvents(
                        $userId,
                        $teamId,
                        $sessionId,
                        'source_facts_failed',
                        (string)($source['external_id'] ?? ''),
                        $this->buildSourceFactsFailureEventPayload($source, $sourceEvidence, $sourceFactsResult),
                        $recoveryPayload,
                        $conceptRecovery,
                        $screenshotRecovery
                    );
                    $metadata = [
                        'sources_used' => $sourcesUsed,
                        'source_evidence' => $sourceEvidence,
                        'source_review' => $sourceReview,
                    ];
                    if (!empty($recoveryPayload['recovery_video_choices'])) {
                        $metadata['recovery_video_choices'] = $recoveryPayload['recovery_video_choices'];
                    }
                    if (!empty($conceptRecovery['concept_recovery'])) {
                        $metadata['concept_recovery'] = $conceptRecovery['concept_recovery'];
                    }
                    if (!empty($screenshotRecovery['screenshot_recovery'])) {
                        $metadata['screenshot_recovery'] = $screenshotRecovery['screenshot_recovery'];
                        $metadata['screenshot_context'] = $this->buildScreenshotRecoveryContext(
                            $source,
                            $sourcesUsed,
                            $retrievalWarnings,
                            $latestUserMessage,
                            $selectedSegmentId,
                            'source_facts_failed'
                        );
                    }
                    $this->sessionService->insertChatMessage(
                        $sessionId,
                        'assistant',
                        $assistantText,
                        (string)$model['model_id'],
                        $this->encodeJson($metadata, '{}')
                    );

                    $this->jsonResponse([
                        'ok' => false,
                        'session_id' => $sessionId,
                        'error' => $assistantText,
                        'code' => $sourceFactsResult['code'] ?? 'source_facts_failed',
                        'sources_used' => $sourcesUsed,
                        'source_evidence' => $sourceEvidence,
                        'source_review' => $sourceReview,
                    ] + $recoveryPayload + $conceptRecovery + $screenshotRecovery, 422);
                }

                $qualityWarnings[] = $sourceFactsResult['error'] ?? 'Ik zie nog niet duidelijk genoeg hoe de oefening loopt.';
            }

            // Fuse textual source facts with visual facts (if available)
            $visualFacts = $source['visual_facts'] ?? null;
            $sourceFacts = $this->fusionService->fuse($sourceFacts, $visualFacts);
            $sourceReview = $this->buildSourceReviewPayload($sourceEvidence, $sourceFacts);

            $firstCandidate = $this->runVideoTranslationCandidate(
                $source,
                $sourceFacts,
                $sourceEvidence,
                $fieldTypeHint,
                $formState,
                $latestUserMessage,
                $isExistingExercise,
                (string)$model['model_id'],
                $userId,
                $sourceContext,
                $usageTotals
            );
            if (!($firstCandidate['ok'] ?? false)) {
                $pricing = $this->buildUsageUpdatePayload($usageTotals, $model, $settings, 'failed', $firstCandidate['code'] ?? 'generation_failed');
                $this->usageService->updateEvent($usageEventId, $pricing);

                if (isset($firstCandidate['provider_response']) && is_array($firstCandidate['provider_response'])) {
                    $providerFailure = $this->buildProviderFailurePayload($firstCandidate['provider_response']);
                    $this->jsonResponse($providerFailure['payload'], $providerFailure['status']);
                }

                $this->jsonResponse([
                    'ok' => false,
                    'error' => $firstCandidate['error'] ?? 'Ik kon nog geen goede oefening maken.',
                    'code' => $firstCandidate['code'] ?? 'generation_failed',
                ], 422);
            }

            $bestCandidate = $firstCandidate;
            $maxRewrites = max(0, min(2, (int)($settings['ai_quality_max_rewrites'] ?? 1)));

            if (($firstCandidate['evaluation']['verdict'] ?? 'fail') !== 'pass' && $maxRewrites > 0) {
                $rewrittenCandidate = $this->runVideoTranslationCandidate(
                    $source,
                    $sourceFacts,
                    $sourceEvidence,
                    $fieldTypeHint,
                    $formState,
                    $latestUserMessage,
                    $isExistingExercise,
                    (string)$model['model_id'],
                    $userId,
                    $sourceContext,
                    $usageTotals,
                    $firstCandidate['evaluation'],
                    $firstCandidate['output']['text_suggestion'] ?? null,
                    $firstCandidate['output']['drawing_suggestion'] ?? null
                );

                if (($rewrittenCandidate['ok'] ?? false) && $this->isCandidateBetter($rewrittenCandidate, $bestCandidate)) {
                    $bestCandidate = $rewrittenCandidate;
                }
            }

            $pricing = $this->buildUsageUpdatePayload($usageTotals, $model, $settings, 'success', null);
            $qualityPasses = $this->passesConfiguredQualityGate($bestCandidate['evaluation'], $settings);

            if (!$qualityPasses && $qualityGateMode === 'hard') {
                $assistantText = $this->buildAlignmentFailureMessage($bestCandidate['evaluation'], $sourceEvidence);
                $metadata = [
                    'sources_used' => $sourcesUsed,
                    'source_evidence' => $sourceEvidence,
                    'source_review' => $sourceReview,
                    'quality' => $bestCandidate['evaluation'],
                ];

                $this->pdo->beginTransaction();
                try {
                    $failedUsage = $pricing;
                    $failedUsage['status'] = 'failed';
                    $failedUsage['error_code'] = 'alignment_failed';
                    $this->usageService->updateEvent($usageEventId, $failedUsage);
                    $this->sessionService->insertChatMessage(
                        $sessionId,
                        'assistant',
                        $assistantText,
                        (string)$model['model_id'],
                        $this->encodeJson($metadata, '{}')
                    );
                    $this->pdo->commit();
                } catch (Throwable $e) {
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    throw $e;
                }

                $this->jsonResponse([
                    'ok' => false,
                    'error' => $assistantText,
                    'code' => 'alignment_failed',
                    'sources_used' => $sourcesUsed,
                    'source_evidence' => $sourceEvidence,
                    'source_review' => $sourceReview,
                    'quality' => $bestCandidate['evaluation'],
                ], 422);
            }

            $output = $bestCandidate['output'];

            if (!$qualityPasses) {
                $qualityWarnings[] = $this->buildAlignmentFailureMessage($bestCandidate['evaluation'], $sourceEvidence);
            }
            if (!$qualityPasses && $this->shouldDropDrawingSuggestion($bestCandidate['evaluation'])) {
                $fallbackDrawing = $this->buildFallbackDrawingSuggestion($output['text_suggestion'], $fieldTypeHint);
                if ($fallbackDrawing !== null) {
                    $output['drawing_suggestion'] = $fallbackDrawing;
                } else {
                    $output['drawing_suggestion'] = null;
                }
            }
            if ($output['drawing_suggestion'] === null) {
                $fallbackDrawing = $this->buildFallbackDrawingSuggestion($output['text_suggestion'], $fieldTypeHint);
                if ($fallbackDrawing !== null) {
                    $output['drawing_suggestion'] = $fallbackDrawing;
                }
            }
            if ($this->shouldSimplifyDrawingForLowEvidence(
                $sourceEvidence,
                is_array($output['drawing_suggestion'] ?? null) ? $output['drawing_suggestion'] : null
            )) {
                $fallbackDrawing = $this->buildFallbackDrawingSuggestion($output['text_suggestion'], $fieldTypeHint);
                if ($fallbackDrawing !== null) {
                    $output['drawing_suggestion'] = $fallbackDrawing;
                    $qualityWarnings[] = 'Ik heb de tekening vereenvoudigd omdat deze bron te weinig houvast geeft voor een gedetailleerde opstelling.';
                }
            }

            $assistantText = $output['chat_text'];
            if ($assistantText === '') {
                $assistantText = $this->promptBuilder->buildSuggestionSummary($output['text_suggestion'], $output['drawing_suggestion']);
            }
            if ($assistantText === '') {
                $assistantText = 'Ik heb een oefening voor je gemaakt op basis van deze video.';
            }

            $suggestionsPayload = [];
            if ($output['text_suggestion'] !== null) {
                $suggestionsPayload['text'] = $output['text_suggestion'];
            }
            if ($output['drawing_suggestion'] !== null) {
                $suggestionsPayload['drawing'] = $output['drawing_suggestion'];
            }
            if (!empty($output['warnings'])) {
                $suggestionsPayload['warnings'] = array_merge($retrievalWarnings, $output['warnings']);
            } elseif (!empty($retrievalWarnings)) {
                $suggestionsPayload['warnings'] = $retrievalWarnings;
            }

            $sourceFactWarnings = is_array($sourceFacts['missing_details'] ?? null) ? array_values(array_filter(array_map(
                static fn(mixed $item): string => trim((string)$item),
                $sourceFacts['missing_details']
            ))) : [];
            if (!empty($sourceFactWarnings)) {
                $suggestionsPayload['warnings'] = array_merge(
                    $suggestionsPayload['warnings'] ?? [],
                    ['Niet alle details in de video waren duidelijk. Daarom heb ik dit bewust simpel gehouden: ' . implode('; ', array_slice($sourceFactWarnings, 0, 3)) . '.']
                );
            }
            if (!empty($qualityWarnings)) {
                $suggestionsPayload['warnings'] = array_merge(
                    $suggestionsPayload['warnings'] ?? [],
                    array_values(array_filter(array_map(
                        static fn(mixed $warning): string => trim((string)$warning),
                        $qualityWarnings
                    )))
                );
            }

            if (!empty($suggestionsPayload['warnings'])) {
                $suggestionsPayload['warnings'] = array_values(array_unique($suggestionsPayload['warnings']));
            }

            $metadata = [
                'sources_used' => $sourcesUsed,
                'source_evidence' => $sourceEvidence,
                'source_review' => $sourceReview,
                'quality' => $bestCandidate['evaluation'],
            ];
            if (!empty($suggestionsPayload)) {
                $metadata['suggestions'] = $suggestionsPayload;
            }

            $this->pdo->beginTransaction();
            try {
                $this->usageService->updateEvent($usageEventId, $pricing);
                $this->sessionService->insertChatMessage(
                    $sessionId, 'assistant', $assistantText,
                    (string)$model['model_id'],
                    $this->encodeJson($metadata, '{}')
                );
                $this->pdo->commit();
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $e;
            }

            $responsePayload = [
                'ok' => true,
                'session_id' => $sessionId,
                'phase' => 'exercise_generated',
                'model_id' => (string)$model['model_id'],
                'message' => [
                    'role' => 'assistant',
                    'content' => $assistantText,
                    'created_at' => date('Y-m-d H:i:s'),
                ],
                'sources_used' => $sourcesUsed,
                'usage' => [
                    'input_tokens' => (int)$pricing['input_tokens'],
                    'output_tokens' => (int)$pricing['output_tokens'],
                    'total_tokens' => (int)$pricing['total_tokens'],
                    'supplier_cost_usd' => (float)$pricing['supplier_cost_usd'],
                    'billable_cost_eur' => (float)$pricing['billable_cost_eur'],
                ],
                'quality_gate_mode' => $qualityGateMode,
                'source_evidence' => $sourceEvidence,
                'source_review' => $sourceReview,
                'quality' => $bestCandidate['evaluation'],
                'summary' => $this->usageService->buildSummary($userId, $this->accessService->getSettingsWithDefaults()),
            ];
            if (!empty($suggestionsPayload)) {
                $responsePayload['suggestions'] = $suggestionsPayload;
            }

            $this->jsonResponse($responsePayload);
        } catch (Throwable $e) {
            if ($usageEventId > 0) {
                try {
                    $this->usageService->failEvent($usageEventId, 'internal_error');
                } catch (Throwable) {
                    // Avoid masking the original exception.
                }
            }
            throw $e;
        }
    }

    private function handleRefinementPhase(
        int $sessionId,
        int $userId,
        ?int $teamId,
        int $exerciseId,
        array $model,
        array $settings,
        string $fieldTypeHint,
        ?array $formState,
        string $message,
        string $mode,
        array $screenshotFrames
    ): void {
        if (!$this->promptBuilder->hasMeaningfulFormState($formState)) {
            $this->jsonResponse([
                'ok' => false,
                'session_id' => $sessionId,
                'error' => 'Er is nog geen oefening om aan te passen. Kies eerst een video en maak een eerste opzet.',
                'code' => 'refine_without_existing_exercise',
            ], 422);
        }

        $usageEventId = 0;

        try {
            $usageEventId = $this->usageService->createEvent(
                $userId,
                $teamId,
                $sessionId,
                $exerciseId > 0 ? $exerciseId : null,
                (string)$model['model_id'],
                'in_progress',
                null
            );

            $usageTotals = [
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
                'supplier_cost_usd' => 0.0,
                'generation_id' => null,
            ];

            $systemPrompt = $this->promptBuilder->buildSystemPrompt(
                $fieldTypeHint,
                $formState,
                [],
                true
            );
            $instruction = $mode === 'refine_drawing'
                ? $this->promptBuilder->buildDrawingRefinementInstruction($message, $formState)
                : $this->promptBuilder->buildTextRefinementInstruction($message, $formState);

            $response = $this->runModelCall(
                [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $instruction],
                ],
                (string)$model['model_id'],
                $userId,
                $usageTotals
            );
            if (!($response['ok'] ?? false)) {
                $pricing = $this->buildUsageUpdatePayload($usageTotals, $model, $settings, 'failed', 'refine_provider_error');
                $this->usageService->updateEvent($usageEventId, $pricing);

                if (isset($response['provider_response']) && is_array($response['provider_response'])) {
                    $providerFailure = $this->buildProviderFailurePayload($response['provider_response']);
                    $this->jsonResponse($providerFailure['payload'], $providerFailure['status']);
                }
                $providerFailure = $this->buildProviderFailurePayload($response);
                $this->jsonResponse($providerFailure['payload'], $providerFailure['status']);
            }

            $output = $this->parseAndValidateOutput(
                (string)($response['content'] ?? ''),
                $fieldTypeHint,
                $formState,
                [],
                $message
            );

            $warnings = is_array($output['warnings'] ?? null) ? $output['warnings'] : [];
            if ($mode === 'refine_text') {
                $output['drawing_suggestion'] = null;
                if (!is_array($output['text_suggestion'] ?? null)) {
                    $warnings[] = 'Ik kon nog geen concrete tekstwijziging uit je verzoek halen.';
                }
            } else {
                $output['text_suggestion'] = null;
                if (!$this->hasUsableDrawingSuggestion(is_array($output['drawing_suggestion'] ?? null) ? $output['drawing_suggestion'] : null)) {
                    $output['drawing_suggestion'] = null;
                    $warnings[] = 'Ik heb de bestaande tekening behouden omdat de AI-tekening niet bruikbaar was.';
                }
            }

            $normalizedWarnings = [];
            foreach ($warnings as $warning) {
                $value = trim((string)$warning);
                if ($value !== '') {
                    $normalizedWarnings[$value] = true;
                }
            }
            $warnings = array_keys($normalizedWarnings);

            $assistantText = trim((string)($output['chat_text'] ?? ''));
            if ($assistantText === '') {
                if ($mode === 'refine_drawing') {
                    $assistantText = $output['drawing_suggestion'] !== null
                        ? 'Ik heb de tekening bijgewerkt.'
                        : 'Ik heb geen bruikbare tekeningwijziging gevonden.';
                } else {
                    $assistantText = $output['text_suggestion'] !== null
                        ? 'Ik heb de tekst bijgewerkt.'
                        : 'Ik heb nog geen concrete tekstwijziging kunnen doorvoeren.';
                }
            }

            $suggestionsPayload = [];
            if ($output['text_suggestion'] !== null) {
                $suggestionsPayload['text'] = $output['text_suggestion'];
            }
            if ($output['drawing_suggestion'] !== null) {
                $suggestionsPayload['drawing'] = $output['drawing_suggestion'];
            }
            if (!empty($warnings)) {
                $suggestionsPayload['warnings'] = $warnings;
            }

            $pricing = $this->buildUsageUpdatePayload($usageTotals, $model, $settings, 'success', null);
            $metadata = [
                'phase' => 'exercise_generated',
                'refinement_mode' => $mode,
            ];
            if (!empty($suggestionsPayload)) {
                $metadata['suggestions'] = $suggestionsPayload;
            }

            $this->pdo->beginTransaction();
            try {
                $this->usageService->updateEvent($usageEventId, $pricing);
                $this->sessionService->insertChatMessage(
                    $sessionId,
                    'assistant',
                    $assistantText,
                    (string)$model['model_id'],
                    $this->encodeJson($metadata, '{}')
                );
                $this->pdo->commit();
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $e;
            }

            $responsePayload = [
                'ok' => true,
                'session_id' => $sessionId,
                'phase' => 'exercise_generated',
                'model_id' => (string)$model['model_id'],
                'message' => [
                    'role' => 'assistant',
                    'content' => $assistantText,
                    'created_at' => date('Y-m-d H:i:s'),
                ],
                'usage' => [
                    'input_tokens' => (int)$pricing['input_tokens'],
                    'output_tokens' => (int)$pricing['output_tokens'],
                    'total_tokens' => (int)$pricing['total_tokens'],
                    'supplier_cost_usd' => (float)$pricing['supplier_cost_usd'],
                    'billable_cost_eur' => (float)$pricing['billable_cost_eur'],
                ],
                'summary' => $this->usageService->buildSummary($userId, $this->accessService->getSettingsWithDefaults()),
            ];
            if (!empty($suggestionsPayload)) {
                $responsePayload['suggestions'] = $suggestionsPayload;
            }

            $this->jsonResponse($responsePayload);
        } catch (Throwable $e) {
            if ($usageEventId > 0) {
                try {
                    $this->usageService->failEvent($usageEventId, 'internal_error');
                } catch (Throwable) {
                    // Avoid masking the original exception.
                }
            }
            throw $e;
        }
    }

    private function extractSourceFacts(
        array $source,
        array $sourceEvidence,
        string $coachRequest,
        string $modelId,
        int $userId,
        array &$usageTotals
    ): array {
        $response = $this->runModelCall(
            [['role' => 'user', 'content' => $this->promptBuilder->buildSourceFactsPrompt($source, $sourceEvidence, $coachRequest)]],
            $modelId,
            $userId,
            $usageTotals
        );
        if (!($response['ok'] ?? false)) {
            return [
                'ok' => false,
                'code' => 'source_facts_provider_error',
                'provider_response' => $response,
            ];
        }

        $decoded = $this->parseJsonObjectResponse((string)($response['content'] ?? ''));
        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'code' => 'source_facts_parse_failed',
                'error' => 'Ik zie nog niet duidelijk genoeg hoe de oefening loopt.',
            ];
        }

        $sourceFacts = $this->normalizeSourceFacts($decoded);
        $recognitionPoints = is_array($sourceFacts['recognition_points'] ?? null) ? $sourceFacts['recognition_points'] : [];
        $evidenceItems = is_array($sourceFacts['evidence_items'] ?? null) ? $sourceFacts['evidence_items'] : [];
        $confidence = (string)($sourceFacts['confidence'] ?? 'low');

        $isSufficient = $confidence !== 'low'
            && count($recognitionPoints) >= 2
            && count($evidenceItems) >= 1;

        if (($sourceEvidence['level'] ?? 'low') === 'high' && count($evidenceItems) >= 2 && count($recognitionPoints) >= 3) {
            $isSufficient = true;
        }

        return [
            'ok' => $isSufficient,
            'code' => $isSufficient ? null : 'source_facts_low_confidence',
            'error' => $isSufficient ? null : 'Ik zie nog niet duidelijk genoeg hoe de oefening loopt.',
            'source_facts' => $sourceFacts,
        ];
    }

    private function runVideoTranslationCandidate(
        array $source,
        array $sourceFacts,
        array $sourceEvidence,
        string $fieldTypeHint,
        ?array $formState,
        string $coachRequest,
        bool $isExistingExercise,
        string $modelId,
        int $userId,
        array $sourceContext,
        array &$usageTotals,
        ?array $revisionFeedback = null,
        ?array $currentTextSuggestion = null,
        ?array $currentDrawingSuggestion = null
    ): array {
        $systemPrompt = $this->promptBuilder->buildSystemPrompt($fieldTypeHint, $formState, $sourceContext, $isExistingExercise);
        $instruction = $revisionFeedback === null
            ? $this->promptBuilder->buildSourceAnchoredGenerationInstruction(
                $source,
                $sourceFacts,
                $sourceEvidence,
                $coachRequest,
                $formState,
                $isExistingExercise
            )
            : $this->promptBuilder->buildRevisionInstruction(
                $source,
                $sourceFacts,
                $revisionFeedback,
                $currentTextSuggestion,
                $currentDrawingSuggestion,
                $coachRequest,
                $isExistingExercise
            );

        $response = $this->runModelCall(
            [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $instruction],
            ],
            $modelId,
            $userId,
            $usageTotals
        );
        if (!($response['ok'] ?? false)) {
            return [
                'ok' => false,
                'code' => 'provider_error',
                'provider_response' => $response,
            ];
        }

        $output = $this->parseAndValidateOutput(
            (string)($response['content'] ?? ''),
            $fieldTypeHint,
            $formState,
            $sourceContext,
            $coachRequest
        );
        $evaluation = $this->evaluateTranslationCandidate(
            $sourceFacts,
            $output['text_suggestion'] ?? null,
            $output['drawing_suggestion'] ?? null,
            $coachRequest,
            $modelId,
            $userId,
            $usageTotals
        );

        return [
            'ok' => true,
            'output' => $output,
            'evaluation' => $evaluation,
        ];
    }

    private function evaluateTranslationCandidate(
        array $sourceFacts,
        ?array $textSuggestion,
        ?array $drawingSuggestion,
        string $coachRequest,
        string $modelId,
        int $userId,
        array &$usageTotals
    ): array {
        $fields = is_array($textSuggestion['fields'] ?? null) ? $textSuggestion['fields'] : [];
        $description = trim((string)($fields['description'] ?? ''));
        if ($description === '') {
            return [
                'overall_score' => 1.0,
                'source_alignment' => 1,
                'coach_request_fit' => 1,
                'organization_clarity' => 1,
                'drawing_alignment' => $drawingSuggestion === null ? 1 : 2,
                'verdict' => 'revise',
                'must_fix' => ['Beschrijf begin, verloop en wissels duidelijker op basis van de video.'],
                'summary' => 'De beschrijving was nog te vaag.',
            ];
        }

        $response = $this->runModelCall(
            [[
                'role' => 'user',
                'content' => $this->promptBuilder->buildAlignmentEvaluationPrompt(
                    $sourceFacts,
                    $textSuggestion,
                    $drawingSuggestion,
                    $coachRequest
                ),
            ]],
            $modelId,
            $userId,
            $usageTotals
        );
        if (!($response['ok'] ?? false)) {
            return [
                'overall_score' => 3.0,
                'source_alignment' => 3,
                'coach_request_fit' => 3,
                'organization_clarity' => 3,
                'drawing_alignment' => $drawingSuggestion === null ? 2 : 3,
                'verdict' => 'revise',
                'must_fix' => ['Koppel de beschrijving duidelijker aan wat in de video te zien is.'],
                'summary' => 'Ik kon nog niet goed genoeg zien of de beschrijving echt past bij de video.',
            ];
        }

        $decoded = $this->parseJsonObjectResponse((string)($response['content'] ?? ''));
        if (!is_array($decoded)) {
            return [
                'overall_score' => 3.0,
                'source_alignment' => 3,
                'coach_request_fit' => 3,
                'organization_clarity' => 3,
                'drawing_alignment' => $drawingSuggestion === null ? 2 : 3,
                'verdict' => 'revise',
                'must_fix' => ['Maak de beschrijving concreter zodat begin en verloop beter kloppen met de video.'],
                'summary' => 'Ik kon de controle op de beschrijving niet goed afronden.',
            ];
        }

        return $this->normalizeQualityEvaluation($decoded);
    }

    private function runModelCall(array $messages, string $modelId, int $userId, array &$usageTotals): array {
        $response = $this->openRouterClient->chatCompletion($messages, $modelId, $userId);
        if ($response['ok'] ?? false) {
            $usage = is_array($response['usage'] ?? null) ? $response['usage'] : [];
            $usageTotals['prompt_tokens'] += max(0, (int)($usage['prompt_tokens'] ?? 0));
            $usageTotals['completion_tokens'] += max(0, (int)($usage['completion_tokens'] ?? 0));
            $usageTotals['total_tokens'] += max(0, (int)($usage['total_tokens'] ?? 0));
            $usageTotals['supplier_cost_usd'] = round(
                (float)($usageTotals['supplier_cost_usd'] ?? 0.0) + max(0.0, (float)($usage['supplier_cost_usd'] ?? 0.0)),
                6
            );
            if (!empty($response['generation_id'])) {
                $usageTotals['generation_id'] = (string)$response['generation_id'];
            }
        }

        return $response;
    }

    private function buildUsageUpdatePayload(array $usageTotals, array $model, array $settings, string $status, ?string $errorCode): array {
        $inputTokens = max(0, (int)($usageTotals['prompt_tokens'] ?? 0));
        $outputTokens = max(0, (int)($usageTotals['completion_tokens'] ?? 0));
        $totalTokens = max(0, (int)($usageTotals['total_tokens'] ?? ($inputTokens + $outputTokens)));
        $supplierCostUsd = round(max(0.0, (float)($usageTotals['supplier_cost_usd'] ?? 0.0)), 6);

        $pricingBreakdown = $this->pricingEngine->calculate(
            is_array($model['pricing'] ?? null) ? $model['pricing'] : [],
            $inputTokens,
            $outputTokens
        );

        $pricingVersion = null;
        if (isset($settings['ai_pricing_version']) && is_numeric((string)$settings['ai_pricing_version'])) {
            $candidate = (int)$settings['ai_pricing_version'];
            if ($candidate > 0) {
                $pricingVersion = $candidate;
            }
        }

        return [
            'status' => $status,
            'error_code' => $errorCode,
            'generation_id' => $usageTotals['generation_id'] ?? null,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $totalTokens,
            'supplier_cost_usd' => $supplierCostUsd,
            'billable_cost_eur' => round(max(0.0, (float)($pricingBreakdown['billable_cost_eur'] ?? 0.0)), 6),
            'pricing_version' => $pricingVersion,
            'pricing_snapshot_json' => $this->encodeJson($pricingBreakdown['pricing_snapshot'] ?? [], '{}'),
        ];
    }

    private function buildProviderFailurePayload(array $providerResponse): array {
        $httpStatus = (int)($providerResponse['http_status'] ?? 502);
        if ($httpStatus < 400 || $httpStatus > 599) {
            $httpStatus = 502;
        }

        $errorCode = $httpStatus === 429 ? 'provider_rate_limited' : 'provider_error';
        $payload = [
            'ok' => false,
            'error' => $httpStatus === 429
                ? 'Het is nu even druk. Probeer het zo opnieuw.'
                : 'Het lukt nu even niet om een antwoord te maken.',
            'code' => $errorCode,
        ];
        if (isset($providerResponse['retry_after']) && is_numeric((string)$providerResponse['retry_after'])) {
            $payload['retry_after'] = (int)$providerResponse['retry_after'];
        }

        return [
            'status' => $httpStatus,
            'payload' => $payload,
        ];
    }

    private function parseJsonObjectResponse(string $content): ?array {
        if (preg_match('/\{.*\}/s', $content, $matches) !== 1) {
            return null;
        }

        $decoded = json_decode($matches[0], true);
        return is_array($decoded) ? $decoded : null;
    }

    private function normalizeSourceFacts(array $input): array {
        $normalizeStringList = static function (mixed $value): array {
            if (!is_array($value)) {
                return [];
            }

            $items = [];
            foreach ($value as $item) {
                $text = trim((string)$item);
                if ($text !== '') {
                    $items[] = $text;
                }
            }

            return array_values(array_unique($items));
        };

        $setup = is_array($input['setup'] ?? null) ? $input['setup'] : [];
        $equipment = $normalizeStringList($setup['equipment'] ?? []);
        $recognitionPoints = $normalizeStringList($input['recognition_points'] ?? []);
        if (empty($recognitionPoints)) {
            $recognitionPoints = array_slice($normalizeStringList($input['sequence'] ?? []), 0, 3);
        }

        $confidence = strtolower(trim((string)($input['confidence'] ?? 'low')));
        if (!in_array($confidence, ['high', 'medium', 'low'], true)) {
            $confidence = 'low';
        }

        $evidenceItems = [];
        if (is_array($input['evidence_items'] ?? null)) {
            foreach ($input['evidence_items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $fact = trim((string)($item['fact'] ?? ''));
                $source = trim((string)($item['source'] ?? ''));
                $snippet = trim((string)($item['snippet'] ?? ''));
                if ($fact === '') {
                    continue;
                }
                $evidenceItems[] = [
                    'fact' => $fact,
                    'source' => $source !== '' ? $source : 'unknown',
                    'snippet' => $snippet,
                ];
            }
        }

        return [
            'summary' => trim((string)($input['summary'] ?? '')),
            'setup' => [
                'starting_shape' => trim((string)($setup['starting_shape'] ?? '')),
                'player_structure' => trim((string)($setup['player_structure'] ?? '')),
                'area' => trim((string)($setup['area'] ?? '')),
                'equipment' => $equipment,
            ],
            'sequence' => $normalizeStringList($input['sequence'] ?? []),
            'rotation' => trim((string)($input['rotation'] ?? '')),
            'rules' => $normalizeStringList($input['rules'] ?? []),
            'coach_cues' => $normalizeStringList($input['coach_cues'] ?? []),
            'recognition_points' => array_slice($recognitionPoints, 0, 5),
            'missing_details' => $normalizeStringList($input['missing_details'] ?? []),
            'confidence' => $confidence,
            'evidence_items' => $evidenceItems,
        ];
    }

    private function normalizeQualityEvaluation(array $input): array {
        $mustFix = [];
        if (is_array($input['must_fix'] ?? null)) {
            foreach ($input['must_fix'] as $item) {
                $text = trim((string)$item);
                if ($text !== '') {
                    $mustFix[] = $text;
                }
            }
        }

        $overall = round(max(1.0, min(5.0, (float)($input['overall_score'] ?? 1.0))), 1);
        $sourceAlignment = max(1, min(5, (int)round((float)($input['source_alignment'] ?? 1))));
        $coachFit = max(1, min(5, (int)round((float)($input['coach_request_fit'] ?? 1))));
        $organization = max(1, min(5, (int)round((float)($input['organization_clarity'] ?? 1))));
        $drawingAlignment = max(1, min(5, (int)round((float)($input['drawing_alignment'] ?? 1))));
        $verdict = strtolower(trim((string)($input['verdict'] ?? 'revise')));
        if (!in_array($verdict, ['pass', 'revise', 'fail'], true)) {
            $verdict = 'revise';
        }

        if ($overall >= 4.2 && $sourceAlignment >= 4 && $organization >= 4) {
            $verdict = 'pass';
        } elseif ($verdict === 'pass') {
            $verdict = 'revise';
        }

        return [
            'overall_score' => $overall,
            'source_alignment' => $sourceAlignment,
            'coach_request_fit' => $coachFit,
            'organization_clarity' => $organization,
            'drawing_alignment' => $drawingAlignment,
            'verdict' => $verdict,
            'must_fix' => array_values(array_unique($mustFix)),
            'summary' => trim((string)($input['summary'] ?? '')),
        ];
    }

    private function isCandidateBetter(array $candidate, array $currentBest): bool {
        $candidateScore = (float)($candidate['evaluation']['overall_score'] ?? 0.0);
        $bestScore = (float)($currentBest['evaluation']['overall_score'] ?? 0.0);
        if ($candidateScore !== $bestScore) {
            return $candidateScore > $bestScore;
        }

        $candidateAlignment = (int)($candidate['evaluation']['source_alignment'] ?? 0);
        $bestAlignment = (int)($currentBest['evaluation']['source_alignment'] ?? 0);
        return $candidateAlignment > $bestAlignment;
    }

    private function buildAlignmentFailureMessage(array $evaluation, array $sourceEvidence): string {
        $summary = trim((string)($evaluation['summary'] ?? ''));
        $mustFix = is_array($evaluation['must_fix'] ?? null) ? array_values(array_filter(array_map(
            static fn(mixed $item): string => trim((string)$item),
            $evaluation['must_fix']
        ))) : [];

        $parts = [
            'Ik heb de oefening nog eens gecontroleerd, maar hij past nog niet goed genoeg bij de video.',
        ];
        if ($summary !== '') {
            $parts[] = $summary;
        }
        if (!empty($mustFix)) {
            $parts[] = 'Wat nog beter moet: ' . implode('; ', array_slice($mustFix, 0, 3)) . '.';
        }
        if (($sourceEvidence['level'] ?? 'low') !== 'high') {
            $parts[] = 'De video geeft zelf ook nog te weinig houvast.';
        }

        return implode(' ', $parts);
    }

    private function resolveQualityGateMode(array $settings): string
    {
        $mode = strtolower(trim((string)($settings['ai_quality_gate_mode'] ?? 'warn')));
        if ($mode === 'soft') {
            $mode = 'warn';
        }

        return in_array($mode, ['hard', 'warn', 'off'], true) ? $mode : 'warn';
    }

    private function passesConfiguredQualityGate(array $evaluation, array $settings): bool
    {
        $minScore = max(1.0, min(5.0, (float)($settings['ai_quality_min_score'] ?? 4.2)));

        return (float)($evaluation['overall_score'] ?? 0.0) >= $minScore
            && (int)($evaluation['source_alignment'] ?? 0) >= 4
            && (int)($evaluation['organization_clarity'] ?? 0) >= 4
            && (int)($evaluation['drawing_alignment'] ?? 0) >= 4;
    }

    private function shouldDropDrawingSuggestion(array $evaluation): bool
    {
        return (int)($evaluation['drawing_alignment'] ?? 0) < 4;
    }

    private function hasUsableDrawingSuggestion(?array $drawingSuggestion): bool
    {
        if (!is_array($drawingSuggestion)) {
            return false;
        }

        $drawingData = trim((string)($drawingSuggestion['drawing_data'] ?? ''));
        $nodeCount = max(0, (int)($drawingSuggestion['node_count'] ?? 0));

        return $drawingData !== '' && $nodeCount > 0;
    }

    private function shouldSimplifyDrawingForLowEvidence(array $sourceEvidence, ?array $drawingSuggestion): bool
    {
        if (!is_array($drawingSuggestion)) {
            return false;
        }
        if (!empty($sourceEvidence['is_sufficient'])) {
            return false;
        }

        $nodeCount = max(0, (int)($drawingSuggestion['node_count'] ?? 0));
        $durationSeconds = max(0, (int)($sourceEvidence['duration_seconds'] ?? 0));
        $chapterCount = max(0, (int)($sourceEvidence['chapter_count'] ?? 0));
        $transcriptSource = trim((string)($sourceEvidence['transcript_source'] ?? 'none'));

        if ($nodeCount >= 26) {
            return true;
        }
        if ($nodeCount >= 20 && $durationSeconds > 0 && $durationSeconds < 45 && $chapterCount === 0) {
            return true;
        }
        if ($nodeCount >= 18 && $transcriptSource === 'metadata_fallback' && $chapterCount === 0) {
            return true;
        }

        return false;
    }

    private function buildFallbackDrawingSuggestion(?array $textSuggestion, string $fieldTypeHint): ?array
    {
        if (!is_array($textSuggestion)) {
            return null;
        }

        $fields = is_array($textSuggestion['fields'] ?? null) ? $textSuggestion['fields'] : [];
        if (empty($fields)) {
            return null;
        }

        $fieldType = trim((string)($fields['field_type'] ?? $fieldTypeHint));
        if ($fieldType === '') {
            $fieldType = 'portrait';
        }

        return $this->konvaSanitizer->buildFallbackDrawing($fields, $fieldType);
    }

    public function applyText(): void {
        $this->requireAuth();
        $this->requireApiCsrf();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['ok' => false, 'error' => 'Deze actie mag nu niet.'], 405);
        }

        $access = $this->accessService->checkBasicAiModeAccess((int)Session::get('user_id'));
        if (!$access['ok']) {
            $this->jsonResponse(['ok' => false, 'error' => $access['error']], (int)$access['status']);
        }

        $rawPayload = trim((string)($_POST['payload'] ?? ''));
        if ($rawPayload === '') {
            $this->jsonResponse(['ok' => false, 'error' => 'Er ontbreken gegevens.'], 422);
        }

        $decoded = json_decode($rawPayload, true);
        if (!is_array($decoded)) {
            $this->jsonResponse(['ok' => false, 'error' => 'De ontvangen gegevens kloppen niet.'], 422);
        }

        $validated = $this->exerciseValidator->validate($decoded, $this->promptBuilder->fetchExerciseOptions());

        $this->jsonResponse([
            'ok' => true,
            'fields' => $validated['fields'],
            'warnings' => $validated['warnings'],
            'applied_count' => $validated['applied_count'],
        ]);
    }

    public function applyDrawing(): void {
        $this->requireAuth();
        $this->requireApiCsrf();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['ok' => false, 'error' => 'Deze actie mag nu niet.'], 405);
        }

        $access = $this->accessService->checkBasicAiModeAccess((int)Session::get('user_id'));
        if (!$access['ok']) {
            $this->jsonResponse(['ok' => false, 'error' => $access['error']], (int)$access['status']);
        }

        $rawPayload = trim((string)($_POST['payload'] ?? ''));
        $fieldType = trim((string)($_POST['field_type'] ?? 'portrait'));

        if ($rawPayload === '') {
            $this->jsonResponse(['ok' => false, 'error' => 'Er ontbreken gegevens.'], 422);
        }

        $decoded = json_decode($rawPayload, true);
        if (!is_array($decoded)) {
            $this->jsonResponse(['ok' => false, 'error' => 'De ontvangen gegevens kloppen niet.'], 422);
        }

        $sanitized = $this->konvaSanitizer->sanitize($decoded, $fieldType);

        $this->jsonResponse([
            'ok' => true,
            'drawing_data' => $sanitized['drawing_data'],
            'field_type' => $sanitized['field_type'],
            'warnings' => $sanitized['warnings'],
            'node_count' => $sanitized['node_count'],
        ]);
    }

    public function listModels(): void {
        $this->requireAuth();

        $access = $this->accessService->checkAiAccess((int)Session::get('user_id'), null, false, false, true);
        if (!$access['ok']) {
            $this->jsonResponse([
                'ok' => false,
                'error' => $access['error'],
                'code' => $access['error_code'] ?? 'blocked',
            ], (int)$access['status']);
        }

        $defaultModel = (string)($access['models'][0]['model_id'] ?? '');

        $models = array_map(static function (array $model) use ($defaultModel): array {
            return [
                'model_id' => $model['model_id'],
                'label' => $model['label'],
                'is_default' => $defaultModel !== '' && $defaultModel === $model['model_id'],
            ];
        }, $access['models']);

        $this->jsonResponse([
            'ok' => true,
            'models' => $models,
            'default_model' => $defaultModel,
        ]);
    }

    public function listSessions(): void {
        $this->requireAuth();

        $userId = (int)Session::get('user_id');
        $teamId = $this->currentTeamId();

        $sql = "SELECT
                    s.id,
                    s.title,
                    s.created_at,
                    s.updated_at,
                    (
                        SELECT content
                        FROM ai_chat_messages m
                        WHERE m.session_id = s.id
                        ORDER BY m.created_at DESC, m.id DESC
                        LIMIT 1
                    ) AS last_message
                FROM ai_chat_sessions s
                WHERE s.user_id = :user_id";

        $params = [':user_id' => $userId];
        if ($teamId !== null) {
            $sql .= ' AND (s.team_id = :team_id OR s.team_id IS NULL)';
            $params[':team_id'] = $teamId;
        }

        $sql .= ' ORDER BY s.updated_at DESC, s.id DESC LIMIT 100';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $sessions = $stmt->fetchAll();

        $this->jsonResponse([
            'ok' => true,
            'sessions' => $sessions,
        ]);
    }

    public function getSession(): void {
        $this->requireAuth();

        $userId = (int)Session::get('user_id');
        $sessionId = (int)($_GET['id'] ?? 0);
        if ($sessionId <= 0) {
            $this->jsonResponse(['ok' => false, 'error' => 'Dit gesprek klopt niet meer.'], 422);
        }

        $session = $this->sessionService->getSessionForUser($sessionId, $userId, $this->currentTeamId());
        if ($session === null) {
            $this->jsonResponse(['ok' => false, 'error' => 'Dit gesprek is niet gevonden.'], 404);
        }

        $messageStmt = $this->pdo->prepare(
            'SELECT id, role, content, model_id, metadata_json, created_at
             FROM ai_chat_messages
             WHERE session_id = :session_id
             ORDER BY created_at ASC, id ASC'
        );
        $messageStmt->execute([':session_id' => $sessionId]);

        $messages = [];
        foreach ($messageStmt->fetchAll() as $row) {
            $metadata = null;
            if (!empty($row['metadata_json'])) {
                $decoded = json_decode((string)$row['metadata_json'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $metadata = $decoded;
                }
            }

            $messages[] = [
                'id' => (int)$row['id'],
                'role' => $row['role'],
                'content' => $row['content'],
                'model_id' => $row['model_id'],
                'metadata' => $metadata,
                'created_at' => $row['created_at'],
            ];
        }

        $this->jsonResponse([
            'ok' => true,
            'session' => $session,
            'messages' => $messages,
        ]);
    }

    public function usageSummary(): void {
        $this->requireAuth();

        $summary = $this->usageService->buildSummary((int)Session::get('user_id'), $this->accessService->getSettingsWithDefaults());

        $this->jsonResponse([
            'ok' => true,
            'summary' => $summary,
        ]);
    }

    public function usageHistory(): void {
        $this->requireAuth();

        $userId = (int)Session::get('user_id');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(100, (int)($_GET['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM ai_usage_events WHERE user_id = :user_id');
        $countStmt->execute([':user_id' => $userId]);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            'SELECT id, model_id, status, input_tokens, output_tokens, total_tokens, supplier_cost_usd, billable_cost_eur, error_code, created_at
             FROM ai_usage_events
             WHERE user_id = :user_id
             ORDER BY created_at DESC, id DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        $this->jsonResponse([
            'ok' => true,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ],
            'events' => $rows,
        ]);
    }

    private function parseFormState(): ?array {
        $raw = trim((string)($_POST['form_state'] ?? ''));
        if ($raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }
        $allowed = ['title','description','variation','coach_instructions','source','team_task',
                     'objectives','actions','min_players','max_players','duration','field_type','has_drawing'];
        return array_intersect_key($decoded, array_flip($allowed));
    }

    private function parseAndValidateOutput(
        string $rawContent,
        string $fieldTypeHint,
        ?array $formState,
        array $sourceContext,
        string $latestUserMessage
    ): array {
        $parsedOutput = $this->outputParser->parse($rawContent);
        $warnings = is_array($parsedOutput['warnings'] ?? null) ? $parsedOutput['warnings'] : [];

        $textSuggestion = null;
        if (is_array($parsedOutput['exercise_raw'] ?? null)) {
            $textSuggestion = $this->exerciseValidator->validate(
                $parsedOutput['exercise_raw'],
                $this->promptBuilder->fetchExerciseOptions()
            );
            $textSuggestion = $this->workflowService->applySourceConstraintAlignment(
                $textSuggestion, $sourceContext, $formState, $latestUserMessage
            );
            if (is_array($textSuggestion['warnings'] ?? null)) {
                $warnings = array_merge($warnings, $textSuggestion['warnings']);
            }

            $sanitizedText = $this->sanitizeCoachFacingTextSuggestion($textSuggestion);
            $textSuggestion = $sanitizedText['text_suggestion'];
            if (!empty($sanitizedText['warnings'])) {
                $warnings = array_merge($warnings, $sanitizedText['warnings']);
            }
        }

        $drawingSuggestion = null;
        if (is_array($parsedOutput['drawing_raw'] ?? null)) {
            $drawingFieldType = $fieldTypeHint;
            if (is_array($textSuggestion) && !empty($textSuggestion['fields']['field_type'])) {
                $drawingFieldType = (string)$textSuggestion['fields']['field_type'];
            } elseif (!empty($formState['field_type'])) {
                $drawingFieldType = (string)$formState['field_type'];
            }

            $drawingSuggestion = $this->konvaSanitizer->sanitize($parsedOutput['drawing_raw'], $drawingFieldType);
            if (is_array($drawingSuggestion['warnings'] ?? null)) {
                $warnings = array_merge($warnings, $drawingSuggestion['warnings']);
            }
            if (!$this->hasUsableDrawingSuggestion($drawingSuggestion)) {
                $drawingSuggestion = null;
                $warnings[] = 'De voorgestelde tekening was leeg en is genegeerd.';
            }
        }

        $normalizedWarnings = [];
        foreach ($warnings as $warning) {
            $value = trim((string)$warning);
            if ($value !== '') {
                $normalizedWarnings[$value] = true;
            }
        }

        return [
            'chat_text' => trim((string)($parsedOutput['chat_text'] ?? '')),
            'text_suggestion' => $textSuggestion,
            'drawing_suggestion' => $drawingSuggestion,
            'warnings' => array_keys($normalizedWarnings),
        ];
    }

    private function sanitizeCoachFacingTextSuggestion(array $textSuggestion): array
    {
        $fields = is_array($textSuggestion['fields'] ?? null) ? $textSuggestion['fields'] : [];
        if (empty($fields)) {
            return [
                'text_suggestion' => $textSuggestion,
                'warnings' => [],
            ];
        }

        $changed = false;
        foreach (['description', 'variation', 'coach_instructions'] as $fieldName) {
            if (!array_key_exists($fieldName, $fields)) {
                continue;
            }

            $original = trim((string)$fields[$fieldName]);
            if ($original === '') {
                continue;
            }

            $cleaned = $this->stripInternalMetaText($original);
            if ($cleaned === $original) {
                continue;
            }

            $fields[$fieldName] = $cleaned;
            $changed = true;
        }

        if ($changed) {
            $textSuggestion['fields'] = $fields;
            return [
                'text_suggestion' => $textSuggestion,
                'warnings' => ['Ik heb interne AI-tekst weggehaald zodat alleen coachbare inhoud overblijft.'],
            ];
        }

        return [
            'text_suggestion' => $textSuggestion,
            'warnings' => [],
        ];
    }

    private function stripInternalMetaText(string $value): string
    {
        $parts = preg_split('/(?<=[.!?])\s+|\R+/u', trim($value));
        if (!is_array($parts) || empty($parts)) {
            return trim($value);
        }

        $kept = [];
        foreach ($parts as $part) {
            $sentence = trim((string)$part);
            if ($sentence === '') {
                continue;
            }
            if ($this->isInternalMetaSentence($sentence)) {
                continue;
            }
            $kept[] = $sentence;
        }

        if (empty($kept)) {
            return '';
        }

        $joined = implode(' ', $kept);
        $joined = preg_replace('/\s{2,}/u', ' ', $joined) ?? $joined;
        return trim($joined);
    }

    private function isInternalMetaSentence(string $sentence): bool
    {
        $patterns = [
            '/brongetrouwe\s+scenesamenvatting/i',
            '/volledig\s+verifieerbare\s+oefenorganisatie/i',
            '/source[_\s-]?facts?/i',
            '/source[_\s-]?evidence/i',
            '/metadata[_\s-]?fallback/i',
            '/\btranscript(?:_source)?\b/i',
            '/\bchapter_count\b/i',
            '/\bbronzekerheid\b/i',
            '/^aannames?:\s*$/i',
            '/^concept(?:-|\s)?aannames?:\s*$/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $sentence) === 1) {
                return true;
            }
        }

        return false;
    }

    private function currentTeamId(): ?int {
        if (!Session::has('current_team')) {
            return null;
        }

        $team = Session::get('current_team');
        if (!is_array($team) || !isset($team['id'])) {
            return null;
        }

        $id = (int)$team['id'];
        return $id > 0 ? $id : null;
    }

    private function requireApiCsrf(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? null;
            if (!Csrf::verifyToken(is_string($token) ? $token : null)) {
                $this->jsonResponse([
                    'ok' => false,
                    'error' => 'Je sessie klopt niet meer. Vernieuw de pagina en probeer opnieuw.',
                    'code' => 'invalid_csrf',
                ], 419);
            }
        }
    }

    private function jsonResponse(array $data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo $this->encodeJson($data, '{"ok":false,"error":"Kon response niet serialiseren."}');
        exit;
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
