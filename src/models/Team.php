<?php
declare(strict_types=1);

class Team {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function create(string $name, int $creatorId): int {
        $stmt = $this->pdo->prepare("INSERT INTO teams (name, invite_code) VALUES (:name, :invite_code)");
        // Genereer een random invite code
        $inviteCode = bin2hex(random_bytes(8));
        $stmt->execute([':name' => $name, ':invite_code' => $inviteCode]);
        $teamId = (int)$this->pdo->lastInsertId();

        // Voeg maker toe als lid met rol 'admin'
        $this->addMember($teamId, $creatorId, 'admin');

        return $teamId;
    }

    public function getTeamsForUser(int $userId): array {
        $stmt = $this->pdo->prepare("
            SELECT t.*, tm.role 
            FROM teams t 
            JOIN team_members tm ON t.id = tm.team_id 
            WHERE tm.user_id = :user_id
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function addMember(int $teamId, int $userId, string $role = 'coach'): void {
        $stmt = $this->pdo->prepare("INSERT INTO team_members (team_id, user_id, role) VALUES (:team_id, :user_id, :role)");
        $stmt->execute([':team_id' => $teamId, ':user_id' => $userId, ':role' => $role]);
    }
    
    public function isMember(int $teamId, int $userId): bool {
        $stmt = $this->pdo->prepare("SELECT 1 FROM team_members WHERE team_id = :team_id AND user_id = :user_id");
        $stmt->execute([':team_id' => $teamId, ':user_id' => $userId]);
        return (bool)$stmt->fetch();
    }

    public function getByInviteCode(string $code): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM teams WHERE invite_code = :code");
        $stmt->execute([':code' => $code]);
        $team = $stmt->fetch();
        return $team ?: null;
    }
}
