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

    public function count(): int {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM {$this->table}");
        return (int)$stmt->fetchColumn();
    }

    protected function replaceMany(string $deleteSql, array $deleteParams, string $insertSql, array $items, callable $mapItemToParams): void {
        $this->pdo->beginTransaction();
        try {
            // Delete
            $stmt = $this->pdo->prepare($deleteSql);
            $stmt->execute($deleteParams);

            // Insert
            if (!empty($items)) {
                $stmt = $this->pdo->prepare($insertSql);
                foreach ($items as $index => $item) {
                    $params = $mapItemToParams($item, $index);
                    $stmt->execute($params);
                }
            }
            
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
