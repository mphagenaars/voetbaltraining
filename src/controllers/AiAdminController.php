<?php
declare(strict_types=1);

class AiAdminController extends BaseController {
    private const ACCESS_MODES = ['off', 'selective', 'on'];
    private const BUDGET_MODES = ['none', 'monthly_per_user'];

    private AppSetting $settings;

    public function __construct(PDO $pdo) {
        parent::__construct($pdo);
        $this->settings = new AppSetting($pdo);
    }

    public function manageAiSettings(): void {
        $this->requireAdmin();

        $settings = $this->getSettingsWithDefaults();
        $models = $this->fetchModelsWithPricing();

        View::render('admin/ai_settings', [
            'pageTitle' => 'AI Module - Admin',
            'settings' => $settings,
            'models' => $models,
            'hasEncryptionKey' => Config::hasEncryptionKey(),
            'apiKeyMasked' => $this->getMaskedKey('openrouter_api_key_enc'),
            'managementKeyMasked' => $this->getMaskedKey('openrouter_management_api_key_enc'),
            'youtubeKeyMasked' => $this->getMaskedKey('youtube_api_key_enc'),
            'usageSummary' => $this->getUsageSummary(),
        ]);
    }

    public function updateAiAccessMode(): void {
        $this->requireAdmin();
        $this->verifyCsrf('/admin/ai/settings');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/ai/settings');
        }

        $mode = trim((string)($_POST['ai_access_mode'] ?? ''));
        if (!in_array($mode, self::ACCESS_MODES, true)) {
            Session::flash('error', 'Ongeldige AI-toegangsmodus.');
            $this->redirect('/admin/ai/settings');
        }

