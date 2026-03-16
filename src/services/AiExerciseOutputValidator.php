<?php
declare(strict_types=1);

class AiExerciseOutputValidator {
    private const FIELD_TYPES = ['portrait', 'landscape', 'square'];

    public function validate(array $input, array $options): array {
        $warnings = [];
        $fields = [];

        $teamTasks = $this->normalizeOptionList($options['team_task'] ?? []);
        $objectives = $this->normalizeOptionList($options['objective'] ?? []);
        $actions = $this->normalizeOptionList($options['football_action'] ?? []);

        // Only process and return fields that were actually present in the AI output.
        // This allows partial updates (iterative adjustments) without overwriting
        // existing form values with empty defaults.

        if (array_key_exists('title', $input)) {
            $title = trim((string)($input['title'] ?? ''));
            if ($title === '') {
                $warnings[] = 'De titel ontbrak nog.';
            }
            if ($this->strLength($title) > 100) {
                $title = $this->strSlice($title, 0, 100);
                $warnings[] = 'De titel was te lang en is korter gemaakt.';
            }
            $fields['title'] = $title;
        }

        if (array_key_exists('description', $input)) {
            $description = trim((string)($input['description'] ?? ''));
            if ($description === '') {
                $warnings[] = 'De beschrijving ontbrak nog.';
            }
            $fields['description'] = $description;
        }

        if (array_key_exists('variation', $input)) {
            $fields['variation'] = $this->nullableString($input['variation'] ?? null);
        }
        if (array_key_exists('coach_instructions', $input)) {
            $fields['coach_instructions'] = $this->nullableString($input['coach_instructions'] ?? null);
        }
        if (array_key_exists('source', $input)) {
            $fields['source'] = $this->nullableString($input['source'] ?? null);
        }

        if (array_key_exists('team_task', $input)) {
            $fields['team_task'] = $this->matchSingleOption($this->nullableString($input['team_task'] ?? null), $teamTasks, 'Teamtaak', $warnings);
        }
        if (array_key_exists('objectives', $input)) {
            $fields['objectives'] = $this->matchArrayOptions($input['objectives'] ?? [], $objectives, 'Doelstelling', $warnings);
        }
        if (array_key_exists('actions', $input)) {
            $fields['actions'] = $this->matchArrayOptions($input['actions'] ?? [], $actions, 'Voetbalhandeling', $warnings);
        }

        $hasMin = array_key_exists('min_players', $input);
        $hasMax = array_key_exists('max_players', $input);
        if ($hasMin || $hasMax) {
            $minPlayers = $this->clampInt($input['min_players'] ?? 1, 1, 30);
            $maxPlayers = $this->clampInt($input['max_players'] ?? 30, 1, 30);
            if ($hasMin && $hasMax && $maxPlayers < $minPlayers) {
                [$minPlayers, $maxPlayers] = [$maxPlayers, $minPlayers];
                $warnings[] = 'Het aantal spelers is aangepast zodat het weer klopt.';
            }
            if ($hasMin) {
                $fields['min_players'] = $minPlayers;
            }
            if ($hasMax) {
                $fields['max_players'] = $maxPlayers;
            }
        }

        if (array_key_exists('duration', $input)) {
            $duration = $this->normalizeDuration($input['duration'] ?? null);
            if ($duration === null) {
                $duration = 10;
                $warnings[] = 'De duur was niet duidelijk. Ik heb er 10 minuten van gemaakt.';
            }
            $fields['duration'] = $duration;
        }

        if (array_key_exists('field_type', $input)) {
            $fieldType = strtolower(trim((string)($input['field_type'] ?? 'portrait')));
            if (!in_array($fieldType, self::FIELD_TYPES, true)) {
                $fieldType = 'portrait';
                $warnings[] = 'Het veldtype was niet duidelijk. Ik heb het standaard veld gekozen.';
            }
            $fields['field_type'] = $fieldType;
        }

        $appliedCount = 0;
        foreach ($fields as $value) {
            if (is_array($value)) {
                if (!empty($value)) {
                    $appliedCount++;
                }
                continue;
            }

            if ($value !== null && $value !== '') {
                $appliedCount++;
            }
        }

        return [
            'fields' => $fields,
            'warnings' => $warnings,
            'applied_count' => $appliedCount,
        ];
    }

    private function normalizeOptionList(array $values): array {
        $normalized = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }

            $trimmed = trim($value);
            if ($trimmed === '') {
                continue;
            }

            $normalized[] = $trimmed;
        }

        return array_values(array_unique($normalized));
    }

    private function nullableString(mixed $value): ?string {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string)$value);
        return $trimmed === '' ? null : $trimmed;
    }

    private function matchSingleOption(?string $value, array $allowed, string $label, array &$warnings): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        $exact = $this->findExactCaseInsensitive($value, $allowed);
        if ($exact !== null) {
            return $exact;
        }

        $fuzzy = $this->findFuzzy($value, $allowed);
        if ($fuzzy !== null && $fuzzy['score'] >= 80.0) {
            $warnings[] = "$label is aangepast naar een bekende keuze.";
            return $fuzzy['value'];
        }

        $warnings[] = "$label paste niet goed en is leeg gelaten.";
        return null;
    }

    private function matchArrayOptions(mixed $values, array $allowed, string $label, array &$warnings): array {
        if (!is_array($values)) {
            return [];
        }

        $matches = [];
        foreach ($values as $raw) {
            $value = trim((string)$raw);
            if ($value === '') {
                continue;
            }

            $exact = $this->findExactCaseInsensitive($value, $allowed);
            if ($exact !== null) {
                $matches[] = $exact;
                continue;
            }

            $fuzzy = $this->findFuzzy($value, $allowed);
            if ($fuzzy !== null && $fuzzy['score'] >= 80.0) {
                $matches[] = $fuzzy['value'];
                $warnings[] = "$label is aangepast naar een bekende keuze.";
                continue;
            }

            $warnings[] = "$label paste niet goed en is weggelaten.";
        }

        return array_values(array_unique($matches));
    }

    private function findExactCaseInsensitive(string $needle, array $allowed): ?string {
        $needleNorm = $this->toLower($needle);
        foreach ($allowed as $value) {
            if ($this->toLower($value) === $needleNorm) {
                return $value;
            }
        }

        return null;
    }

    private function findFuzzy(string $needle, array $allowed): ?array {
        $best = null;
        foreach ($allowed as $value) {
            similar_text($this->toLower($needle), $this->toLower($value), $score);
            if ($best === null || $score > $best['score']) {
                $best = ['value' => $value, 'score' => $score];
            }
        }

        return $best;
    }

    private function clampInt(mixed $value, int $min, int $max): int {
        $int = is_numeric($value) ? (int)$value : $min;
        if ($int < $min) {
            return $min;
        }
        if ($int > $max) {
            return $max;
        }
        return $int;
    }

    private function normalizeDuration(mixed $value): ?int {
        if (!is_numeric($value)) {
            return null;
        }

        $duration = (int)round(((int)$value) / 5) * 5;
        if ($duration < 5) {
            $duration = 5;
        }
        if ($duration > 90) {
            $duration = 90;
        }

        return $duration;
    }

    private function toLower(string $value): string {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }

        return strtolower($value);
    }

    private function strLength(string $value): int {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        return strlen($value);
    }

    private function strSlice(string $value, int $start, int $length): string {
        if (function_exists('mb_substr')) {
            return (string)mb_substr($value, $start, $length, 'UTF-8');
        }

        return (string)substr($value, $start, $length);
    }
}
