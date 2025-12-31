<?php
declare(strict_types=1);

class Exercise extends Model {
    protected string $table = 'exercises';

    public function create(?int $teamId, string $title, string $description, ?string $teamTask, ?string $trainingObjective, ?string $footballAction, ?int $minPlayers, ?int $maxPlayers, ?int $duration, ?string $imagePath = null, ?string $drawingData = null, ?string $variation = null, string $fieldType = 'portrait'): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO exercises (team_id, title, description, team_task, training_objective, football_action, min_players, max_players, duration, image_path, drawing_data, variation, field_type) 
            VALUES (:team_id, :title, :description, :team_task, :training_objective, :football_action, :min_players, :max_players, :duration, :image_path, :drawing_data, :variation, :field_type)
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
            ':field_type' => $fieldType
        ]);
        return (int)$this->pdo->lastInsertId();
    }



    public function search(?int $teamId, ?string $query = null, ?string $teamTask = null, ?string $trainingObjective = null, ?string $footballAction = null): array {
        // Show all exercises (generic)
        $sql = "SELECT DISTINCT e.* FROM exercises e WHERE 1=1";
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



    public function update(int $id, string $title, string $description, ?string $teamTask, ?string $trainingObjective, ?string $footballAction, ?int $minPlayers, ?int $maxPlayers, ?int $duration, ?string $imagePath = null, ?string $drawingData = null, ?string $variation = null, ?string $fieldType = null): void {
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

        if ($fieldType !== null) {
            $sql .= ", field_type = :field_type";
            $params[':field_type'] = $fieldType;
        }

        $sql .= " WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public static function getTeamTasks(): array {
        return ['Aanvallen', 'Omschakelen', 'Verdedigen', 'Neutraal'];
    }

    public static function getObjectives(): array {
        return [
            'Creëren van kansen',
            'Dieptespel in opbouw verbeteren',
            'Positiespel in opbouw verbeteren',
            'Scoren verbeteren',
            'Uitspelen van één tegen één situatie verbeteren',
            'Omschakelen bij veroveren van de bal verbeteren',
            'Omschakelen op moment van balverlies verbeteren',
            'Storen en veroveren van de bal verbeteren',
            'Verdedigen van dieptespel verbeteren',
            'Verdedigen van één tegen één situatie verbeteren',
            'Verdedigen wanneer de tegenstander kansen creëert verbeteren',
            'Voorkomen van doelpunten verbeteren'
        ];
    }

    public static function getFootballActions(): array {
        return [
            'Kijken',
            'Dribbelen',
            'Passen',
            'Schieten',
            'Cheeta',
            'Brug maken',
            'Lijntje doorknippen',
            'Jagen'
        ];
    }
}
