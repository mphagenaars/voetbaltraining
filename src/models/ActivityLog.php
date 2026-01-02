<?php
declare(strict_types=1);

class ActivityLog extends Model {
    protected string $table = 'activity_logs';

    public function log(int $userId, string $action, ?int $entityId = null, ?string $details = null): void {
        $stmt = $this->pdo->prepare("INSERT INTO activity_logs (user_id, action, entity_id, details) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $entityId, $details]);
    }

    public function getRecent(int $limit = 20): array {
        $stmt = $this->pdo->prepare("
            SELECT a.*, u.name as user_name 
            FROM activity_logs a 
            LEFT JOIN users u ON a.user_id = u.id 
            ORDER BY a.created_at DESC LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public function getPopularExercises(int $limit = 5): array {
        $stmt = $this->pdo->prepare("
            SELECT details, COUNT(*) as count 
            FROM activity_logs 
            WHERE action = 'view_exercise' 
            GROUP BY entity_id 
            ORDER BY count DESC LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
}
