<?php
declare(strict_types=1);

class Exercise {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function create(int $teamId, string $title, string $description, ?string $teamTask, ?string $trainingObjective, ?string $footballAction, ?int $minPlayers, ?int $maxPlayers, ?int $duration, ?string $imagePath = null, ?string $drawingData = null, ?string $variation = null): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO exercises (team_id, title, description, team_task, training_objective, football_action, min_players, max_players, duration, image_path, drawing_data, variation) 
            VALUES (:team_id, :title, :description, :team_task, :training_objective, :football_action, :min_players, :max_players, :duration, :image_path, :drawing_data, :variation)
        ");
        $stmt->execute([
            ':team_id' => $teamId,
            ':title' => $title,
            ':description' => $description,
            ':team_task' => $teamTask,
            ':training_objective' => $trainingObjective,
            ':football_action' => $footballAction,
            ':min_players' => $minPlayers,
            ':max_players' => $maxPlayers,
            ':duration' => $duration,
            ':image_path' => $imagePath,
            ':drawing_data' => $drawingData,
            ':variation' => $variation
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getAllForTeam(int $teamId): array {
        $stmt = $this->pdo->prepare("SELECT * FROM exercises WHERE team_id = :team_id ORDER BY created_at DESC");
        $stmt->execute([':team_id' => $teamId]);
        return $stmt->fetchAll();
    }

    public function search(int $teamId, ?string $query = null): array {
        $sql = "SELECT DISTINCT e.* FROM exercises e WHERE e.team_id = :team_id";
        $params = [':team_id' => $teamId];
        
        if ($query) {
            $sql .= " AND (e.title LIKE :query OR e.description LIKE :query)";
            $params[':query'] = '%' . $query . '%';
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

    public function update(int $id, string $title, string $description, ?string $teamTask, ?string $trainingObjective, ?string $footballAction, ?int $minPlayers, ?int $maxPlayers, ?int $duration, ?string $imagePath = null, ?string $drawingData = null, ?string $variation = null): void {
        $sql = "UPDATE exercises SET title = :title, description = :description, team_task = :team_task, training_objective = :training_objective, football_action = :football_action, min_players = :min_players, max_players = :max_players, duration = :duration, variation = :variation";
        $params = [
            ':id' => $id,
            ':title' => $title,
            ':description' => $description,
            ':team_task' => $teamTask,
            ':training_objective' => $trainingObjective,
            ':football_action' => $footballAction,
            ':min_players' => $minPlayers,
            ':max_players' => $maxPlayers,
            ':duration' => $duration,
            ':variation' => $variation
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
