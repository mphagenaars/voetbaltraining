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

    public function updateName(int $id, string $name): void {
        $stmt = $this->pdo->prepare("UPDATE users SET name = :name WHERE id = :id");
        $stmt->execute([':name' => $name, ':id' => $id]);
    }

    public function updatePassword(int $id, string $hash): void {
        $stmt = $this->pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
        $stmt->execute([':hash' => $hash, ':id' => $id]);
    }

    public function setAdminStatus(int $id, bool $isAdmin): void {
        $stmt = $this->pdo->prepare("UPDATE users SET is_admin = :is_admin WHERE id = :id");
        $stmt->execute([':is_admin' => $isAdmin ? 1 : 0, ':id' => $id]);
    }

    public function createRememberToken(int $userId, string $selector, string $hashedValidator, string $expiresAt): void {
        $stmt = $this->pdo->prepare("INSERT INTO user_tokens (user_id, selector, hashed_validator, expires_at) VALUES (:user_id, :selector, :hashed_validator, :expires_at)");
        $stmt->execute([
            ':user_id' => $userId,
            ':selector' => $selector,
            ':hashed_validator' => $hashedValidator,
            ':expires_at' => $expiresAt
        ]);
    }

    public function findTokenBySelector(string $selector): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM user_tokens WHERE selector = :selector AND expires_at > datetime('now')");
        $stmt->execute([':selector' => $selector]);
        $token = $stmt->fetch();
        return $token ?: null;
    }

    public function removeTokenBySelector(string $selector): void {
        $stmt = $this->pdo->prepare("DELETE FROM user_tokens WHERE selector = :selector");
        $stmt->execute([':selector' => $selector]);
    }
    
    public function removeExpiredTokens(): void {
        $this->pdo->exec("DELETE FROM user_tokens WHERE expires_at <= datetime('now')");
    }
}
