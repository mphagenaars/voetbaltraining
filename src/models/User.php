<?php
declare(strict_types=1);

class User extends Model {
    protected string $table = 'users';

    public function create(string $username, string $password, string $name): int {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO users (username, password_hash, name) VALUES (:username, :hash, :name)");
        $stmt->execute([
            ':username' => $username,
            ':hash' => $hash,
            ':name' => $name
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getByUsername(string $username): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();
        return $user ?: null;
    }
}
