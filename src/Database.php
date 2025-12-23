<?php
declare(strict_types=1);

class Database {
    private ?PDO $pdo = null;

    public function getConnection(): PDO {
        if ($this->pdo === null) {
            $dbPath = __DIR__ . '/../data/database.sqlite';
            $this->pdo = new PDO('sqlite:' . $dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            // Zet foreign keys aan voor SQLite
            $this->pdo->exec('PRAGMA foreign_keys = ON;');
        }
        return $this->pdo;
    }
}
