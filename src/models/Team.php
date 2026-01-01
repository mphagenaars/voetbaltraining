<?php
declare(strict_types=1);

class Team extends Model {
    protected string $table = 'teams';

    public function create(string $name, int $creatorId): int {
        $stmt = $this->pdo->prepare("INSERT INTO teams (name, invite_code) VALUES (:name, :invite_code)");
        // Genereer een random invite code
        $inviteCode = bin2hex(random_bytes(8));
        $stmt->execute([':name' => $name, ':invite_code' => $inviteCode]);
        $teamId = (int)$this->pdo->lastInsertId();

        // Voeg maker toe als lid met rol coach en trainer
        $this->addMember($teamId, $creatorId, true, true);

        return $teamId;
    }

    public function getTeamsForUser(int $userId): array {
        $stmt = $this->pdo->prepare("
            SELECT t.*, tm.is_coach, tm.is_trainer 
            FROM teams t 
            JOIN team_members tm ON t.id = tm.team_id 
            WHERE tm.user_id = :user_id
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function addMember(int $teamId, int $userId, bool $isCoach = true, bool $isTrainer = false): void {
        $stmt = $this->pdo->prepare("INSERT INTO team_members (team_id, user_id, is_coach, is_trainer) VALUES (:team_id, :user_id, :is_coach, :is_trainer)");
        $stmt->execute([
            ':team_id' => $teamId, 
            ':user_id' => $userId, 
            ':is_coach' => $isCoach ? 1 : 0,
            ':is_trainer' => $isTrainer ? 1 : 0
        ]);
    }
    
    public function isMember(int $teamId, int $userId): bool {
        $stmt = $this->pdo->prepare("SELECT 1 FROM team_members WHERE team_id = :team_id AND user_id = :user_id");
        $stmt->execute([':team_id' => $teamId, ':user_id' => $userId]);
        return (bool)$stmt->fetch();
    }

    public function getMemberRoles(int $teamId, int $userId): array {
        $stmt = $this->pdo->prepare("SELECT is_coach, is_trainer FROM team_members WHERE team_id = :team_id AND user_id = :user_id");
        $stmt->execute([':team_id' => $teamId, ':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return ['is_coach' => 0, 'is_trainer' => 0];
        }
        
        return [
            'is_coach' => (int)$result['is_coach'],
            'is_trainer' => (int)$result['is_trainer']
        ];
    }

    public function getByInviteCode(string $code): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM teams WHERE invite_code = :code");
        $stmt->execute([':code' => $code]);
        $team = $stmt->fetch();
        return $team ?: null;
    }

    public function updateMemberRoles(int $teamId, int $userId, bool $isCoach, bool $isTrainer): void {
        $stmt = $this->pdo->prepare("UPDATE team_members SET is_coach = :is_coach, is_trainer = :is_trainer WHERE team_id = :team_id AND user_id = :user_id");
        $stmt->execute([
            ':is_coach' => $isCoach ? 1 : 0, 
            ':is_trainer' => $isTrainer ? 1 : 0, 
            ':team_id' => $teamId, 
            ':user_id' => $userId
        ]);
    }

    public function removeMember(int $teamId, int $userId): void {
        $stmt = $this->pdo->prepare("DELETE FROM team_members WHERE team_id = :team_id AND user_id = :user_id");
        $stmt->execute([':team_id' => $teamId, ':user_id' => $userId]);
    }
}
