<?php
declare(strict_types=1);

class Game extends Model {
    protected string $table = 'matches';

    public function getMatches(int $teamId, string $orderBy, bool $hidePlayed): array {
        $sql = "SELECT * FROM matches WHERE team_id = :team_id";
        if ($hidePlayed) {
            $sql .= " AND date >= date('now')";
        }
        $sql .= " ORDER BY $orderBy";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':team_id' => $teamId]);
        return $stmt->fetchAll();
    }

    public function create(int $teamId, string $opponent, string $date, int $isHome, string $formation): int {
        $stmt = $this->pdo->prepare("INSERT INTO matches (team_id, opponent, date, is_home, formation) VALUES (:team_id, :opponent, :date, :is_home, :formation)");
        $stmt->execute([
            ':team_id' => $teamId,
            ':opponent' => $opponent,
            ':date' => $date,
            ':is_home' => $isHome,
            ':formation' => $formation
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateMatch(int $matchId, string $opponent, string $date, int $isHome, string $formation): void {
        $stmt = $this->pdo->prepare("
            UPDATE matches
            SET opponent = :opponent, date = :date, is_home = :is_home, formation = :formation
            WHERE id = :id
        ");
        $stmt->execute([
            ':opponent' => $opponent,
            ':date' => $date,
            ':is_home' => $isHome,
            ':formation' => $formation,
            ':id' => $matchId
        ]);
    }

    public function savePlayers(int $matchId, array $players): void {
        $this->replaceMany(
            "DELETE FROM match_players WHERE match_id = :match_id",
            [':match_id' => $matchId],
            "INSERT INTO match_players (match_id, player_id, position_x, position_y, is_substitute, is_keeper, is_absent) VALUES (:match_id, :player_id, :x, :y, :sub, :is_keeper, :is_absent)",
            $players,
            function($p) use ($matchId) {
                return [
                    ':match_id' => $matchId,
                    ':player_id' => $p['player_id'],
                    ':x' => $p['x'],
                    ':y' => $p['y'],
                    ':sub' => $p['is_substitute'] ?? 0,
                    ':is_keeper' => $p['is_keeper'] ?? 0,
                    ':is_absent' => $p['is_absent'] ?? 0
                ];
            }
        );
    }

    public function allPlayersBelongToTeam(array $playerIds, int $teamId): bool {
        $uniqueIds = array_values(array_unique(array_map('intval', $playerIds)));
        $uniqueIds = array_values(array_filter($uniqueIds, static fn(int $id): bool => $id > 0));

        if (empty($uniqueIds)) {
            return true;
        }

        $placeholders = [];
        $params = [':team_id' => $teamId];

        foreach ($uniqueIds as $i => $playerId) {
            $key = ':pid' . $i;
            $placeholders[] = $key;
            $params[$key] = $playerId;
        }

        $sql = "SELECT COUNT(*) FROM players WHERE team_id = :team_id AND id IN (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() === count($uniqueIds);
    }

    public function getPlayers(int $matchId): array {
        $stmt = $this->pdo->prepare("
            SELECT mp.*, p.name as player_name, p.number
            FROM match_players mp 
            JOIN players p ON mp.player_id = p.id 
            WHERE mp.match_id = :match_id
        ");
        $stmt->execute([':match_id' => $matchId]);
        return $stmt->fetchAll();
    }

    public function addEvent(int $matchId, int $minute, string $type, ?int $playerId, ?string $description, int $period = 1): void {
        $stmt = $this->pdo->prepare("INSERT INTO match_events (match_id, minute, type, player_id, description, period) VALUES (:match_id, :minute, :type, :player_id, :description, :period)");
        $stmt->execute([
            ':match_id' => $matchId,
            ':minute' => $minute,
            ':type' => $type,
            ':player_id' => $playerId,
            ':description' => $description,
            ':period' => $period
        ]);
    }

    public function getEvents(int $matchId): array {
        $stmt = $this->pdo->prepare("
            SELECT me.*, p.name as player_name 
            FROM match_events me 
            LEFT JOIN players p ON me.player_id = p.id 
            WHERE me.match_id = :match_id 
            ORDER BY me.minute ASC, me.created_at ASC
        ");
        $stmt->execute([':match_id' => $matchId]);
        return $stmt->fetchAll();
    }

    public function getPlayerReportStats(int $teamId, string $sortBy = 'matches', string $sortDir = 'desc'): array {
        $sortMap = [
            'name' => 'p.name',
            'matches' => 'matches_played',
            'absent' => 'absent_matches',
            'starts' => 'starts',
            'goals' => 'goals',
            'goal_matches' => 'goal_matches',
            'keepers' => 'keeper_selections',
        ];

        $sortColumn = $sortMap[$sortBy] ?? 'matches_played';
        $direction = strtolower($sortDir) === 'asc' ? 'ASC' : 'DESC';
        $orderBy = $sortColumn . ' ' . $direction;
        if ($sortColumn !== 'p.name') {
            $orderBy .= ', p.name ASC';
        }

        $stmt = $this->pdo->prepare("
            SELECT
                p.id,
                p.name,
                COALESCE(start_stats.starts, 0) AS starts,
                COALESCE(goal_stats.goals, 0) AS goals,
                COALESCE(goal_match_stats.goal_matches, 0) AS goal_matches,
                COALESCE(absent_stats.absent_matches, 0) AS absent_matches,
                COALESCE(keeper_stats.keeper_selections, 0) AS keeper_selections,
                MAX(
                    (SELECT COUNT(*) FROM matches WHERE team_id = :team_id_total_matches) - COALESCE(absent_stats.absent_matches, 0),
                    0
                ) AS matches_played
            FROM players p
            LEFT JOIN (
                SELECT mp.player_id, COUNT(*) AS starts
                FROM match_players mp
                JOIN matches m ON m.id = mp.match_id
                WHERE m.team_id = :team_id_start
                  AND mp.is_substitute = 0
                  AND mp.is_absent = 0
                GROUP BY mp.player_id
            ) start_stats ON start_stats.player_id = p.id
            LEFT JOIN (
                SELECT me.player_id, COUNT(*) AS goals
                FROM match_events me
                JOIN matches m ON m.id = me.match_id
                WHERE m.team_id = :team_id_goal
                  AND me.type = 'goal'
                  AND me.player_id IS NOT NULL
                GROUP BY me.player_id
            ) goal_stats ON goal_stats.player_id = p.id
            LEFT JOIN (
                SELECT me.player_id, COUNT(DISTINCT me.match_id) AS goal_matches
                FROM match_events me
                JOIN matches m ON m.id = me.match_id
                WHERE m.team_id = :team_id_goal_matches
                  AND me.type = 'goal'
                  AND me.player_id IS NOT NULL
                GROUP BY me.player_id
            ) goal_match_stats ON goal_match_stats.player_id = p.id
            LEFT JOIN (
                SELECT mp.player_id, COUNT(*) AS absent_matches
                FROM match_players mp
                JOIN matches m ON m.id = mp.match_id
                WHERE m.team_id = :team_id_absent
                  AND mp.is_absent = 1
                GROUP BY mp.player_id
            ) absent_stats ON absent_stats.player_id = p.id
            LEFT JOIN (
                SELECT mp.player_id, COUNT(*) AS keeper_selections
                FROM match_players mp
                JOIN matches m ON m.id = mp.match_id
                WHERE m.team_id = :team_id_keeper
                  AND mp.is_keeper = 1
                GROUP BY mp.player_id
            ) keeper_stats ON keeper_stats.player_id = p.id
            WHERE p.team_id = :team_id_player
            ORDER BY {$orderBy}
        ");

        $stmt->execute([
            ':team_id_start' => $teamId,
            ':team_id_goal' => $teamId,
            ':team_id_goal_matches' => $teamId,
            ':team_id_absent' => $teamId,
            ':team_id_keeper' => $teamId,
            ':team_id_total_matches' => $teamId,
            ':team_id_player' => $teamId
        ]);

        return $stmt->fetchAll();
    }

    public function getReportSummary(int $teamId): array {
        $stmt = $this->pdo->prepare("
            SELECT
                (SELECT COUNT(*) FROM matches WHERE team_id = :team_id_matches) AS total_matches,
                (
                    SELECT COUNT(*)
                    FROM match_events me
                    JOIN matches m ON m.id = me.match_id
                    WHERE m.team_id = :team_id_goals
                      AND me.type = 'goal'
                      AND me.player_id IS NOT NULL
                ) AS total_goals
        ");

        $stmt->execute([
            ':team_id_matches' => $teamId,
            ':team_id_goals' => $teamId
        ]);

        $summary = $stmt->fetch();
        return $summary ?: ['total_matches' => 0, 'total_goals' => 0];
    }
    
    public function getTimerState(int $matchId): array {
        $stmt = $this->pdo->prepare("SELECT * FROM match_events WHERE match_id = :match_id AND type = 'whistle' ORDER BY created_at ASC");
        $stmt->execute([':match_id' => $matchId]);
        $whistles = $stmt->fetchAll();

        $isPlaying = false;
        $totalSeconds = 0;
        $currentPeriod = 0;
        $lastStartTime = null;

        foreach ($whistles as $w) {
            $period = (int)$w['period'];
            $time = strtotime($w['created_at']);
            
            if ($w['description'] === 'start_period') {
                $isPlaying = true;
                $lastStartTime = $time;
                $currentPeriod = $period;
            } elseif ($w['description'] === 'end_period') {
                if ($isPlaying && $lastStartTime) {
                    $totalSeconds += ($time - $lastStartTime);
                }
                $isPlaying = false;
                $lastStartTime = null;
            }
        }

        if ($isPlaying && $lastStartTime) {
             $totalSeconds += (time() - $lastStartTime);
        }

        return [
            'is_playing' => $isPlaying,
            'current_period' => $currentPeriod,
            'total_minutes' => floor($totalSeconds / 60),
            'total_seconds' => $totalSeconds,
            'start_time' => $lastStartTime
        ];
    }
    
    public function updateScore(int $matchId, int $home, int $away): void {
        $stmt = $this->pdo->prepare("UPDATE matches SET score_home = :home, score_away = :away WHERE id = :id");
        $stmt->execute([':home' => $home, ':away' => $away, ':id' => $matchId]);
    }

    public function updateEvaluation(int $matchId, string $evaluation): void {
        $stmt = $this->pdo->prepare("UPDATE matches SET evaluation = :evaluation WHERE id = :id");
        $stmt->execute([':evaluation' => $evaluation, ':id' => $matchId]);
    }
}
