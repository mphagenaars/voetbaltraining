<?php
declare(strict_types=1);

class Team extends Model {
    protected string $table = 'teams';

    /** @return array<string,string> */
    public static function competitionCategoryOptions(): array {
        return [
            'JO7' => 'JO7',
            'JO8' => 'JO8',
            'JO9' => 'JO9',
            'JO10' => 'JO10',
            'JO11' => 'JO11',
            'JO12' => 'JO12',
            'JO13' => 'JO13',
            'JO14' => 'JO14',
            'JO15' => 'JO15',
            'JO16' => 'JO16',
            'JO17' => 'JO17',
            'JO18' => 'JO18',
            'JO19' => 'JO19',
            'MO11' => 'MO11',
            'MO12' => 'MO12',
            'MO13' => 'MO13',
            'MO14' => 'MO14',
            'MO15' => 'MO15',
            'MO16' => 'MO16',
            'MO17' => 'MO17',
            'MO18' => 'MO18',
            'MO19' => 'MO19',
            'MO20' => 'MO20',
        ];
    }

    public static function normalizeCompetitionCategory(string $value): string {
        $value = strtoupper(trim($value));
        if ($value === '') {
            return '';
        }

        if (preg_match('/^(JO|MO)\s*([0-9]{1,2})$/', $value, $matches) !== 1) {
            return '';
        }

        $prefix = (string)$matches[1];
        $age = (int)$matches[2];
        if ($age <= 0 || $age > 99) {
            return '';
        }

        return $prefix . $age;
    }

    public static function inferCompetitionCategoryFromTeamName(string $name): string {
        if (preg_match('/\b(JO|MO)\s*([0-9]{1,2})\b/i', $name, $matches) === 1) {
            return self::normalizeCompetitionCategory((string)$matches[1] . (string)$matches[2]);
        }
        return '';
    }

    public static function resolveCompetitionCategory(array $team): string {
        $explicit = self::normalizeCompetitionCategory((string)($team['competition_category'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        return self::inferCompetitionCategoryFromTeamName((string)($team['name'] ?? ''));
    }

    public static function normalizeMatchFormat(string $value): string {
        $value = strtolower(trim($value));
        return match ($value) {
            '4-vs-4', '4v4' => '4-vs-4',
            '6-vs-6', '6v6' => '6-vs-6',
            '8-vs-8', '8v8' => '8-vs-8',
            '11-vs-11', '11v11' => '11-vs-11',
            default => '',
        };
    }

    public static function deriveMatchFormatFromCompetitionCategory(string $category): ?string {
        if (preg_match('/^(JO|MO)([0-9]{1,2})$/', self::normalizeCompetitionCategory($category), $matches) !== 1) {
            return null;
        }

        $age = (int)$matches[2];
        if ($age >= 7 && $age <= 10) {
            return '6-vs-6';
        }
        if ($age === 11 || $age === 12) {
            return '8-vs-8';
        }
        if ($age >= 13) {
            return '11-vs-11';
        }

        return null;
    }

    public static function resolveMatchFormatForTeam(array $team): string {
        $category = self::resolveCompetitionCategory($team);
        $derived = self::deriveMatchFormatFromCompetitionCategory($category);
        if ($derived !== null) {
            return $derived;
        }

        $legacy = self::normalizeMatchFormat((string)($team['formation'] ?? ''));
        if ($legacy !== '') {
            return $legacy;
        }

        return '11-vs-11';
    }

    public static function matchFormatLabel(string $format): string {
        $normalized = self::normalizeMatchFormat($format);
        return match ($normalized) {
            '4-vs-4' => '4 tegen 4',
            '6-vs-6' => '6 tegen 6',
            '8-vs-8' => '8 tegen 8',
            default => '11 tegen 11',
        };
    }

    public static function resolveMemberRole(array $team): string {
        if (!empty($team['is_coach'])) {
            return 'coach';
        }
        if (!empty($team['is_trainer'])) {
            return 'trainer';
        }
        return 'player';
    }

    public static function roleLabelFromRoles(array $roles): string {
        $roleParts = [];
        if (!empty($roles['is_coach'])) {
            $roleParts[] = 'Coach';
        }
        if (!empty($roles['is_trainer'])) {
            $roleParts[] = 'Trainer';
        }
        return implode(' & ', $roleParts);
    }

    public static function hasStaffPrivileges(array $roles): bool {
        return !empty($roles['is_coach']) || !empty($roles['is_trainer']);
    }

    public function canAccessTeam(int $teamId, int $userId, bool $isAdmin = false): bool {
        if ($isAdmin) {
            return true;
        }

        return $this->isMember($teamId, $userId);
    }

    public function canManageTeam(int $teamId, int $userId, bool $isAdmin = false): bool {
        if ($isAdmin) {
            return true;
        }

        $roles = $this->getMemberRoles($teamId, $userId);
        return self::hasStaffPrivileges($roles);
    }

    public function create(string $name, int $creatorId, string $club = '', string $season = '', string $competitionCategory = ''): int {
        $resolvedCategory = self::normalizeCompetitionCategory($competitionCategory);
        if ($resolvedCategory === '') {
            $resolvedCategory = self::inferCompetitionCategoryFromTeamName($name);
        }

        $stmt = $this->pdo->prepare("INSERT INTO teams (name, invite_code, club, season, competition_category) VALUES (:name, :invite_code, :club, :season, :competition_category)");
        // Genereer een random invite code
        $inviteCode = bin2hex(random_bytes(8));
        $stmt->execute([
            ':name' => $name, 
            ':invite_code' => $inviteCode,
            ':club' => $club,
            ':season' => $season,
            ':competition_category' => $resolvedCategory
        ]);
        $teamId = (int)$this->pdo->lastInsertId();

        // Voeg maker toe als lid met rol coach en trainer
        $this->addMember($teamId, $creatorId, true, true);

        return $teamId;
    }

    public function getTeamsForUser(int $userId): array {
        $stmt = $this->pdo->prepare("
            SELECT t.*, tm.is_coach, tm.is_trainer, tm.is_hidden, c.logo_path 
            FROM teams t 
            JOIN team_members tm ON t.id = tm.team_id 
            LEFT JOIN clubs c ON t.club = c.name
            WHERE tm.user_id = :user_id
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function setTeamVisibility(int $teamId, int $userId, bool $hidden): void {
        $stmt = $this->pdo->prepare("UPDATE team_members SET is_hidden = :hidden WHERE team_id = :team_id AND user_id = :user_id");
        $stmt->execute([
            ':hidden' => $hidden ? 1 : 0,
            ':team_id' => $teamId,
            ':user_id' => $userId
        ]);
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

    public function getTeamDetails(int $teamId): ?array {
        $stmt = $this->pdo->prepare("
            SELECT t.*, c.logo_path 
            FROM teams t 
            LEFT JOIN clubs c ON t.club = c.name
            WHERE t.id = :id
        ");
        $stmt->execute([':id' => $teamId]);
        $result = $stmt->fetch();
        return $result ?: null;
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

    public function getTeamMembers(int $teamId): array {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.username, u.name, tm.is_coach, tm.is_trainer
            FROM team_members tm
            JOIN users u ON tm.user_id = u.id
            WHERE tm.team_id = :team_id
            ORDER BY u.name ASC
        ");
        $stmt->execute([':team_id' => $teamId]);
        return $stmt->fetchAll();
    }
}
