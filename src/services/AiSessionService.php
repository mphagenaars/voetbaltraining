<?php
declare(strict_types=1);

/**
 * Manages AI chat sessions and messages in the database.
 */
class AiSessionService {
    public function __construct(private PDO $pdo) {}

    public function resolveSession(int $sessionId, int $userId, ?int $teamId, int $exerciseId, string $firstMessage, int $maxSessions = 50): int {
        if ($sessionId > 0) {
            $session = $this->getSessionForUser($sessionId, $userId, $teamId);
            if ($session !== null) {
                $stmt = $this->pdo->prepare('UPDATE ai_chat_sessions SET updated_at = CURRENT_TIMESTAMP WHERE id = :id');
                $stmt->execute([':id' => $sessionId]);
                return $sessionId;
            }
        }

        $maxSessions = max(1, $maxSessions);

        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM ai_chat_sessions WHERE user_id = :user_id');
        $countStmt->execute([':user_id' => $userId]);
        $sessionCount = (int)$countStmt->fetchColumn();

        $title = function_exists('mb_substr')
            ? (string)mb_substr($firstMessage, 0, 120, 'UTF-8')
            : (string)substr($firstMessage, 0, 120);
        if ($title === '') {
            $title = 'Nieuwe AI sessie';
        }

        $this->pdo->beginTransaction();
        try {
            if ($sessionCount >= $maxSessions) {
                $this->deleteOldestSessions($userId, $sessionCount - $maxSessions + 1);
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO ai_chat_sessions (user_id, team_id, exercise_id, title, created_at, updated_at)
                 VALUES (:user_id, :team_id, :exercise_id, :title, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
            );
            $stmt->execute([
                ':user_id' => $userId,
                ':team_id' => $teamId,
                ':exercise_id' => $exerciseId > 0 ? $exerciseId : null,
                ':title' => $title,
            ]);

            $newId = (int)$this->pdo->lastInsertId();
            $this->pdo->commit();
            return $newId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function deleteOldestSessions(int $userId, int $count): void {
        if ($count <= 0) {
            return;
        }

        $selectStmt = $this->pdo->prepare(
            'SELECT id
             FROM ai_chat_sessions
             WHERE user_id = :user_id
             ORDER BY updated_at ASC, id ASC
             LIMIT :limit'
        );
        $selectStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $selectStmt->bindValue(':limit', $count, PDO::PARAM_INT);
        $selectStmt->execute();
        $ids = array_map(static fn(array $row): int => (int)$row['id'], $selectStmt->fetchAll());

        if (empty($ids)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $deleteStmt = $this->pdo->prepare("DELETE FROM ai_chat_sessions WHERE id IN ($placeholders)");
        $deleteStmt->execute($ids);
    }

    public function getSessionForUser(int $sessionId, int $userId, ?int $teamId): ?array {
        $sql = 'SELECT id, user_id, team_id, exercise_id, title, created_at, updated_at
                FROM ai_chat_sessions
                WHERE id = :id AND user_id = :user_id';

        $params = [
            ':id' => $sessionId,
            ':user_id' => $userId,
        ];

        if ($teamId !== null) {
            $sql .= ' AND (team_id = :team_id OR team_id IS NULL)';
            $params[':team_id'] = $teamId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $session = $stmt->fetch();

        return $session ?: null;
    }

    public function insertChatMessage(int $sessionId, string $role, string $content, ?string $modelId, ?string $metadataJson): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO ai_chat_messages (session_id, role, content, model_id, metadata_json, created_at)
             VALUES (:session_id, :role, :content, :model_id, :metadata_json, CURRENT_TIMESTAMP)'
        );

        $stmt->execute([
            ':session_id' => $sessionId,
            ':role' => $role,
            ':content' => $content,
            ':model_id' => $modelId,
            ':metadata_json' => $metadataJson,
        ]);

        $updateStmt = $this->pdo->prepare('UPDATE ai_chat_sessions SET updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $updateStmt->execute([':id' => $sessionId]);
    }

    public function getSessionMessagesForPrompt(int $sessionId): array {
        $stmt = $this->pdo->prepare(
            'SELECT role, content, metadata_json
             FROM ai_chat_messages
             WHERE session_id = :session_id
             ORDER BY created_at ASC, id ASC
             LIMIT 50'
        );
        $stmt->execute([':session_id' => $sessionId]);

        $messages = [];
        foreach ($stmt->fetchAll() as $row) {
            $role = (string)$row['role'];
            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }

            $content = (string)$row['content'];

            // Enrich assistant search-result messages with video summaries
            // so follow-up LLM calls know what was previously shown
            if ($role === 'assistant' && !empty($row['metadata_json'])) {
                $meta = json_decode((string)$row['metadata_json'], true);
                if (is_array($meta['video_choices'] ?? null) && !empty($meta['video_choices'])) {
                    $lines = [];
                    foreach ($meta['video_choices'] as $vc) {
                        $title = (string)($vc['title'] ?? '');
                        $channel = (string)($vc['channel'] ?? '');
                        $dur = (string)($vc['duration_formatted'] ?? '');
                        if ($title !== '') {
                            $lines[] = '- ' . $title . ($channel !== '' ? ' (' . $channel . ($dur !== '' ? ', ' . $dur : '') . ')' : '');
                        }
                    }
                    if (!empty($lines)) {
                        $content .= "\nGetoonde video's:\n" . implode("\n", $lines);
                    }
                }
            }

            $messages[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        return $messages;
    }
}
