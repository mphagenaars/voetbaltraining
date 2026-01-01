<?php
declare(strict_types=1);

class Tag extends Model {
    protected string $table = 'tags';

    public function create(int $teamId, string $name): int {
        // Check if tag already exists for this team
        $existing = $this->findByName($teamId, $name);
        if ($existing) {
            return $existing['id'];
        }

        $stmt = $this->pdo->prepare("INSERT INTO tags (team_id, name) VALUES (:team_id, :name)");
        $stmt->execute([':team_id' => $teamId, ':name' => $name]);
        return (int)$this->pdo->lastInsertId();
    }

    public function findByName(int $teamId, string $name): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM tags WHERE team_id = :team_id AND name = :name");
        $stmt->execute([':team_id' => $teamId, ':name' => $name]);
        $tag = $stmt->fetch();
        return $tag ?: null;
    }

    public function getTagsForExercise(int $exerciseId): array {
        $stmt = $this->pdo->prepare("
            SELECT t.* 
            FROM tags t
            JOIN exercise_tags et ON t.id = et.tag_id
            WHERE et.exercise_id = :exercise_id
            ORDER BY t.name ASC
        ");
        $stmt->execute([':exercise_id' => $exerciseId]);
        return $stmt->fetchAll();
    }

    public function addTagToExercise(int $exerciseId, int $tagId): void {
        // Ignore if already exists
        $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO exercise_tags (exercise_id, tag_id) VALUES (:exercise_id, :tag_id)");
        $stmt->execute([':exercise_id' => $exerciseId, ':tag_id' => $tagId]);
    }

    public function removeAllTagsFromExercise(int $exerciseId): void {
        $stmt = $this->pdo->prepare("DELETE FROM exercise_tags WHERE exercise_id = :exercise_id");
        $stmt->execute([':exercise_id' => $exerciseId]);
    }
}
