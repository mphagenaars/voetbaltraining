<?php
declare(strict_types=1);

class Exercise {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function create(int $teamId, string $title, string $description, ?int $players, ?int $duration, ?string $imagePath = null, ?string $drawingData = null): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO exercises (team_id, title, description, players, duration, image_path, drawing_data) 
            VALUES (:team_id, :title, :description, :players, :duration, :image_path, :drawing_data)
        ");
        $stmt->execute([
            ':team_id' => $teamId,
            ':title' => $title,
            ':description' => $description,
            ':players' => $players,
            ':duration' => $duration,
            ':image_path' => $imagePath,
            ':drawing_data' => $drawingData
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getAllForTeam(int $teamId): array {
        $stmt = $this->pdo->prepare("SELECT * FROM exercises WHERE team_id = :team_id ORDER BY created_at DESC");
        $stmt->execute([':team_id' => $teamId]);
        return $stmt->fetchAll();
    }

    public function search(int $teamId, ?string $query = null, ?int $tagId = null): array {
        $sql = "SELECT DISTINCT e.* FROM exercises e";
        $params = [':team_id' => $teamId];
        
        if ($tagId) {
            $sql .= " JOIN exercise_tags et ON e.id = et.exercise_id";
        }
        
        $sql .= " WHERE e.team_id = :team_id";
        
        if ($query) {
            $sql .= " AND (e.title LIKE :query OR e.description LIKE :query)";
            $params[':query'] = '%' . $query . '%';
        }
        
        if ($tagId) {
            $sql .= " AND et.tag_id = :tag_id";
            $params[':tag_id'] = $tagId;
        }
        
        $sql .= " ORDER BY e.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM exercises WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function update(int $id, string $title, string $description, ?int $players, ?int $duration, ?string $imagePath = null, ?string $drawingData = null): void {
        $sql = "UPDATE exercises SET title = :title, description = :description, players = :players, duration = :duration";
        $params = [
            ':id' => $id,
            ':title' => $title,
            ':description' => $description,
            ':players' => $players,
            ':duration' => $duration
        ];

        if ($imagePath !== null) {
            $sql .= ", image_path = :image_path";
            $params[':image_path'] = $imagePath;
        }

        if ($drawingData !== null) {
            $sql .= ", drawing_data = :drawing_data";
            $params[':drawing_data'] = $drawingData;
        }

        $sql .= " WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function delete(int $id): void {
        $stmt = $this->pdo->prepare("DELETE FROM exercises WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }
}
