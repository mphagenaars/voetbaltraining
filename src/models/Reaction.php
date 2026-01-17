<?php
declare(strict_types=1);

class Reaction extends Model {
    protected string $table = 'exercise_reactions';

    public function toggle(int $exerciseId, int $userId, string $type): string {
        // Check if exists
        $stmt = $this->pdo->prepare("
            SELECT reaction_type FROM {$this->table} 
            WHERE exercise_id = :exercise_id AND user_id = :user_id
        ");
        $stmt->execute([':exercise_id' => $exerciseId, ':user_id' => $userId]);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            // If same type, remove it (toggle off)
            // If different type, update it (change vote)
            if ($existing === $type) {
                $del = $this->pdo->prepare("DELETE FROM {$this->table} WHERE exercise_id = :aid AND user_id = :uid");
                $del->execute([':aid' => $exerciseId, ':uid' => $userId]);
                return 'removed';
            } else {
                $upd = $this->pdo->prepare("UPDATE {$this->table} SET reaction_type = :type WHERE exercise_id = :aid AND user_id = :uid");
                $upd->execute([':type' => $type, ':aid' => $exerciseId, ':uid' => $userId]);
                return 'updated';
            }
        } else {
            // Insert
            $ins = $this->pdo->prepare("INSERT INTO {$this->table} (exercise_id, user_id, reaction_type) VALUES (:aid, :uid, :type)");
            $ins->execute([':aid' => $exerciseId, ':uid' => $userId, ':type' => $type]);
            return 'added';
        }
    }

    public function getCounts(int $exerciseId): array {
        $stmt = $this->pdo->prepare("
            SELECT reaction_type, COUNT(*) as count 
            FROM {$this->table} 
            WHERE exercise_id = :id 
            GROUP BY reaction_type
        ");
        $stmt->execute([':id' => $exerciseId]);
        
        $counts = [];
        while ($row = $stmt->fetch()) {
            $counts[$row['reaction_type']] = (int)$row['count'];
        }
        return $counts;
    }

    public function getUserReaction(int $exerciseId, int $userId): ?string {
        $stmt = $this->pdo->prepare("
            SELECT reaction_type FROM {$this->table} 
            WHERE exercise_id = :id AND user_id = :uid
        ");
        $stmt->execute([':id' => $exerciseId, ':uid' => $userId]);
        $res = $stmt->fetchColumn();
        return $res ?: null;
    }
}
