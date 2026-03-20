<?php
declare(strict_types=1);

class MatchTactic extends Model {
    protected string $table = 'match_tactics';
    public const CONTEXT_MATCH = 'match';
    public const CONTEXT_TEAM = 'team';

    public function getForMatch(int $matchId): array {
        $stmt = $this->pdo->prepare(
            "SELECT mt.*
             FROM match_tactics mt
             WHERE mt.match_id = :match_id
               AND mt.context_type = :context_type
             ORDER BY mt.sort_order ASC, mt.id ASC"
        );
        $stmt->execute([
            ':match_id' => $matchId,
            ':context_type' => self::CONTEXT_MATCH,
        ]);
        return $stmt->fetchAll();
    }

    public function getForTeam(int $teamId): array {
        $stmt = $this->pdo->prepare(
            "SELECT
                mt.*,
                m.opponent AS match_opponent,
                m.date AS match_date,
                CASE
                    WHEN mt.context_type = :context_match
                        THEN ('Wedstrijd: ' || COALESCE(m.opponent, 'Onbekend'))
                    ELSE 'Fictief'
                END AS source_label
             FROM match_tactics mt
             LEFT JOIN matches m ON m.id = mt.match_id
             WHERE mt.team_id = :team_id
             ORDER BY
                CASE WHEN mt.context_type = :context_team THEN 0 ELSE 1 END ASC,
                COALESCE(mt.updated_at, mt.created_at) DESC,
                mt.id DESC"
        );
        $stmt->execute([
            ':team_id' => $teamId,
            ':context_match' => self::CONTEXT_MATCH,
            ':context_team' => self::CONTEXT_TEAM,
        ]);
        return $stmt->fetchAll();
    }

