<?php
declare(strict_types=1);

/**
 * Handles AI access checks: mode, API keys, models, rate limiting, concurrency, and budget.
 */
class AiAccessService {
    private ?array $cachedSettings = null;

    public function __construct(
        private PDO $pdo,
        private AppSetting $appSetting,
        private AiUsageService $usageService
    ) {}

    public function checkAiAccess(
        int $userId,
        ?string $requestedModelId,
        bool $checkRateLimit,
        bool $checkConcurrency,
        bool $requireProviderKey
    ): array {
        $settings = $this->getSettingsWithDefaults();

        $mode = (string)($settings['ai_access_mode'] ?? 'off');
        if ($mode === 'off') {
            return [
                'ok' => false,
                'status' => 403,
                'error' => 'AI staat nu uit.',
                'error_code' => 'ai_disabled',
            ];
        }

        if ($mode === 'selective' && !$this->isUserAiEnabled($userId)) {
            return [
                'ok' => false,
                'status' => 403,
                'error' => 'AI staat niet aan voor jouw account.',
                'error_code' => 'ai_user_disabled',
            ];
        }

        if ($requireProviderKey) {
            $apiKeyEnc = $settings['openrouter_api_key_enc'] ?? null;
            if ($apiKeyEnc === null || trim((string)$apiKeyEnc) === '') {
                return [
                    'ok' => false,
                    'status' => 503,
                    'error' => 'AI is nu niet klaar voor gebruik.',
                    'error_code' => 'missing_api_key',
                ];
            }

            try {
                $decrypted = Encryption::decrypt((string)$apiKeyEnc);
                if (trim($decrypted) === '') {
                    return [
                        'ok' => false,
                        'status' => 503,
                        'error' => 'AI is nu niet klaar voor gebruik.',
                        'error_code' => 'invalid_api_key',
                    ];
                }
            } catch (Throwable $e) {
                return [
                    'ok' => false,
                    'status' => 503,
                    'error' => 'AI is nu niet klaar voor gebruik.',
                    'error_code' => 'invalid_api_key',
                ];
            }
        }

        $publicModels = $this->fetchPublishableModels();
        if (empty($publicModels)) {
            return [
                'ok' => false,
                'status' => 503,
                'error' => 'AI is nu even niet beschikbaar.',
                'error_code' => 'no_publishable_models',
            ];
        }

        $selectedModel = $this->resolveRequestedModel($requestedModelId, $publicModels);
        if ($selectedModel === null) {
            return [
                'ok' => false,
                'status' => 422,
                'error' => 'Deze AI-keuze is nu niet beschikbaar.',
                'error_code' => 'invalid_model',
            ];
        }

        if ($checkRateLimit) {
            $rateLimit = max(1, (int)($settings['ai_rate_limit_per_minute'] ?? 10));
            $countStmt = $this->pdo->prepare(
                "SELECT COUNT(*)
                 FROM ai_usage_events
                 WHERE user_id = :user_id
                   AND created_at > datetime('now', '-1 minute')"
            );
            $countStmt->execute([':user_id' => $userId]);
            $recentCount = (int)$countStmt->fetchColumn();

            if ($recentCount >= $rateLimit) {
                return [
                    'ok' => false,
                    'status' => 429,
                    'error' => 'Even wachten. Probeer het zo opnieuw.',
                    'error_code' => 'rate_limited',
                    'model_id' => $selectedModel['model_id'],
                ];
            }
        }

        if ($checkConcurrency) {
            $this->usageService->cleanupStaleInProgress($userId, 3);

            $progressStmt = $this->pdo->prepare(
                "SELECT COUNT(*)
                 FROM ai_usage_events
                 WHERE user_id = :user_id
                   AND status = 'in_progress'
                   AND created_at > datetime('now', '-3 minutes')"
            );
            $progressStmt->execute([':user_id' => $userId]);
            if ((int)$progressStmt->fetchColumn() > 0) {
                return [
                    'ok' => false,
                    'status' => 409,
                    'error' => 'Even wachten. Er loopt nog een ander verzoek.',
                    'error_code' => 'concurrent_request',
                    'model_id' => $selectedModel['model_id'],
                ];
            }
        }

        if ((string)($settings['ai_billing_enabled'] ?? '1') === '1' && (string)($settings['ai_budget_mode'] ?? 'monthly_per_user') === 'monthly_per_user') {
            $monthlyBudgetRaw = $settings['ai_monthly_user_budget_eur'] ?? null;
            if ($monthlyBudgetRaw !== null && trim((string)$monthlyBudgetRaw) !== '' && is_numeric((string)$monthlyBudgetRaw)) {
                $summary = $this->usageService->buildSummary($userId, $settings);
                $remaining = $summary['remaining_budget_eur'];
                $minimumNeeded = max(0.0, (float)($selectedModel['pricing']['min_request_price'] ?? 0.0));

                if ($remaining !== null && $remaining < $minimumNeeded) {
                    return [
                        'ok' => false,
                        'status' => 402,
                        'error' => 'Je AI-budget is op voor deze periode.',
                        'error_code' => 'budget_exhausted',
                        'model_id' => $selectedModel['model_id'],
                    ];
                }
            }
        }

        return [
            'ok' => true,
            'status' => 200,
            'settings' => $settings,
            'models' => $publicModels,
            'model' => $selectedModel,
            'model_id' => $selectedModel['model_id'],
        ];
    }