        $this->settings->set('ai_access_mode', $mode);
        Session::flash('success', 'AI-toegangsmodus opgeslagen.');
        $this->redirect('/admin/ai/settings');
    }

    public function saveOpenRouterApiKey(): void {
        $this->saveEncryptedApiKey('openrouter_api_key_enc', 'api_key', 'Inference API key opgeslagen.');
    }

    public function deleteOpenRouterApiKey(): void {
        $this->deleteEncryptedApiKey('openrouter_api_key_enc', 'Inference API key verwijderd.');
    }

    public function saveManagementApiKey(): void {
        $this->saveEncryptedApiKey('openrouter_management_api_key_enc', 'management_api_key', 'Management API key opgeslagen.');
    }

    public function deleteManagementApiKey(): void {
        $this->deleteEncryptedApiKey('openrouter_management_api_key_enc', 'Management API key verwijderd.');
    }

    public function saveYouTubeApiKey(): void {
        $this->saveEncryptedApiKey('youtube_api_key_enc', 'youtube_api_key', 'YouTube API key opgeslagen.');
    }

    public function deleteYouTubeApiKey(): void {
        $this->deleteEncryptedApiKey('youtube_api_key_enc', 'YouTube API key verwijderd.');
    }

    public function createAiModel(): void {
        $this->requireAdmin();
        $this->verifyCsrf('/admin/ai/settings');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/ai/settings');
        }

        $modelId = trim((string)($_POST['model_id'] ?? ''));
        $label = trim((string)($_POST['label'] ?? ''));
        $enabled = 1;
        $supportsVision = $this->inferModelSupportsVision($modelId);

        if ($modelId === '' || $label === '') {
            Session::flash('error', 'Model ID en label zijn verplicht.');
            $this->redirect('/admin/ai/settings');
        }

        $sortOrderRaw = trim((string)($_POST['sort_order'] ?? ''));
        $sortOrder = $sortOrderRaw !== ''
            ? (int)$sortOrderRaw
            : ((int)$this->pdo->query('SELECT COALESCE(MAX(sort_order), -1) FROM ai_models')->fetchColumn() + 1);

        $stmt = $this->pdo->prepare(
            'INSERT INTO ai_models (model_id, label, enabled, supports_vision, sort_order, created_at, updated_at)
             VALUES (:model_id, :label, :enabled, :supports_vision, :sort_order, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );

        try {
            $stmt->execute([
                ':model_id' => $modelId,
                ':label' => $label,
                ':enabled' => $enabled,
                ':supports_vision' => $supportsVision,
                ':sort_order' => $sortOrder,
            ]);
            Session::flash('success', 'Model toegevoegd.');
        } catch (PDOException $e) {
            Session::flash('error', 'Kon model niet toevoegen (bestaat mogelijk al).');
        }

        $this->redirect('/admin/ai/settings');
    }

    public function updateAiModel(): void {
        $this->requireAdmin();
        $this->verifyCsrf('/admin/ai/settings');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/ai/settings');
        }

        $id = (int)($_POST['id'] ?? 0);
        $label = trim((string)($_POST['label'] ?? ''));
        $sortOrderRaw = isset($_POST['sort_order']) ? trim((string)$_POST['sort_order']) : '';

        if ($id <= 0 || $label === '') {
            Session::flash('error', 'Ongeldige modelgegevens.');
            $this->redirect('/admin/ai/settings');
        }

        $modelIdStmt = $this->pdo->prepare('SELECT model_id FROM ai_models WHERE id = :id LIMIT 1');
        $modelIdStmt->execute([':id' => $id]);
        $modelId = trim((string)$modelIdStmt->fetchColumn());
        if ($modelId === '') {
            Session::flash('error', 'Model niet gevonden.');
            $this->redirect('/admin/ai/settings');
        }
        $supportsVision = $this->inferModelSupportsVision($modelId);

        if ($sortOrderRaw !== '') {
            $sortOrder = (int)$sortOrderRaw;
        } else {
            $sortStmt = $this->pdo->prepare('SELECT sort_order FROM ai_models WHERE id = :id LIMIT 1');
            $sortStmt->execute([':id' => $id]);
            $sortOrder = (int)$sortStmt->fetchColumn();
        }

        $stmt = $this->pdo->prepare(
            'UPDATE ai_models
             SET label = :label,
                 enabled = 1,
                 supports_vision = :supports_vision,
                 sort_order = :sort_order,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $id,
            ':label' => $label,
            ':supports_vision' => $supportsVision,
            ':sort_order' => $sortOrder,
        ]);

        Session::flash('success', 'Model bijgewerkt.');
        $this->redirect('/admin/ai/settings');
    }

    public function deleteAiModel(): void {
        $this->requireAdmin();
        $this->verifyCsrf('/admin/ai/settings');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/ai/settings');
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            Session::flash('error', 'Ongeldig model.');
            $this->redirect('/admin/ai/settings');
        }

        $stmt = $this->pdo->prepare('SELECT model_id FROM ai_models WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $modelId = $stmt->fetchColumn();

        if ($modelId === false) {
            Session::flash('error', 'Model niet gevonden.');
            $this->redirect('/admin/ai/settings');
        }

        $this->pdo->beginTransaction();
        try {
            $deletePricingStmt = $this->pdo->prepare('DELETE FROM ai_model_pricing WHERE model_id = :model_id');
            $deletePricingStmt->execute([':model_id' => $modelId]);

            $deleteModelStmt = $this->pdo->prepare('DELETE FROM ai_models WHERE id = :id');
            $deleteModelStmt->execute([':id' => $id]);

            $this->pdo->commit();
            Session::flash('success', 'Model verwijderd.');
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            Session::flash('error', 'Model verwijderen is mislukt.');
        }

        $this->redirect('/admin/ai/settings');
    }

    public function reorderAiModels(): void {
        $this->requireAdmin();
        $this->verifyCsrf('/admin/ai/settings');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/ai/settings');
        }

        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid ids payload']);
            exit;
        }

        $stmt = $this->pdo->prepare('UPDATE ai_models SET sort_order = :sort_order, updated_at = CURRENT_TIMESTAMP WHERE id = :id');

        $this->pdo->beginTransaction();
        try {
            foreach ($ids as $index => $id) {
                $stmt->execute([
                    ':sort_order' => (int)$index,
                    ':id' => (int)$id,
                ]);
            }
            $this->pdo->commit();

            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Opslaan van modelvolgorde is mislukt.']);
        }
        exit;
    }

    public function updateAiModelPricing(): void {
        $this->requireAdmin();
        $this->verifyCsrf('/admin/ai/settings');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/ai/settings');
        }

        $modelId = trim((string)($_POST['model_id'] ?? ''));
        $currency = strtoupper(trim((string)($_POST['currency'] ?? 'EUR')));

        if ($modelId === '' || !$this->modelExists($modelId)) {
            Session::flash('error', 'Onbekend model voor pricing.');
            $this->redirect('/admin/ai/settings');
        }

        if ($currency !== 'EUR') {
            Session::flash('error', 'Alleen EUR wordt ondersteund voor pricing.');
            $this->redirect('/admin/ai/settings');
        }

        $inputPrice = $this->parseNonNegativeFloat($_POST['input_price_per_mtoken'] ?? null);
        $outputPrice = $this->parseNonNegativeFloat($_POST['output_price_per_mtoken'] ?? null);
        $requestFlatPrice = $this->parseNonNegativeFloat($_POST['request_flat_price'] ?? null);
        $minRequestPrice = $this->parseNonNegativeFloat($_POST['min_request_price'] ?? null);

        if ($inputPrice === null || $outputPrice === null || $requestFlatPrice === null || $minRequestPrice === null) {
            Session::flash('error', 'Ongeldige pricing invoer. Alle bedragen moeten 0 of hoger zijn.');
            $this->redirect('/admin/ai/settings');
        }

        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        $stmt = $this->pdo->prepare(
            "INSERT INTO ai_model_pricing (
                model_id, currency, input_price_per_mtoken, output_price_per_mtoken,
                request_flat_price, min_request_price, is_active, updated_at
             ) VALUES (
                :model_id, :currency, :input_price, :output_price,
                :request_flat_price, :min_request_price, :is_active, CURRENT_TIMESTAMP
             )
             ON CONFLICT(model_id) DO UPDATE SET
                currency = excluded.currency,
                input_price_per_mtoken = excluded.input_price_per_mtoken,
                output_price_per_mtoken = excluded.output_price_per_mtoken,
                request_flat_price = excluded.request_flat_price,
                min_request_price = excluded.min_request_price,
                is_active = excluded.is_active,
                updated_at = CURRENT_TIMESTAMP"
        );

        $stmt->execute([
            ':model_id' => $modelId,
            ':currency' => $currency,
            ':input_price' => $inputPrice,
            ':output_price' => $outputPrice,
            ':request_flat_price' => $requestFlatPrice,
            ':min_request_price' => $minRequestPrice,
            ':is_active' => $isActive,
        ]);

        $currentVersion = (int)($this->settings->get('ai_pricing_version', '1') ?? '1');
        $nextVersion = max(1, $currentVersion + 1);
        $this->settings->set('ai_pricing_version', (string)$nextVersion);

        Session::flash('success', 'Pricing opgeslagen.');
        $this->redirect('/admin/ai/settings');
    }

    public function updateAiBudgetSettings(): void {
        $this->requireAdmin();
        $this->verifyCsrf('/admin/ai/settings');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/ai/settings');
        }

        $billingEnabled = !empty($_POST['ai_billing_enabled']) ? '1' : '0';
        $budgetMode = trim((string)($_POST['ai_budget_mode'] ?? 'monthly_per_user'));
        $budgetValueRaw = trim((string)($_POST['ai_monthly_user_budget_eur'] ?? ''));
        $resetDay = (int)($_POST['ai_budget_reset_day'] ?? 1);
        $rateLimit = (int)($_POST['ai_rate_limit_per_minute'] ?? 10);
        $maxSessions = (int)($_POST['ai_max_sessions_per_user'] ?? 50);

        if (!in_array($budgetMode, self::BUDGET_MODES, true)) {
            Session::flash('error', 'Ongeldige budgetmodus.');
            $this->redirect('/admin/ai/settings');
        }

        if ($resetDay < 1 || $resetDay > 28) {
            Session::flash('error', 'Budget reset dag moet tussen 1 en 28 liggen.');
            $this->redirect('/admin/ai/settings');
        }

        if ($rateLimit < 1 || $maxSessions < 1) {
            Session::flash('error', 'Rate limit en max sessies moeten groter dan 0 zijn.');
            $this->redirect('/admin/ai/settings');
        }

        $budgetValue = null;
        if ($budgetValueRaw !== '') {
            $parsed = $this->parseNonNegativeFloat($budgetValueRaw);
            if ($parsed === null) {
                Session::flash('error', 'Maandbudget moet een geldig bedrag zijn.');
                $this->redirect('/admin/ai/settings');
            }
            $budgetValue = number_format($parsed, 2, '.', '');
        }

        $this->settings->setMany([
            'ai_billing_enabled' => $billingEnabled,
            'ai_budget_mode' => $budgetMode,
            'ai_monthly_user_budget_eur' => $budgetValue,
            'ai_budget_reset_day' => (string)$resetDay,
            'ai_rate_limit_per_minute' => (string)$rateLimit,
            'ai_max_sessions_per_user' => (string)$maxSessions,
        ]);

        Session::flash('success', 'Budgetinstellingen bijgewerkt.');
        $this->redirect('/admin/ai/settings');
    }

    public function updateAiRetrievalSettings(): void {
        $this->requireAdmin();
        $this->verifyCsrf('/admin/ai/settings');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/ai/settings');
        }

        $retrievalEnabled = !empty($_POST['ai_retrieval_enabled']) ? '1' : '0';
        $youtubeEnabled = !empty($_POST['ai_retrieval_youtube_enabled']) ? '1' : '0';
        $maxCandidates = (int)($_POST['ai_retrieval_max_candidates'] ?? 10);
        $minYoutubeSources = (int)($_POST['ai_retrieval_min_youtube_sources'] ?? 2);
        $internalLimit = (int)($_POST['ai_retrieval_internal_limit'] ?? 2);
        $cookiesPath = trim((string)($_POST['ai_ytdlp_cookies_path'] ?? ''));

        if ($maxCandidates < 1 || $maxCandidates > 20) {
            Session::flash('error', 'Max kandidaten moet tussen 1 en 20 liggen.');
            $this->redirect('/admin/ai/settings');
        }

        if ($minYoutubeSources < 1 || $minYoutubeSources > 3) {
            Session::flash('error', 'Min. YouTube bronnen moet tussen 1 en 3 liggen.');
            $this->redirect('/admin/ai/settings');
        }

        if ($internalLimit < 0 || $internalLimit > 3) {
            Session::flash('error', 'Interne bronlimiet moet tussen 0 en 3 liggen.');
            $this->redirect('/admin/ai/settings');
        }

        if ($cookiesPath !== '' && !$this->isAbsolutePath($cookiesPath)) {
            Session::flash('error', 'Het cookies.txt-pad moet een absoluut serverpad zijn.');
            $this->redirect('/admin/ai/settings');
        }

        $this->settings->setMany([
            'ai_retrieval_enabled' => $retrievalEnabled,
            'ai_retrieval_youtube_enabled' => $youtubeEnabled,
            'ai_retrieval_max_candidates' => (string)$maxCandidates,
            'ai_retrieval_min_youtube_sources' => (string)$minYoutubeSources,
            'ai_retrieval_internal_limit' => (string)$internalLimit,
            'ai_ytdlp_cookies_path' => $cookiesPath,
        ]);

        Session::flash('success', 'Retrievalinstellingen opgeslagen.');
        $this->redirect('/admin/ai/settings');
    }

    public function updateLiveVoiceSettings(): void {
        $this->requireAdmin();
        $this->verifyCsrf('/admin/ai/settings');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/ai/settings');
        }

        $liveVoiceEnabled = !empty($_POST['live_voice_enabled']) ? '1' : '0';
        $this->settings->set('live_voice_enabled', $liveVoiceEnabled);

        Session::flash('success', 'Spraakinstelling opgeslagen.');
        $this->redirect('/admin/ai/settings');
    }

    public function usageReport(): void {
        $this->requireAdmin();

        $summary = $this->getUsageSummary();
        $qualitySummary = $this->getQualitySummary();

        $usagePerModel = $this->pdo->query(
            "SELECT
                model_id,
                COUNT(*) AS calls,
                COALESCE(SUM(input_tokens), 0) AS input_tokens,
                COALESCE(SUM(output_tokens), 0) AS output_tokens,
                COALESCE(SUM(total_tokens), 0) AS total_tokens,
                COALESCE(SUM(supplier_cost_usd), 0) AS supplier_cost_usd,
                COALESCE(SUM(billable_cost_eur), 0) AS billable_cost_eur
             FROM ai_usage_events
             GROUP BY model_id
             ORDER BY calls DESC, model_id ASC"
        )->fetchAll();

        $usagePerUser = $this->pdo->query(
            "SELECT
                e.user_id,
                COALESCE(u.name, 'Onbekend') AS user_name,
                COUNT(*) AS calls,
                COALESCE(SUM(e.total_tokens), 0) AS total_tokens,
                COALESCE(SUM(e.billable_cost_eur), 0) AS billable_cost_eur
             FROM ai_usage_events e
             LEFT JOIN users u ON u.id = e.user_id
             GROUP BY e.user_id
             ORDER BY calls DESC, user_name ASC"
        )->fetchAll();

        $qualityPerType = $this->getQualityEventsByType();
        $recentQualityEvents = $this->getRecentQualityEvents();

        View::render('admin/ai_usage', [
            'pageTitle' => 'AI Usage Rapport - Admin',
            'summary' => $summary,
            'usagePerModel' => $usagePerModel,
            'usagePerUser' => $usagePerUser,
            'qualitySummary' => $qualitySummary,
            'qualityPerType' => $qualityPerType,
            'recentQualityEvents' => $recentQualityEvents,
        ]);
    }

    private function saveEncryptedApiKey(string $settingKey, string $inputField, string $successMessage): void {
        $this->requireAdmin();
        $this->verifyCsrf('/admin/ai/settings');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/ai/settings');
        }

        if (!Config::hasEncryptionKey()) {
            Session::flash('error', 'Encryptiesleutel ontbreekt. Configureer data/config.php eerst.');
            $this->redirect('/admin/ai/settings');
        }

        $apiKey = trim((string)($_POST[$inputField] ?? ''));
        if ($apiKey === '') {
            Session::flash('error', 'API key mag niet leeg zijn.');
            $this->redirect('/admin/ai/settings');
        }

        try {
            $encrypted = Encryption::encrypt($apiKey);
            $this->settings->set($settingKey, $encrypted);
            Session::flash('success', $successMessage);
        } catch (Throwable $e) {
            error_log('[AiAdminController] API key save failed: ' . $e->getMessage());

            $message = 'API key kon niet worden opgeslagen.';
            $errorText = strtolower($e->getMessage());
            if (str_contains($errorText, 'readonly')) {
                $message = 'API key kon niet worden opgeslagen: database is alleen-lezen. Controleer bestandsrechten op data/database.sqlite en data/.';
            } elseif (str_contains($errorText, 'updated_at')) {
                $message = 'API key kon niet worden opgeslagen door een onvolledige database-migratie. Draai scripts/init_db.php opnieuw.';
            }
            Session::flash('error', $message);
        }

        $this->redirect('/admin/ai/settings');
    }

    private function deleteEncryptedApiKey(string $settingKey, string $successMessage): void {
        $this->requireAdmin();
        $this->verifyCsrf('/admin/ai/settings');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/ai/settings');
        }

        $this->settings->set($settingKey, null);
        Session::flash('success', $successMessage);
        $this->redirect('/admin/ai/settings');
    }

    private function fetchModelsWithPricing(): array {
        return $this->pdo->query(
            "SELECT
                m.id,
                m.model_id,
                m.label,
                m.enabled,
                m.supports_vision,
                m.sort_order,
                m.created_at,
                m.updated_at,
                p.currency,
                p.input_price_per_mtoken,
                p.output_price_per_mtoken,
                p.request_flat_price,
                p.min_request_price,
                p.is_active,
                CASE
                    WHEN m.enabled = 1
                     AND TRIM(m.model_id) <> ''
                     AND TRIM(m.label) <> ''
                     AND p.id IS NOT NULL
                     AND p.is_active = 1
                    THEN 1
                    ELSE 0
                END AS is_publishable
             FROM ai_models m
             LEFT JOIN ai_model_pricing p ON p.model_id = m.model_id
             ORDER BY m.sort_order ASC, m.id ASC"
        )->fetchAll();
    }

    private function getSettingsWithDefaults(): array {
        $defaults = [
            'ai_access_mode' => 'off',
            'ai_billing_enabled' => '1',
            'ai_pricing_version' => '1',
            'ai_budget_mode' => 'monthly_per_user',
            'ai_monthly_user_budget_eur' => null,
            'ai_budget_reset_day' => '1',
            'ai_rate_limit_per_minute' => '10',
            'ai_max_sessions_per_user' => '50',
            'openrouter_api_key_enc' => null,
            'openrouter_management_api_key_enc' => null,
            'youtube_api_key_enc' => null,
            'ai_retrieval_enabled' => '1',
            'ai_retrieval_youtube_enabled' => '1',
            'ai_retrieval_max_candidates' => '10',
            'ai_retrieval_min_youtube_sources' => '2',
            'ai_retrieval_internal_limit' => '2',
            'ai_ytdlp_cookies_path' => '',
            'live_voice_enabled' => '1',
        ];

        $stored = $this->settings->getMany(array_keys($defaults));

        $settings = $defaults;
        foreach ($stored as $key => $value) {
            $settings[$key] = $value;
        }

        return $settings;
    }

    private function getMaskedKey(string $settingKey): ?string {
        $encrypted = $this->settings->get($settingKey);
        if ($encrypted === null || trim($encrypted) === '') {
            return null;
        }

        if (!Config::hasEncryptionKey()) {
            return 'ingesteld (sleutel ontbreekt)';
        }

        try {
            $plaintext = Encryption::decrypt($encrypted);
            return $this->maskSecret($plaintext);
        } catch (Throwable $e) {
            return 'ingesteld (niet leesbaar)';
        }
    }

    private function maskSecret(string $secret): string {
        $length = strlen($secret);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        $prefix = substr($secret, 0, 6);
        $suffix = substr($secret, -4);
        return $prefix . str_repeat('*', max(4, $length - 10)) . $suffix;
    }

    private function isAbsolutePath(string $path): bool {
        return $path !== '' && ($path[0] ?? '') === '/';
    }

    private function getUsageSummary(): array {
        $summary = $this->pdo->query(
            "SELECT
                COUNT(*) AS total_calls,
                COALESCE(SUM(total_tokens), 0) AS total_tokens,
                COALESCE(SUM(supplier_cost_usd), 0) AS total_supplier_cost_usd,
                COALESCE(SUM(billable_cost_eur), 0) AS total_billable_cost_eur
             FROM ai_usage_events"
        )->fetch();

        if (!$summary) {
            return [
                'total_calls' => 0,
                'total_tokens' => 0,
                'total_supplier_cost_usd' => 0,
                'total_billable_cost_eur' => 0,
                'margin_eur_minus_usd' => 0,
            ];
        }

        $supplier = (float)$summary['total_supplier_cost_usd'];
        $billable = (float)$summary['total_billable_cost_eur'];

        return [
            'total_calls' => (int)$summary['total_calls'],
            'total_tokens' => (int)$summary['total_tokens'],
            'total_supplier_cost_usd' => $supplier,
            'total_billable_cost_eur' => $billable,
            'margin_eur_minus_usd' => $billable - $supplier,
        ];
    }

    private function getQualitySummary(): array {
        if (!$this->hasTable('ai_quality_events')) {
            return [
                'total_events' => 0,
                'blocker_events' => 0,
                'recovery_offered' => 0,
                'recovery_selected' => 0,
                'cookie_recoveries' => 0,
            ];
        }

        $summary = $this->pdo->query(
            "SELECT
                COUNT(*) AS total_events,
                COALESCE(SUM(CASE WHEN event_type IN ('video_preflight_failed', 'source_evidence_too_low', 'source_facts_failed') THEN 1 ELSE 0 END), 0) AS blocker_events,
                COALESCE(SUM(CASE WHEN event_type = 'recovery_offered' AND status = 'offered' THEN 1 ELSE 0 END), 0) AS recovery_offered,
                COALESCE(SUM(CASE WHEN event_type = 'recovery_choice_selected' THEN 1 ELSE 0 END), 0) AS recovery_selected,
                COALESCE(SUM(CASE WHEN event_type = 'frame_download_result' AND status = 'cookie_recovered' THEN 1 ELSE 0 END), 0) AS cookie_recoveries
             FROM ai_quality_events"
        )->fetch();

        if (!$summary) {
            return [
                'total_events' => 0,
                'blocker_events' => 0,
                'recovery_offered' => 0,
                'recovery_selected' => 0,
                'cookie_recoveries' => 0,
            ];
        }

        return [
            'total_events' => (int)($summary['total_events'] ?? 0),
            'blocker_events' => (int)($summary['blocker_events'] ?? 0),
            'recovery_offered' => (int)($summary['recovery_offered'] ?? 0),
            'recovery_selected' => (int)($summary['recovery_selected'] ?? 0),
            'cookie_recoveries' => (int)($summary['cookie_recoveries'] ?? 0),
        ];
    }

    private function getQualityEventsByType(): array {
        if (!$this->hasTable('ai_quality_events')) {
            return [];
        }

        return $this->pdo->query(
            "SELECT
                event_type,
                status,
                COUNT(*) AS event_count
             FROM ai_quality_events
             GROUP BY event_type, status
             ORDER BY event_count DESC, event_type ASC, status ASC"
        )->fetchAll();
    }

    private function getRecentQualityEvents(int $limit = 15): array {
        if (!$this->hasTable('ai_quality_events')) {
            return [];
        }

        $limit = max(1, min(50, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT
                q.id,
                q.user_id,
                COALESCE(u.name, 'Onbekend') AS user_name,
                q.event_type,
                q.status,
                q.external_id,
                q.payload_json,
                q.created_at
             FROM ai_quality_events q
             LEFT JOIN users u ON u.id = q.user_id
             ORDER BY q.created_at DESC, q.id DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        return array_map(function (array $row): array {
            return $row + [
                'payload_summary' => $this->summarizeQualityPayload((string)($row['event_type'] ?? ''), (string)($row['status'] ?? ''), (string)($row['payload_json'] ?? '')),
            ];
        }, $rows);
    }

    private function summarizeQualityPayload(string $eventType, string $status, string $payloadJson): string {
        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return '';
        }

        if ($eventType === 'recovery_offered') {
            $count = max(0, (int)($payload['recovery_count'] ?? 0));
            $triggerEvent = trim((string)($payload['trigger_event'] ?? ''));
            return $count > 0
                ? sprintf('%d alternatieven na %s', $count, $triggerEvent !== '' ? $triggerEvent : 'blokkade')
                : 'Geen alternatieven beschikbaar';
        }

        if ($eventType === 'recovery_choice_selected') {
            $triggerCode = trim((string)($payload['recovery_trigger_code'] ?? ''));
            return $triggerCode !== ''
                ? 'Coach koos recovery-optie na ' . $triggerCode
                : 'Coach koos recovery-optie';
        }

        if ($eventType === 'frame_download_result') {
            if ($status === 'cookie_recovered') {
                return 'Anonieme download faalde, cookies-retry slaagde';
            }
            if ($status === 'cookies_invalid') {
                return 'Cookies-pad ongeldig of onleesbaar';
            }
            if ($status === 'cookie_failed') {
                return 'Cookies-retry geprobeerd maar download bleef falen';
            }
        }

        if ($eventType === 'preflight_result') {
            $mode = trim((string)($payload['availability_mode'] ?? ''));
            $label = trim((string)($payload['technical_label'] ?? ''));
            $parts = array_values(array_filter([$mode, $label], static fn(string $part): bool => $part !== ''));
            return implode(' | ', $parts);
        }

        if (in_array($eventType, ['video_preflight_failed', 'source_evidence_too_low', 'source_facts_failed'], true)) {
            $reasons = is_array($payload['blocking_reasons'] ?? null) ? $payload['blocking_reasons'] : [];
            if (!empty($reasons)) {
                return implode(' ', array_slice($reasons, 0, 2));
            }

            $error = trim((string)($payload['source_facts_error'] ?? ''));
            return $error;
        }

        return '';
    }

    private function hasTable(string $tableName): bool {
        try {
            $stmt = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :name LIMIT 1");
            $stmt->execute([':name' => $tableName]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    private function parseNonNegativeFloat(mixed $value): ?float {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_scalar($value) || !is_numeric((string)$value)) {
            return null;
        }

        $parsed = (float)$value;
        if ($parsed < 0) {
            return null;
        }

        return $parsed;
    }

    private function inferModelSupportsVision(string $modelId): int {
        $id = strtolower(trim($modelId));
        if ($id === '') {
            return 0;
        }

        $textOnlyHints = [
            'mistral-large',
            'mixtral',
            'gpt-3.5',
            'instruct',
        ];
        foreach ($textOnlyHints as $hint) {
            if (str_contains($id, $hint)) {
                return 0;
            }
        }

        $visionHints = [
            'gpt-4o',
            'gpt-4.1',
            'gpt-5',
            'gemini',
            'claude-3',
            'claude-4',
            'llama-3.2-vision',
            'pixtral',
            'qwen-vl',
            'vision',
            'multimodal',
        ];
        foreach ($visionHints as $hint) {
            if (str_contains($id, $hint)) {
                return 1;
            }
        }

        return 0;
    }

    private function modelExists(string $modelId): bool {
        $stmt = $this->pdo->prepare('SELECT 1 FROM ai_models WHERE model_id = :model_id LIMIT 1');
        $stmt->execute([':model_id' => $modelId]);
        return (bool)$stmt->fetchColumn();
    }

}
