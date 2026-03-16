<?php
declare(strict_types=1);

class MatchTactic extends Model {
    protected string $table = 'match_tactics';

    public function getForMatch(int $matchId): array {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM match_tactics WHERE match_id = :match_id ORDER BY sort_order ASC, id ASC"
        );
        $stmt->execute([':match_id' => $matchId]);
        return $stmt->fetchAll();
    }

    public function getByIdForMatch(int $id, int $matchId): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM match_tactics WHERE id = :id AND match_id = :match_id LIMIT 1"
        );
        $stmt->execute([
            ':id' => $id,
            ':match_id' => $matchId,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getNextSortOrder(int $matchId): int {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(MAX(sort_order), 0) + 1 FROM match_tactics WHERE match_id = :match_id"
        );
        $stmt->execute([':match_id' => $matchId]);
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
        $stmt = $this->pdo->prepare(
            "INSERT INTO match_tactics (
                match_id,
                title,
                phase,
                minute,
                field_type,
                drawing_data,
                sort_order,
                created_by
            ) VALUES (
                :match_id,
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
            ':match_id' => $matchId,
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
             WHERE id = :id AND match_id = :match_id"
        );
        $stmt->execute([
            ':id' => $id,
            ':match_id' => $matchId,
            ':title' => $title,
            ':phase' => $phase,
            ':minute' => $minute,
            ':field_type' => $fieldType,
            ':drawing_data' => $drawingData,
        ]);
    }

    public function deleteForMatch(int $id, int $matchId): void {
        $stmt = $this->pdo->prepare(
            "DELETE FROM match_tactics WHERE id = :id AND match_id = :match_id"
        );
        $stmt->execute([
            ':id' => $id,
            ':match_id' => $matchId,
        ]);
    }
}