    public function checkBasicAiModeAccess(int $userId): array {
        $settings = $this->getSettingsWithDefaults();
        $mode = (string)($settings['ai_access_mode'] ?? 'off');

        if ($mode === 'off') {
            return [
                'ok' => false,
                'status' => 403,
                'error' => 'AI staat nu uit.',
            ];
        }

        if ($mode === 'selective' && !$this->isUserAiEnabled($userId)) {
            return [
                'ok' => false,
                'status' => 403,
                'error' => 'AI staat niet aan voor jouw account.',
            ];
        }

        return ['ok' => true, 'status' => 200];
    }

    public function getSettingsWithDefaults(): array {
        if ($this->cachedSettings !== null) {
            return $this->cachedSettings;
        }

        $defaults = [
            'ai_access_mode' => 'off',
            'openrouter_api_key_enc' => null,
            'youtube_api_key_enc' => null,
            'ai_billing_enabled' => '1',
            'ai_pricing_version' => '1',
            'ai_budget_mode' => 'monthly_per_user',
            'ai_monthly_user_budget_eur' => null,
            'ai_budget_reset_day' => '1',
            'ai_rate_limit_per_minute' => '10',
            'ai_max_sessions_per_user' => '50',
            'ai_retrieval_enabled' => '1',
            'ai_retrieval_youtube_enabled' => '1',
            'ai_retrieval_max_candidates' => '10',
            'ai_retrieval_min_youtube_sources' => '2',
            'ai_retrieval_internal_limit' => '2',
            'ai_ytdlp_cookies_path' => '',
            'ai_retrieval_llm_rerank_enabled' => '1',
            'ai_source_min_evidence_score' => '0.55',
            'ai_quality_gate_mode' => 'warn',
            'ai_quality_min_score' => '4.2',
            'ai_quality_max_rewrites' => '1',
        ];

        $stored = $this->appSetting->getMany(array_keys($defaults));

        $settings = $defaults;
        foreach ($stored as $key => $value) {
            $settings[$key] = $value;
        }

        $this->cachedSettings = $settings;
        return $settings;
    }

    private function isUserAiEnabled(int $userId): bool {
        $stmt = $this->pdo->prepare('SELECT ai_access_enabled FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $value = $stmt->fetchColumn();

        return (int)$value === 1;
    }

    public function fetchPublishableModels(): array {
        $stmt = $this->pdo->query(
            "SELECT
                m.model_id,
                m.label,
                m.sort_order,
                p.currency,
                p.input_price_per_mtoken,
                p.output_price_per_mtoken,
                p.request_flat_price,
                p.min_request_price
             FROM ai_models m
             INNER JOIN ai_model_pricing p ON p.model_id = m.model_id
             WHERE m.enabled = 1
               AND p.is_active = 1
               AND TRIM(m.model_id) <> ''
               AND TRIM(m.label) <> ''
             ORDER BY m.sort_order ASC, m.id ASC"
        );

        $models = [];
        foreach ($stmt->fetchAll() as $row) {
            $models[] = [
                'model_id' => (string)$row['model_id'],
                'label' => (string)$row['label'],
                'sort_order' => (int)$row['sort_order'],
                'pricing' => [
                    'currency' => (string)$row['currency'],
                    'input_price_per_mtoken' => (float)$row['input_price_per_mtoken'],
                    'output_price_per_mtoken' => (float)$row['output_price_per_mtoken'],
                    'request_flat_price' => (float)$row['request_flat_price'],
                    'min_request_price' => (float)$row['min_request_price'],
                ],
            ];
        }

        return $models;
    }

    public function resolveRequestedModel(?string $requestedModelId, array $publicModels): ?array {
        $modelsById = [];
        foreach ($publicModels as $model) {
            $modelsById[$model['model_id']] = $model;
        }

        $requestedModelId = trim((string)$requestedModelId);
        if ($requestedModelId !== '') {
            return $modelsById[$requestedModelId] ?? null;
        }

        return $publicModels[0] ?? null;
    }

    /**
     * Resolve the vision model to use for multimodal analysis.
     * Uses the first publishable vision-capable model.
     */
    public function resolveVisionModel(?array $settings = null): ?array {
        $models = $this->fetchVisionCapableModels();
        return $models[0] ?? null;
    }

    /**
     * Fetch all enabled models that have supports_vision = 1 and active pricing.
     */
    public function fetchVisionCapableModels(): array {
        $stmt = $this->pdo->query(
            "SELECT
                m.model_id,
                m.label,
                m.sort_order,
                p.currency,
                p.input_price_per_mtoken,
                p.output_price_per_mtoken,
                p.request_flat_price,
                p.min_request_price
             FROM ai_models m
             INNER JOIN ai_model_pricing p ON p.model_id = m.model_id
             WHERE m.enabled = 1
               AND m.supports_vision = 1
               AND p.is_active = 1
               AND TRIM(m.model_id) <> ''
               AND TRIM(m.label) <> ''
             ORDER BY m.sort_order ASC, m.id ASC"
        );

        $models = [];
        foreach ($stmt->fetchAll() as $row) {
            $models[] = [
                'model_id' => (string)$row['model_id'],
                'label' => (string)$row['label'],
                'sort_order' => (int)$row['sort_order'],
                'pricing' => [
                    'currency' => (string)$row['currency'],
                    'input_price_per_mtoken' => (float)$row['input_price_per_mtoken'],
                    'output_price_per_mtoken' => (float)$row['output_price_per_mtoken'],
                    'request_flat_price' => (float)$row['request_flat_price'],
                    'min_request_price' => (float)$row['min_request_price'],
                ],
            ];
        }

        return $models;
    }
}
