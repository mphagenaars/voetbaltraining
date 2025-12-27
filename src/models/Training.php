<?php
declare(strict_types=1);

class Training extends Model {
    protected string $table = 'trainings';

    public function create(int $teamId, string $title, string $description): int {
        $stmt = $this->pdo->prepare("INSERT INTO trainings (team_id, title, description) VALUES (:team_id, :title, :description)");
        $stmt->execute([':team_id' => $teamId, ':title' => $title, ':description' => $description]);
        return (int)$this->pdo->lastInsertId();
    }

    public function addExercise(int $trainingId, int $exerciseId, int $sortOrder, ?int $duration = null): void {
        $stmt = $this->pdo->prepare("INSERT INTO training_exercises (training_id, exercise_id, sort_order, duration) VALUES (:training_id, :exercise_id, :sort_order, :duration)");
        $stmt->execute([
            ':training_id' => $trainingId,
            ':exercise_id' => $exerciseId,
            ':sort_order' => $sortOrder,
            ':duration' => $duration
        ]);
    }

    public function getAllForTeam(int $teamId, string $orderBy = 'created_at DESC'): array {
        $stmt = $this->pdo->prepare("
            SELECT t.*, 
            (SELECT COUNT(*) FROM training_exercises te WHERE te.training_id = t.id) as exercise_count,
            (SELECT SUM(COALESCE(te.duration, e.duration, 0)) 
             FROM training_exercises te 
             JOIN exercises e ON te.exercise_id = e.id 
             WHERE te.training_id = t.id) as total_duration
            FROM trainings t 
            WHERE t.team_id = :team_id 
            ORDER BY $orderBy
        ");
        $stmt->execute([':team_id' => $teamId]);
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM trainings WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $training = $stmt->fetch();
        
        if (!$training) {
            return null;
        }

        // Get exercises
        $stmt = $this->pdo->prepare("
            SELECT e.*, te.sort_order, te.duration as training_duration 
            FROM exercises e
            JOIN training_exercises te ON e.id = te.exercise_id
            WHERE te.training_id = :id
            ORDER BY te.sort_order ASC
        ");
        $stmt->execute([':id' => $id]);
        $training['exercises'] = $stmt->fetchAll();

        return $training;
    }

    public function update(int $id, string $title, string $description): void {
        $stmt = $this->pdo->prepare("UPDATE trainings SET title = :title, description = :description WHERE id = :id");
        $stmt->execute([':id' => $id, ':title' => $title, ':description' => $description]);
    }

    public function updateExercises(int $trainingId, array $exercises): void {
        // $exercises is array of ['id' => exercise_id, 'duration' => duration]
        
        $this->pdo->beginTransaction();
        try {
            // Remove old
            $stmt = $this->pdo->prepare("DELETE FROM training_exercises WHERE training_id = :training_id");
            $stmt->execute([':training_id' => $trainingId]);

            // Add new
            $stmt = $this->pdo->prepare("INSERT INTO training_exercises (training_id, exercise_id, sort_order, duration) VALUES (:training_id, :exercise_id, :sort_order, :duration)");
            
            foreach ($exercises as $index => $ex) {
                $stmt->execute([
                    ':training_id' => $trainingId,
                    ':exercise_id' => $ex['id'],
                    ':sort_order' => $index,
                    ':duration' => !empty($ex['duration']) ? $ex['duration'] : null
                ]);
            }
            
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
