<?php
declare(strict_types=1);

class Exercise extends Model {
    protected string $table = 'exercises';

    public function create(?int $teamId, string $title, string $description, ?string $teamTask, ?string $trainingObjective, ?string $footballAction, ?int $minPlayers, ?int $maxPlayers, ?int $duration, ?string $imagePath = null, ?string $drawingData = null, ?string $variation = null, string $fieldType = 'portrait', ?int $createdBy = null, ?string $source = null, ?string $coachInstructions = null): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO exercises (team_id, title, description, team_task, training_objective, football_action, min_players, max_players, duration, image_path, drawing_data, variation, field_type, created_by, source, coach_instructions) 
            VALUES (:team_id, :title, :description, :team_task, :training_objective, :football_action, :min_players, :max_players, :duration, :image_path, :drawing_data, :variation, :field_type, :created_by, :source, :coach_instructions)
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
            ':variation' => $variation,
            ':field_type' => $fieldType,
            ':created_by' => $createdBy,
            ':source' => $source,
            ':coach_instructions' => $coachInstructions
        ]);
        return (int)$this->pdo->lastInsertId();
    }



    public function getById(int $id): ?array {
        $stmt = $this->pdo->prepare("
            SELECT e.*, u.name as creator_name 
            FROM exercises e 
            LEFT JOIN users u ON e.created_by = u.id 
            WHERE e.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function search(?int $teamId, ?string $query = null, ?string $teamTask = null, ?string $trainingObjective = null, ?string $footballAction = null): array {
        // Show all exercises (generic)
        $sql = "SELECT DISTINCT e.*, u.name as creator_name FROM exercises e LEFT JOIN users u ON e.created_by = u.id WHERE 1=1";
        $params = [];
        
        if ($query) {
            $sql .= " AND (e.title LIKE :query OR e.description LIKE :query)";
            $params[':query'] = '%' . $query . '%';
        }

        if ($teamTask) {
            $sql .= " AND e.team_task = :team_task";
            $params[':team_task'] = $teamTask;
        }

        if ($trainingObjective) {
            $sql .= " AND e.training_objective LIKE :training_objective";
            $params[':training_objective'] = '%' . $trainingObjective . '%';
        }

        if ($footballAction) {
            $sql .= " AND e.football_action LIKE :football_action";
            $params[':football_action'] = '%' . $footballAction . '%';
        }
        
        $sql .= " ORDER BY e.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }



    public function update(int $id, string $title, string $description, ?string $teamTask, ?string $trainingObjective, ?string $footballAction, ?int $minPlayers, ?int $maxPlayers, ?int $duration, ?string $imagePath = null, ?string $drawingData = null, ?string $variation = null, ?string $fieldType = null, ?string $source = null, ?string $coachInstructions = null): void {
        $sql = "UPDATE exercises SET title = :title, description = :description, team_task = :team_task, training_objective = :training_objective, football_action = :football_action, min_players = :min_players, max_players = :max_players, duration = :duration, variation = :variation, source = :source, coach_instructions = :coach_instructions";
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
            ':variation' => $variation,
            ':source' => $source,
            ':coach_instructions' => $coachInstructions
        ];

        if ($imagePath !== null) {
            $sql .= ", image_path = :image_path";
            $params[':image_path'] = $imagePath;
        }

        if ($drawingData !== null) {
            $sql .= ", drawing_data = :drawing_data";
            $params[':drawing_data'] = $drawingData;
        }

        if ($fieldType !== null) {
            $sql .= ", field_type = :field_type";
            $params[':field_type'] = $fieldType;
        }

        $sql .= " WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    private static function getOptionsByCategory(string $category): array {
        $db = (new Database())->getConnection();
        $stmt = $db->prepare("SELECT name FROM exercise_options WHERE category = :category ORDER BY sort_order ASC");
        $stmt->execute([':category' => $category]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function getTeamTasks(): array {
        return self::getOptionsByCategory('team_task');
    }

    public static function getObjectives(): array {
        return self::getOptionsByCategory('objective');
    }

    public static function getFootballActions(): array {
        return self::getOptionsByCategory('football_action');
    }
}