    public function getByIdForMatch(int $id, int $matchId): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM match_tactics
             WHERE id = :id
               AND match_id = :match_id
               AND context_type = :context_type
             LIMIT 1"
        );
        $stmt->execute([
            ':id' => $id,
            ':match_id' => $matchId,
            ':context_type' => self::CONTEXT_MATCH,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getByIdForTeam(int $id, int $teamId): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT
                mt.*,
                m.opponent AS match_opponent,
                m.date AS match_date,
                CASE
                    WHEN mt.context_type = :context_match
                        THEN ('Wedstrijd: ' || COALESCE(m.opponent, 'Onbekend'))
                    ELSE 'Fictief'
                END AS source_label
             FROM match_tactics mt
             LEFT JOIN matches m ON m.id = mt.match_id
             WHERE mt.id = :id
               AND mt.team_id = :team_id
             LIMIT 1"
        );
        $stmt->execute([
            ':id' => $id,
            ':team_id' => $teamId,
            ':context_match' => self::CONTEXT_MATCH,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getNextSortOrder(int $matchId): int {
        $teamId = $this->resolveTeamIdForMatch($matchId);
        if ($teamId === null) {
            return 1;
        }

        return $this->getNextSortOrderForContext($teamId, self::CONTEXT_MATCH, $matchId);
    }

    public function getNextSortOrderForTeamContext(int $teamId): int {
        return $this->getNextSortOrderForContext($teamId, self::CONTEXT_TEAM, null);
    }

    private function getNextSortOrderForContext(int $teamId, string $contextType, ?int $matchId): int {
        $sql = "SELECT COALESCE(MAX(sort_order), 0) + 1
                FROM match_tactics
                WHERE team_id = :team_id
                  AND context_type = :context_type";
        $params = [
            ':team_id' => $teamId,
            ':context_type' => $contextType,
        ];

        if ($matchId === null) {
            $sql .= " AND match_id IS NULL";
        } else {
            $sql .= " AND match_id = :match_id";
            $params[':match_id'] = $matchId;
        }

        $stmt = $this->pdo->prepare(
            $sql
        );
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function create(
        int $matchId,
        string $title,
        string $phase,
        ?int $minute,
        string $fieldType,
        ?string $drawingData,
        int $sortOrder,
        ?int $createdBy
    ): int {
        $teamId = $this->resolveTeamIdForMatch($matchId);
        if ($teamId === null) {
            throw new RuntimeException('Match niet gevonden voor tactieksituatie.');
        }

        return $this->createForContext(
            $teamId,
            $matchId,
            self::CONTEXT_MATCH,
            $title,
            $phase,
            $minute,
            $fieldType,
            $drawingData,
            $sortOrder,
            $createdBy
        );
    }

    public function createForTeam(
        int $teamId,
        string $title,
        string $phase,
        ?int $minute,
        string $fieldType,
        ?string $drawingData,
        int $sortOrder,
        ?int $createdBy
    ): int {
        return $this->createForContext(
            $teamId,
            null,
            self::CONTEXT_TEAM,
            $title,
            $phase,
            $minute,
            $fieldType,
            $drawingData,
            $sortOrder,
            $createdBy
        );
    }

    private function createForContext(
        int $teamId,
        ?int $matchId,
        string $contextType,
        string $title,
        string $phase,
        ?int $minute,
        string $fieldType,
        ?string $drawingData,
        int $sortOrder,
        ?int $createdBy
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO match_tactics (
                team_id,
                match_id,
                context_type,
                title,
                phase,
                minute,
                field_type,
                drawing_data,
                sort_order,
                created_by
            ) VALUES (
                :team_id,
                :match_id,
                :context_type,
                :title,
                :phase,
                :minute,
                :field_type,
                :drawing_data,
                :sort_order,
                :created_by
            )"
        );
        $stmt->execute([
            ':team_id' => $teamId,
            ':match_id' => $matchId,
            ':context_type' => $contextType,
            ':title' => $title,
            ':phase' => $phase,
            ':minute' => $minute,
            ':field_type' => $fieldType,
            ':drawing_data' => $drawingData,
            ':sort_order' => $sortOrder,
            ':created_by' => $createdBy,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function updateTactic(
        int $id,
        int $matchId,
        string $title,
        string $phase,
        ?int $minute,
        string $fieldType,
        ?string $drawingData
    ): void {
        $stmt = $this->pdo->prepare(
            "UPDATE match_tactics
             SET title = :title,
                 phase = :phase,
                 minute = :minute,
                 field_type = :field_type,
                 drawing_data = :drawing_data,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND match_id = :match_id
               AND context_type = :context_type"
        );
        $stmt->execute([
            ':id' => $id,
            ':match_id' => $matchId,
            ':context_type' => self::CONTEXT_MATCH,
            ':title' => $title,
            ':phase' => $phase,
            ':minute' => $minute,
            ':field_type' => $fieldType,
            ':drawing_data' => $drawingData,
        ]);
    }

    public function updateForTeam(
        int $id,
        int $teamId,
        string $title,
        string $phase,
        ?int $minute,
        string $fieldType,
        ?string $drawingData
    ): void {
        $stmt = $this->pdo->prepare(
            "UPDATE match_tactics
             SET title = :title,
                 phase = :phase,
                 minute = :minute,
                 field_type = :field_type,
                 drawing_data = :drawing_data,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND team_id = :team_id"
        );
        $stmt->execute([
            ':id' => $id,
            ':team_id' => $teamId,
            ':title' => $title,
            ':phase' => $phase,
            ':minute' => $minute,
            ':field_type' => $fieldType,
            ':drawing_data' => $drawingData,
        ]);
    }

    public function deleteForMatch(int $id, int $matchId): void {
        $stmt = $this->pdo->prepare(
            "DELETE FROM match_tactics
             WHERE id = :id
               AND match_id = :match_id
               AND context_type = :context_type"
        );
        $stmt->execute([
            ':id' => $id,
            ':match_id' => $matchId,
            ':context_type' => self::CONTEXT_MATCH,
        ]);
    }

    public function deleteForTeam(int $id, int $teamId): void {
        $stmt = $this->pdo->prepare(
            "DELETE FROM match_tactics
             WHERE id = :id
               AND team_id = :team_id"
        );
        $stmt->execute([
            ':id' => $id,
            ':team_id' => $teamId,
        ]);
    }

    private function resolveTeamIdForMatch(int $matchId): ?int {
        $stmt = $this->pdo->prepare(
            "SELECT team_id FROM matches WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $matchId]);
        $teamId = $stmt->fetchColumn();
        if ($teamId === false) {
            return null;
        }
        return (int)$teamId;
    }
}
