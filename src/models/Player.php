<?php
declare(strict_types=1);

class Player {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function create(int $teamId, string $name): int {
        $stmt = $this->pdo->prepare("INSERT INTO players (team_id, name) VALUES (:team_id, :name)");
        $stmt->execute([':team_id' => $teamId, ':name' => $name]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getAllForTeam(int $teamId): array {
        $stmt = $this->pdo->prepare("SELECT * FROM players WHERE team_id = :team_id ORDER BY name ASC");
        $stmt->execute([':team_id' => $teamId]);
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM players WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $player = $stmt->fetch();
        return $player ?: null;
    }

    public function update(int $id, string $name): void {
        $stmt = $this->pdo->prepare("UPDATE players SET name = :name WHERE id = :id");
        $stmt->execute([':id' => $id, ':name' => $name]);
    }

    public function delete(int $id): void {
        $stmt = $this->pdo->prepare("DELETE FROM players WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }
}
