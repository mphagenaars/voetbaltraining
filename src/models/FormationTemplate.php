<?php
declare(strict_types=1);

class FormationTemplate extends Model {
    protected string $table = 'formation_templates';

    /**
     * Get all templates available to a team: own + shared.
     */
    public function getForTeam(int $teamId, ?string $format = null): array {
        $normalizedFormat = Team::normalizeMatchFormat((string)$format);
        $sql = "SELECT *
                FROM formation_templates
                WHERE (team_id = :team_id OR is_shared = 1)";
        $params = [':team_id' => $teamId];

        if ($normalizedFormat !== '') {
            $sql .= " AND format = :format";
            $params[':format'] = $normalizedFormat;
        }

        $sql .= " ORDER BY is_shared ASC, name ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get a template by id, but only if accessible to the given team.
     */
    public function getByIdForTeam(int $id, int $teamId): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM formation_templates
             WHERE id = :id AND (team_id = :team_id OR is_shared = 1)
             LIMIT 1"
        );
        $stmt->execute([':id' => $id, ':team_id' => $teamId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Create a new formation template.
     * @return int The new template id
     */
    public function create(?int $teamId, string $name, array $positions, bool $isShared, ?int $createdBy): int {
        $format = self::deriveFormat(count($positions));
        $positionsJson = json_encode($positions, JSON_UNESCAPED_UNICODE);

        $stmt = $this->pdo->prepare(
            "INSERT INTO formation_templates (team_id, name, format, positions, is_shared, created_by)
             VALUES (:team_id, :name, :format, :positions, :is_shared, :created_by)"
        );
        $stmt->execute([
            ':team_id' => $teamId,
            ':name' => $name,
            ':format' => $format,
            ':positions' => $positionsJson,
            ':is_shared' => $isShared ? 1 : 0,
            ':created_by' => $createdBy,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update an existing formation template.
     */
    public function update(int $id, string $name, array $positions, bool $isShared): void {
        $format = self::deriveFormat(count($positions));
        $positionsJson = json_encode($positions, JSON_UNESCAPED_UNICODE);

        $stmt = $this->pdo->prepare(
            "UPDATE formation_templates
             SET name = :name, format = :format, positions = :positions, is_shared = :is_shared, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        );
        $stmt->execute([
            ':id' => $id,
            ':name' => $name,
            ':format' => $format,
            ':positions' => $positionsJson,
            ':is_shared' => $isShared ? 1 : 0,
        ]);
    }

    /**
     * Delete a template, but only if owned by the given team (not a global shared one).
     */
    public function deleteForTeam(int $id, int $teamId, bool $allowGlobalSharedDelete = false): bool {
        if ($allowGlobalSharedDelete) {
            $stmt = $this->pdo->prepare(
                "DELETE FROM formation_templates
                 WHERE id = :id
                   AND (
                        team_id = :team_id
                        OR (team_id IS NULL AND is_shared = 1)
                   )"
            );
        } else {
            $stmt = $this->pdo->prepare(
                "DELETE FROM formation_templates WHERE id = :id AND team_id = :team_id"
            );
        }

        $stmt->execute([':id' => $id, ':team_id' => $teamId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Validate positions array. Returns error message or null if valid.
     */
    public static function validatePositions(array $positions): ?string {
        $count = count($positions);
        if ($count < 5 || $count > 11) {
            return 'Een speelwijze moet tussen de 5 en 11 posities bevatten.';
        }

        $seenSlots = [];
        foreach ($positions as $i => $pos) {
            if (!is_array($pos)) {
                return "Positie $i is ongeldig.";
            }
            $slotCode = MatchSlotCode::sanitize((string) ($pos['slot_code'] ?? ''));
            if ($slotCode === '') {
                return "Positie $i mist een geldige positiecode.";
            }
            if (isset($seenSlots[$slotCode])) {
                return "Positiecode '$slotCode' komt meerdere keren voor.";
            }
            $seenSlots[$slotCode] = true;

            $x = (int) ($pos['x'] ?? 0);
            $y = (int) ($pos['y'] ?? 0);
            if ($x < 1 || $x > 99 || $y < 1 || $y > 99) {
                return "Positie '$slotCode' heeft ongeldige coördinaten.";
            }
        }
        return null;
    }

    /**
     * Validate a template name. Returns error message or null if valid.
     */
    public static function validateName(string $name): ?string {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 60) {
            return 'Naam moet tussen 1 en 60 tekens zijn.';
        }
        return null;
    }

    /**
     * Sanitize and normalize a positions array from user input.
     */
    public static function sanitizePositions(array $rawPositions): array {
        $result = [];
        foreach ($rawPositions as $pos) {
            if (!is_array($pos)) {
                continue;
            }
            $result[] = [
                'slot_code' => MatchSlotCode::sanitize((string) ($pos['slot_code'] ?? '')),
                'label' => mb_substr(trim((string) ($pos['label'] ?? '')), 0, 40),
                'x' => max(1, min(99, (int) ($pos['x'] ?? 50))),
                'y' => max(1, min(99, (int) ($pos['y'] ?? 50))),
            ];
        }
        return $result;
    }

    /**
     * Derive the match format string from position count.
     */
    public static function deriveFormat(int $positionCount): string {
        return $positionCount . '-vs-' . $positionCount;
    }
}
