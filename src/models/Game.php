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
