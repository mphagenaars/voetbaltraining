<?php
declare(strict_types=1);

abstract class Model {
    protected PDO $pdo;
    protected string $table;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getById(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function delete(int $id): void {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    public function getAll(string $orderBy = 'id ASC'): array {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table} ORDER BY $orderBy");
        return $stmt->fetchAll();
    }
    
    public function getAllForTeam(int $teamId, string $orderBy = 'created_at DESC'): array {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE team_id = :team_id ORDER BY $orderBy");
        $stmt->execute([':team_id' => $teamId]);
        return $stmt->fetchAll();
    }
}
