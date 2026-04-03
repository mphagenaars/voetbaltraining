<?php
declare(strict_types=1);

/**
 * Validates LLM-interpreted voice events against the actual live match state.
 *
 * The LLM does the interpretation (intent, name matching, structuring).
 * This class only checks: are the referenced players/IDs valid given the
 * current field/bench/match state?
 */
class VoiceCommandValidator {
    private const AUTO_ACCEPT_THRESHOLD = 0.90;
    private const CONFIRM_THRESHOLD = 0.75;

    /**
     * Validate an array of LLM-produced events against live match state.
     *
     * @param array $events        Events from LLM: [{type, player_in_id, player_out_id, confidence, ...}, ...]
     * @param array $fieldPlayers  Active lineup: [{player_id, player_name, number, slot_code}, ...]
     * @param array $benchPlayers  Bench: [{player_id, player_name, number}, ...]
     * @return array{events: array, requires_confirmation: bool, reason: ?string}
     */
    public function validate(array $events, array $fieldPlayers, array $benchPlayers): array {
        if (empty($events)) {
            return [
                'events' => [],
                'requires_confirmation' => false,
                'reason' => 'Geen events herkend.',
            ];
        }

        $fieldById = $this->indexById($fieldPlayers);
        $benchById = $this->indexById($benchPlayers);
        $allById = $fieldById + $benchById;

        $validated = [];
        $minConfidence = 1.0;
        $hasIssues = false;

        foreach ($events as $event) {
            if (!is_array($event) || empty($event['type'])) {
                continue;
            }

            $type = (string)$event['type'];
            $confidence = (float)($event['confidence'] ?? 0.5);
            $minConfidence = min($minConfidence, $confidence);

            $validationResult = match ($type) {
                'substitution' => $this->validateSubstitution($event, $fieldById, $benchById),
                'goal' => $this->validatePlayerOnField($event, 'player_id', $fieldById, $allById),
                'card' => $this->validatePlayerOnField($event, 'player_id', $fieldById, $allById),
                'chance' => $this->validatePlayerOnField($event, 'player_id', $fieldById, $allById),
                'note' => $this->validateNote($event),
                default => ['valid' => false, 'issue' => 'Onbekend event-type: ' . $type],
            };

            $event['_valid'] = $validationResult['valid'];
            $event['_issue'] = $validationResult['issue'] ?? null;

            if (!$validationResult['valid']) {
                $hasIssues = true;
            }

            $validated[] = $event;
        }

        $requiresConfirmation = $hasIssues || $minConfidence < self::AUTO_ACCEPT_THRESHOLD;

        $reason = null;
        if ($hasIssues) {
            $issues = array_filter(array_column($validated, '_issue'));
            $reason = !empty($issues) ? implode(' ', $issues) : 'Validatieproblemen gevonden.';
        } elseif ($minConfidence < self::CONFIRM_THRESHOLD) {
            $reason = 'Lage herkenningszekerheid.';
        } elseif ($requiresConfirmation) {
            $reason = 'Bevestig de voorgestelde events.';
        }

        return [
            'events' => $validated,
            'requires_confirmation' => $requiresConfirmation,
            'reason' => $reason,
        ];
    }

    private function validateSubstitution(array $event, array $fieldById, array $benchById): array {
        $playerInId = (int)($event['player_in_id'] ?? 0);
        $playerOutId = (int)($event['player_out_id'] ?? 0);

        if ($playerInId <= 0 || $playerOutId <= 0) {
            return ['valid' => false, 'issue' => 'Wissel: speler-ID ontbreekt.'];
        }

        if ($playerInId === $playerOutId) {
            return ['valid' => false, 'issue' => 'Wissel: speler IN en UIT zijn dezelfde.'];
        }

        // player_out should be on field
        if (!isset($fieldById[$playerOutId])) {
            $name = (string)($event['player_out_name'] ?? 'id=' . $playerOutId);
            return ['valid' => false, 'issue' => $name . ' staat niet op het veld.'];
        }

        // player_in should be on bench (or at least not already on field)
        if (isset($fieldById[$playerInId])) {
            $name = (string)($event['player_in_name'] ?? 'id=' . $playerInId);
            return ['valid' => false, 'issue' => $name . ' staat al op het veld.'];
        }

        if (!isset($benchById[$playerInId])) {
            $name = (string)($event['player_in_name'] ?? 'id=' . $playerInId);
            return ['valid' => false, 'issue' => $name . ' staat niet op de bank.'];
        }

        return ['valid' => true];
    }

    private function validatePlayerOnField(array $event, string $idField, array $fieldById, array $allById): array {
        $playerId = (int)($event[$idField] ?? 0);

        // Notes and events without a player are always valid
        if ($playerId <= 0) {
            return ['valid' => true];
        }

        // Player should at least exist in the match
        if (!isset($allById[$playerId])) {
            $name = (string)($event['player_name'] ?? 'id=' . $playerId);
            return ['valid' => false, 'issue' => $name . ' doet niet mee in deze wedstrijd.'];
        }

        return ['valid' => true];
    }

    private function validateNote(array $event): array {
        $text = trim((string)($event['text'] ?? ''));
        if ($text === '') {
            return ['valid' => false, 'issue' => 'Notitie is leeg.'];
        }
        return ['valid' => true];
    }

    /**
     * @return array<int, array> indexed by player_id
     */
    private function indexById(array $players): array {
        $map = [];
        foreach ($players as $p) {
            if (!is_array($p)) {
                continue;
            }
            $id = (int)($p['player_id'] ?? 0);
            if ($id > 0) {
                $map[$id] = $p;
            }
        }
        return $map;
    }
}
