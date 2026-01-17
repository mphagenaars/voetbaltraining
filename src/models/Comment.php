<?php
declare(strict_types=1);

class Comment extends Model {
    protected string $table = 'exercise_comments';

    public function create(int $exerciseId, int $userId, string $text): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->table} (exercise_id, user_id, comment, created_at) 
            VALUES (:exercise_id, :user_id, :comment, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            ':exercise_id' => $exerciseId,
            ':user_id' => $userId,
            ':comment' => $text
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getForExercise(int $exerciseId): array {
        $stmt = $this->pdo->prepare("
            SELECT c.*, u.name as user_name 
            FROM {$this->table} c
            JOIN users u ON c.user_id = u.id
            WHERE c.exercise_id = :exercise_id
            ORDER BY c.created_at ASC
        ");
        $stmt->execute([':exercise_id' => $exerciseId]);
        return $stmt->fetchAll();
    }
}
