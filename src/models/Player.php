<?php
declare(strict_types=1);

class Player extends Model {
    protected string $table = 'players';

    public function create(int $teamId, string $name): int {
        $stmt = $this->pdo->prepare("INSERT INTO players (team_id, name) VALUES (:team_id, :name)");
        $stmt->execute([':team_id' => $teamId, ':name' => $name]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getAllForTeam(int $teamId, string $orderBy = 'name ASC'): array {
        return parent::getAllForTeam($teamId, $orderBy);
    }



    public function update(int $id, string $name): void {
        $stmt = $this->pdo->prepare("UPDATE players SET name = :name WHERE id = :id");
        $stmt->execute([':id' => $id, ':name' => $name]);
    }


}
