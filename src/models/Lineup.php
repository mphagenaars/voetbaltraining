<?php
declare(strict_types=1);

class Lineup {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function create(int $teamId, string $name, string $formation): int {
        $stmt = $this->pdo->prepare("INSERT INTO lineups (team_id, name, formation) VALUES (:team_id, :name, :formation)");
        $stmt->execute([
            ':team_id' => $teamId,
            ':name' => $name,
            ':formation' => $formation
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getAllForTeam(int $teamId): array {
        $stmt = $this->pdo->prepare("SELECT * FROM lineups WHERE team_id = :team_id ORDER BY created_at DESC");
        $stmt->execute([':team_id' => $teamId]);
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM lineups WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $lineup = $stmt->fetch();
        return $lineup ?: null;
    }

    public function savePositions(int $lineupId, array $positions): void {
        $this->pdo->beginTransaction();
        try {
            // Clear existing positions
            $stmt = $this->pdo->prepare("DELETE FROM lineup_positions WHERE lineup_id = :lineup_id");
            $stmt->execute([':lineup_id' => $lineupId]);

            // Insert new positions
            $stmt = $this->pdo->prepare("INSERT INTO lineup_positions (lineup_id, player_id, position_x, position_y) VALUES (:lineup_id, :player_id, :x, :y)");
            foreach ($positions as $pos) {
                $stmt->execute([
                    ':lineup_id' => $lineupId,
                    ':player_id' => $pos['player_id'],
                    ':x' => $pos['x'],
                    ':y' => $pos['y']
                ]);
            }
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getPositions(int $lineupId): array {
        $stmt = $this->pdo->prepare("
            SELECT lp.*, p.name as player_name 
            FROM lineup_positions lp 
            JOIN players p ON lp.player_id = p.id 
            WHERE lp.lineup_id = :lineup_id
        ");
        $stmt->execute([':lineup_id' => $lineupId]);
        return $stmt->fetchAll();
    }

    public function delete(int $id): void {
        $stmt = $this->pdo->prepare("DELETE FROM lineups WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }
}
