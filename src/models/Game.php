<?php
declare(strict_types=1);

class Game extends Model {
    protected string $table = 'matches';

    public function getMatches(int $teamId, string $orderBy, bool $hidePlayed): array {
        $sql = "SELECT * FROM matches WHERE team_id = :team_id";
        if ($hidePlayed) {
            $sql .= " AND date >= date('now')";
        }
        $sql .= " ORDER BY " . self::sanitizeOrderBy($orderBy, 'date ASC');
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':team_id' => $teamId]);
        return $stmt->fetchAll();
    }

    public function create(int $teamId, string $opponent, string $date, int $isHome, string $formation, ?int $formationTemplateId = null): int {
        $stmt = $this->pdo->prepare("INSERT INTO matches (team_id, opponent, date, is_home, formation, formation_template_id) VALUES (:team_id, :opponent, :date, :is_home, :formation, :formation_template_id)");
        $stmt->execute([
            ':team_id' => $teamId,
            ':opponent' => $opponent,
            ':date' => $date,
            ':is_home' => $isHome,
            ':formation' => $formation,
            ':formation_template_id' => $formationTemplateId,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateMatch(int $matchId, string $opponent, string $date, int $isHome, string $formation, ?int $formationTemplateId = null): void {
        $stmt = $this->pdo->prepare("
            UPDATE matches
            SET opponent = :opponent, date = :date, is_home = :is_home, formation = :formation, formation_template_id = :formation_template_id
            WHERE id = :id
        ");
        $stmt->execute([
            ':opponent' => $opponent,
            ':date' => $date,
            ':is_home' => $isHome,
            ':formation' => $formation,
            ':formation_template_id' => $formationTemplateId,
            ':id' => $matchId
        ]);
    }

    public function updateFormationTemplateId(int $matchId, ?int $formationTemplateId): void {
        $stmt = $this->pdo->prepare("
            UPDATE matches
            SET formation_template_id = :formation_template_id
            WHERE id = :id
        ");
        $stmt->execute([
            ':formation_template_id' => $formationTemplateId,
            ':id' => $matchId,
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

    public function getMatchPlayersForLive(int $matchId): array {
        $stmt = $this->pdo->prepare("
            SELECT
                mp.match_id,
                mp.player_id,
                mp.position_x,
                mp.position_y,
                mp.is_substitute,
                mp.is_keeper,
                mp.is_absent,
                p.name AS player_name,
                p.number
            FROM match_players mp
            JOIN players p ON p.id = mp.player_id
            WHERE mp.match_id = :match_id
            ORDER BY p.name ASC
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

    public function getWhistleEvents(int $matchId): array {
        $stmt = $this->pdo->prepare("
            SELECT id, period, description, created_at
            FROM match_events
            WHERE match_id = :match_id
              AND type = 'whistle'
            ORDER BY created_at ASC, id ASC
        ");
        $stmt->execute([':match_id' => $matchId]);
        return $stmt->fetchAll();
    }

    public function getPeriodLineups(int $matchId): array {
        $stmt = $this->pdo->prepare("
            SELECT
                mpl.*,
                p.name AS player_name
            FROM match_period_lineups mpl
            LEFT JOIN players p ON p.id = mpl.player_id
            WHERE mpl.match_id = :match_id
            ORDER BY mpl.period ASC, mpl.slot_code ASC
        ");
        $stmt->execute([':match_id' => $matchId]);
        return $stmt->fetchAll();
    }

    public function replacePeriodLineup(int $matchId, int $period, array $slots, ?int $createdBy): void {
        $this->pdo->beginTransaction();
        try {
            $deleteStmt = $this->pdo->prepare("
                DELETE FROM match_period_lineups
                WHERE match_id = :match_id
                  AND period = :period
            ");
            $deleteStmt->execute([
                ':match_id' => $matchId,
                ':period' => $period,
            ]);

            if (!empty($slots)) {
                $insertStmt = $this->pdo->prepare("
                    INSERT INTO match_period_lineups (match_id, period, slot_code, player_id, created_by)
                    VALUES (:match_id, :period, :slot_code, :player_id, :created_by)
                ");
                foreach ($slots as $slot) {
                    $insertStmt->execute([
                        ':match_id' => $matchId,
                        ':period' => $period,
                        ':slot_code' => (string)$slot['slot_code'],
                        ':player_id' => (int)$slot['player_id'],
                        ':created_by' => $createdBy,
                    ]);
                }
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function getSubstitutions(int $matchId): array {
        $stmt = $this->pdo->prepare("
            SELECT
                ms.*,
                po.name AS player_out_name,
                pi.name AS player_in_name
            FROM match_substitutions ms
            JOIN players po ON po.id = ms.player_out_id
            JOIN players pi ON pi.id = ms.player_in_id
            WHERE ms.match_id = :match_id
            ORDER BY ms.period ASC, ms.clock_seconds ASC, ms.id ASC
        ");
        $stmt->execute([':match_id' => $matchId]);
        return $stmt->fetchAll();
    }

    public function createSubstitution(array $data): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO match_substitutions (
                match_id,
                period,
                clock_seconds,
                minute_display,
                slot_code,
                player_out_id,
                player_in_id,
                source,
                raw_transcript,
                transcript_confidence,
                stt_model_id,
                created_by
            ) VALUES (
                :match_id,
                :period,
                :clock_seconds,
                :minute_display,
                :slot_code,
                :player_out_id,
                :player_in_id,
                :source,
                :raw_transcript,
                :transcript_confidence,
                :stt_model_id,
                :created_by
            )
        ");
        $stmt->execute([
            ':match_id' => (int)$data['match_id'],
            ':period' => (int)$data['period'],
            ':clock_seconds' => (int)$data['clock_seconds'],
            ':minute_display' => (int)$data['minute_display'],
            ':slot_code' => (string)$data['slot_code'],
            ':player_out_id' => (int)$data['player_out_id'],
            ':player_in_id' => (int)$data['player_in_id'],
            ':source' => (string)($data['source'] ?? 'manual'),
            ':raw_transcript' => $data['raw_transcript'] ?? null,
            ':transcript_confidence' => $data['transcript_confidence'] ?? null,
            ':stt_model_id' => $data['stt_model_id'] ?? null,
            ':created_by' => $data['created_by'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function getSubstitutionById(int $matchId, int $substitutionId): ?array {
        $stmt = $this->pdo->prepare("
            SELECT
                ms.*,
                po.name AS player_out_name,
                pi.name AS player_in_name
            FROM match_substitutions ms
            JOIN players po ON po.id = ms.player_out_id
            JOIN players pi ON pi.id = ms.player_in_id
            WHERE ms.match_id = :match_id
              AND ms.id = :id
            LIMIT 1
        ");
        $stmt->execute([
            ':match_id' => $matchId,
            ':id' => $substitutionId,
        ]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getLatestSubstitution(int $matchId): ?array {
        $stmt = $this->pdo->prepare("
            SELECT
                ms.*,
                po.name AS player_out_name,
                pi.name AS player_in_name
            FROM match_substitutions ms
            JOIN players po ON po.id = ms.player_out_id
            JOIN players pi ON pi.id = ms.player_in_id
            WHERE ms.match_id = :match_id
            ORDER BY ms.id DESC
            LIMIT 1
        ");
        $stmt->execute([':match_id' => $matchId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function deleteSubstitution(int $matchId, int $substitutionId): void {
        $stmt = $this->pdo->prepare("
            DELETE FROM match_substitutions
            WHERE match_id = :match_id
              AND id = :id
        ");
        $stmt->execute([
            ':match_id' => $matchId,
            ':id' => $substitutionId,
        ]);
    }

    public function addSubstitutionEvent(
        int $matchId,
        int $minute,
        int $period,
        int $playerInId,
        int $substitutionId,
        string $description
    ): void {
        $eventDescription = '[[sub:' . $substitutionId . ']] ' . trim($description);
        $this->addEvent($matchId, $minute, 'sub', $playerInId, $eventDescription, $period);
    }

    public function deleteSubstitutionEventBySubstitutionId(int $matchId, int $substitutionId): void {
        $stmt = $this->pdo->prepare("
            DELETE FROM match_events
            WHERE match_id = :match_id
              AND type = 'sub'
              AND description LIKE :description_marker
        ");
        $stmt->execute([
            ':match_id' => $matchId,
            ':description_marker' => '[[sub:' . $substitutionId . ']]%',
        ]);
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
        $periodSeconds = []; // accumulated seconds per period id

        foreach ($whistles as $w) {
            $period = (int)$w['period'];
            $time = strtotime($w['created_at']);

            if ($w['description'] === 'start_period') {
                $isPlaying = true;
                $lastStartTime = $time;
                $currentPeriod = $period;
                if (!isset($periodSeconds[$period])) {
                    $periodSeconds[$period] = 0;
                }
            } elseif ($w['description'] === 'end_period') {
                if ($isPlaying && $lastStartTime) {
                    $delta = $time - $lastStartTime;
                    $totalSeconds += $delta;
                    $stopPeriod = $currentPeriod > 0 ? $currentPeriod : $period;
                    if (!isset($periodSeconds[$stopPeriod])) {
                        $periodSeconds[$stopPeriod] = 0;
                    }
                    $periodSeconds[$stopPeriod] += $delta;
                }
                $isPlaying = false;
                $lastStartTime = null;
            }
        }

        if ($isPlaying && $lastStartTime) {
            $delta = time() - $lastStartTime;
            $totalSeconds += $delta;
            if (!isset($periodSeconds[$currentPeriod])) {
                $periodSeconds[$currentPeriod] = 0;
            }
            $periodSeconds[$currentPeriod] += $delta;
        }

        $currentPeriodSeconds = $currentPeriod > 0 ? (int)($periodSeconds[$currentPeriod] ?? 0) : 0;

        return [
            'is_playing' => $isPlaying,
            'current_period' => $currentPeriod,
            'total_minutes' => floor($totalSeconds / 60),
            'total_seconds' => $totalSeconds,
            'current_period_seconds' => $currentPeriodSeconds,
            'current_period_minutes' => (int)floor($currentPeriodSeconds / 60),
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

    // --- Voice command logs ---

    public function createVoiceCommandLog(array $data): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO match_voice_command_logs (
                match_id, user_id, period, clock_seconds, audio_duration_ms,
                stt_model_id, raw_transcript, normalized_transcript, parsed_json,
                status, error_code, created_at
            ) VALUES (
                :match_id, :user_id, :period, :clock_seconds, :audio_duration_ms,
                :stt_model_id, :raw_transcript, :normalized_transcript, :parsed_json,
                :status, :error_code, CURRENT_TIMESTAMP
            )"
        );

        $stmt->execute([
            ':match_id' => (int)$data['match_id'],
            ':user_id' => (int)$data['user_id'],
            ':period' => isset($data['period']) ? (int)$data['period'] : null,
            ':clock_seconds' => isset($data['clock_seconds']) ? (int)$data['clock_seconds'] : null,
            ':audio_duration_ms' => isset($data['audio_duration_ms']) ? (int)$data['audio_duration_ms'] : null,
            ':stt_model_id' => $data['stt_model_id'] ?? null,
            ':raw_transcript' => $data['raw_transcript'] ?? null,
            ':normalized_transcript' => $data['normalized_transcript'] ?? null,
            ':parsed_json' => $data['parsed_json'] ?? null,
            ':status' => (string)($data['status'] ?? 'error'),
            ':error_code' => $data['error_code'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function updateVoiceCommandLogStatus(int $logId, string $status, ?string $errorCode = null): void {
        $stmt = $this->pdo->prepare(
            "UPDATE match_voice_command_logs
             SET status = :status, error_code = :error_code
             WHERE id = :id"
        );
        $stmt->execute([
            ':id' => $logId,
            ':status' => $status,
            ':error_code' => $errorCode,
        ]);
    }

    // --- Player name aliases ---

    public function getAliasesForTeam(int $teamId): array {
        $stmt = $this->pdo->prepare(
            "SELECT id, team_id, player_id, alias, normalized_alias
             FROM player_name_aliases
             WHERE team_id = :team_id
             ORDER BY player_id, alias"
        );
        $stmt->execute([':team_id' => $teamId]);
        return $stmt->fetchAll();
    }

    public function addPlayerAlias(int $teamId, int $playerId, string $alias, string $normalizedAlias): int {
        $stmt = $this->pdo->prepare(
            "INSERT OR IGNORE INTO player_name_aliases (team_id, player_id, alias, normalized_alias)
             VALUES (:team_id, :player_id, :alias, :normalized_alias)"
        );
        $stmt->execute([
            ':team_id' => $teamId,
            ':player_id' => $playerId,
            ':alias' => $alias,
            ':normalized_alias' => $normalizedAlias,
        ]);
        return (int)$this->pdo->lastInsertId();
    }
}
