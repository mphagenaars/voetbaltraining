<?php
declare(strict_types=1);

class AiQualityEventService
{
    private bool $schemaChecked = false;

    public function __construct(private PDO $pdo)
    {
    }

    public function logEvent(
        ?int $userId,
        ?int $teamId,
        ?int $sessionId,
        string $eventType,
        string $status,
        array $payload = [],
        ?string $externalId = null
    ): int {
        $this->ensureSchema();

        $stmt = $this->pdo->prepare(
            'INSERT INTO ai_quality_events (
                user_id,
                team_id,
                session_id,
                event_type,
                status,
                external_id,
                payload_json,
                created_at
             ) VALUES (
                :user_id,
                :team_id,
                :session_id,
                :event_type,
                :status,
                :external_id,
                :payload_json,
                CURRENT_TIMESTAMP
             )'
        );

        $stmt->execute([
            ':user_id' => $userId,
            ':team_id' => $teamId,
            ':session_id' => $sessionId,
            ':event_type' => trim($eventType) !== '' ? trim($eventType) : 'unknown',
            ':status' => trim($status) !== '' ? trim($status) : 'unknown',
            ':external_id' => $externalId !== null && trim($externalId) !== '' ? trim($externalId) : null,
            ':payload_json' => $this->encodePayload($payload),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    private function ensureSchema(): void
    {
        if ($this->schemaChecked) {
            return;
        }

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS ai_quality_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                team_id INTEGER NULL,
                session_id INTEGER NULL,
                event_type TEXT NOT NULL,
                status TEXT NOT NULL,
                external_id TEXT NULL,
                payload_json TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_ai_quality_events_user_created ON ai_quality_events (user_id, created_at)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_ai_quality_events_team_created ON ai_quality_events (team_id, created_at)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_ai_quality_events_session_created ON ai_quality_events (session_id, created_at)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_ai_quality_events_type_created ON ai_quality_events (event_type, created_at)');

        $this->schemaChecked = true;
    }

    private function encodePayload(array $payload): ?string
    {
        if ($payload === []) {
            return null;
        }

        $encoded = json_encode($payload, $this->jsonFlags());
        if ($encoded === false) {
            return '{"error":"payload_encode_failed"}';
        }

        return $encoded;
    }

    private function jsonFlags(): int
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        return $flags;
    }
}
