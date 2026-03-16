<?php
declare(strict_types=1);

class AiUsageService {
    public function __construct(private PDO $pdo) {}

    public function createEvent(
        int $userId,
        ?int $teamId,
        ?int $sessionId,
        ?int $exerciseId,
        string $modelId,
        string $status,
        ?string $errorCode
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO ai_usage_events (
                user_id, team_id, session_id, exercise_id, provider, generation_id,
                model_id, status, input_tokens, output_tokens, total_tokens,
                supplier_cost_usd, billable_cost_eur, pricing_version,
                pricing_snapshot_json, error_code, created_at
             ) VALUES (
                :user_id, :team_id, :session_id, :exercise_id, :provider, :generation_id,
                :model_id, :status, 0, 0, 0,
                0, 0, NULL,
                NULL, :error_code, CURRENT_TIMESTAMP
             )'
        );

        $stmt->execute([
            ':user_id' => $userId,
            ':team_id' => $teamId,
            ':session_id' => $sessionId,
            ':exercise_id' => $exerciseId,
            ':provider' => 'openrouter',
            ':generation_id' => null,
            ':model_id' => $modelId,
            ':status' => $status,
            ':error_code' => $errorCode,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function updateEvent(int $usageEventId, array $values): void {
        $stmt = $this->pdo->prepare(
            'UPDATE ai_usage_events
             SET
                status = :status,
                generation_id = :generation_id,
                input_tokens = :input_tokens,
                output_tokens = :output_tokens,
                total_tokens = :total_tokens,
                supplier_cost_usd = :supplier_cost_usd,
                billable_cost_eur = :billable_cost_eur,
                pricing_version = :pricing_version,
                pricing_snapshot_json = :pricing_snapshot_json,
                error_code = :error_code
             WHERE id = :id'
        );

        $stmt->execute([
            ':id' => $usageEventId,
            ':status' => $values['status'] ?? 'failed',
            ':generation_id' => $values['generation_id'] ?? null,
            ':input_tokens' => (int)($values['input_tokens'] ?? 0),
            ':output_tokens' => (int)($values['output_tokens'] ?? 0),
            ':total_tokens' => (int)($values['total_tokens'] ?? 0),
            ':supplier_cost_usd' => (float)($values['supplier_cost_usd'] ?? 0),
            ':billable_cost_eur' => (float)($values['billable_cost_eur'] ?? 0),
            ':pricing_version' => $values['pricing_version'] ?? null,
            ':pricing_snapshot_json' => $values['pricing_snapshot_json'] ?? null,
            ':error_code' => $values['error_code'] ?? null,
        ]);
    }

    public function failEvent(int $usageEventId, string $errorCode): void {
        $this->updateEvent($usageEventId, [
            'status' => 'failed',
            'error_code' => $errorCode,
            'generation_id' => null,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'total_tokens' => 0,
            'supplier_cost_usd' => 0,
            'billable_cost_eur' => 0,
            'pricing_version' => null,
            'pricing_snapshot_json' => null,
        ]);
    }

    public function logBlocked(int $userId, ?int $teamId, string $modelId, string $errorCode): void {
        $this->createEvent($userId, $teamId, null, null, $modelId, 'blocked', $errorCode);
    }

    public function buildSummary(int $userId, array $settings): array {
        $billingEnabled = (string)($settings['ai_billing_enabled'] ?? '1') === '1';
        $budgetMode = (string)($settings['ai_budget_mode'] ?? 'monthly_per_user');
        $budgetResetDay = max(1, min(28, (int)($settings['ai_budget_reset_day'] ?? 1)));

        [$periodStart, $periodEnd] = $this->calculateBudgetPeriod($budgetResetDay);

        $stmt = $this->pdo->prepare(
            'SELECT
                COALESCE(SUM(total_tokens), 0) AS total_tokens,
                COALESCE(SUM(billable_cost_eur), 0) AS billable_cost_eur
             FROM ai_usage_events
             WHERE user_id = :user_id
               AND status = :status
               AND created_at >= :period_start
               AND created_at < :period_end'
        );

        $stmt->execute([
            ':user_id' => $userId,
            ':status' => 'success',
            ':period_start' => $periodStart->format('Y-m-d H:i:s'),
            ':period_end' => $periodEnd->format('Y-m-d H:i:s'),
        ]);

        $row = $stmt->fetch() ?: ['total_tokens' => 0, 'billable_cost_eur' => 0];

        $budgetValue = null;
        if ($billingEnabled && $budgetMode === 'monthly_per_user') {
            $rawBudget = $settings['ai_monthly_user_budget_eur'] ?? null;
            if ($rawBudget !== null && trim((string)$rawBudget) !== '' && is_numeric((string)$rawBudget)) {
                $budgetValue = round((float)$rawBudget, 2);
            }
        }

        $billable = round((float)$row['billable_cost_eur'], 6);
        $remaining = null;
        if ($budgetValue !== null) {
            $remaining = round(max(0.0, $budgetValue - $billable), 6);
        }

        return [
            'billing_enabled' => $billingEnabled,
            'budget_mode' => $budgetMode,
            'period_start' => $periodStart->format('Y-m-d H:i:s'),
            'period_end' => $periodEnd->format('Y-m-d H:i:s'),
            'total_tokens' => (int)$row['total_tokens'],
            'billable_cost_eur' => $billable,
            'budget_eur' => $budgetValue,
            'remaining_budget_eur' => $remaining,
        ];
    }

    public function cleanupStaleInProgress(int $userId, int $staleMinutes): void {
        $minutes = max(1, $staleMinutes);
        $threshold = sprintf('-%d minutes', $minutes);

        $stmt = $this->pdo->prepare(
            "UPDATE ai_usage_events
             SET status = 'failed',
                 error_code = COALESCE(error_code, 'timeout_in_progress')
             WHERE user_id = :user_id
               AND status = 'in_progress'
               AND created_at <= datetime('now', :threshold)"
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':threshold' => $threshold,
        ]);
    }

    private function calculateBudgetPeriod(int $resetDay): array {
        $now = new DateTimeImmutable('now');
        $currentDay = (int)$now->format('j');

        if ($currentDay >= $resetDay) {
            $periodStart = $now->setDate((int)$now->format('Y'), (int)$now->format('m'), $resetDay)->setTime(0, 0, 0);
        } else {
            $prevMonth = $now->modify('first day of last month');
            $periodStart = $prevMonth->setDate((int)$prevMonth->format('Y'), (int)$prevMonth->format('m'), $resetDay)->setTime(0, 0, 0);
        }

        $periodEnd = $periodStart->modify('+1 month');

        return [$periodStart, $periodEnd];
    }
}
