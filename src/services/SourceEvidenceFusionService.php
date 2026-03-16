<?php
declare(strict_types=1);

/**
 * Merges textual source facts (from transcript/chapters/description analysis)
 * with visual facts (from frame-based vision analysis) into a single combined
 * source facts structure.
 *
 * Design principles:
 * - Every fact retains its provenance (transcript, chapters, description, frame@timestamp).
 * - Contradictions are flagged explicitly, never silently overwritten.
 * - The output schema is a superset of the original source_facts schema so that
 *   all downstream consumers (generation prompt, judge, revision) can work on it.
 */
class SourceEvidenceFusionService {

    /**
     * Merge textual source facts and visual facts into combined_source_facts.
     *
     * @param array      $textFacts   Normalized source_facts from extractSourceFacts()
     * @param array|null $visualFacts Normalized visual_facts from VisualEvidenceService, or null
     * @return array Combined source facts with provenance and contradiction markers
     */
    public function fuse(array $textFacts, ?array $visualFacts): array {
        // If no visual facts available, return text facts with provenance annotations
        if ($visualFacts === null || empty($visualFacts)) {
            return $this->annotateTextOnly($textFacts);
        }

        $combined = [];

        // 1. Summary — text takes precedence, visual supplements
        $combined['summary'] = $textFacts['summary'] ?? '';

        // 2. Setup — merge with contradiction detection
        $combined['setup'] = $this->fuseSetup(
            $textFacts['setup'] ?? [],
            $visualFacts['setup'] ?? []
        );

        // 3. Sequence — keep textual sequence, enrich with visual sequence
        $combined['sequence'] = $this->fuseSequence(
            $textFacts['sequence'] ?? [],
            $visualFacts['sequence'] ?? []
        );

        // 4. Simple text fields — keep from text
        $combined['rotation'] = $textFacts['rotation'] ?? '';
        $combined['rules'] = $textFacts['rules'] ?? [];
        $combined['coach_cues'] = $textFacts['coach_cues'] ?? [];

        // 5. Recognition points — merge text + visual evidence items as high-certainty facts
        $combined['recognition_points'] = $this->fuseRecognitionPoints(
            $textFacts['recognition_points'] ?? [],
            $visualFacts['evidence_items'] ?? []
        );

        // 6. Missing details — combine text gaps + visual uncertainties
        $combined['missing_details'] = $this->fuseMissingDetails(
            $textFacts['missing_details'] ?? [],
            $visualFacts['uncertainties'] ?? []
        );

        // 7. Confidence — combined assessment
        $combined['confidence'] = $this->fuseConfidence(
            $textFacts['confidence'] ?? 'low',
            $visualFacts['confidence'] ?? 'low'
        );

        // 8. Evidence items — merge with provenance
        $combined['evidence_items'] = $this->fuseEvidenceItems(
            $textFacts['evidence_items'] ?? [],
            $visualFacts['evidence_items'] ?? []
        );

        // 9. Contradictions — detected during setup/sequence fusion
        $combined['contradictions'] = $this->detectContradictions(
            $textFacts,
            $visualFacts
        );

        // 10. Patterns from visual analysis (new field, not in text facts)
        $combined['visual_patterns'] = $visualFacts['patterns'] ?? [
            'passing_directions' => null,
            'running_lines' => null,
            'rotation_visible' => null,
        ];

        // 11. Provenance metadata
        $combined['fusion_meta'] = [
            'has_text' => true,
            'has_visual' => true,
            'text_confidence' => $textFacts['confidence'] ?? 'low',
            'visual_confidence' => $visualFacts['confidence'] ?? 'low',
        ];

        return $combined;
    }

    /**
     * Annotate text-only facts with provenance info (no visual data available).
     */
    private function annotateTextOnly(array $textFacts): array {
        $combined = $textFacts;
        $combined['contradictions'] = [];
        $combined['visual_patterns'] = [
            'passing_directions' => null,
            'running_lines' => null,
            'rotation_visible' => null,
        ];
        $combined['fusion_meta'] = [
            'has_text' => true,
            'has_visual' => false,
            'text_confidence' => $textFacts['confidence'] ?? 'low',
            'visual_confidence' => 'none',
        ];
        return $combined;
    }

    /**
     * Merge setup sections from text and visual analysis.
     * Text is the baseline; visual enriches where text is empty.
     * Contradictions are detected separately.
     */
    private function fuseSetup(array $textSetup, array $visualSetup): array {
        $fused = [
            'starting_shape' => $this->pickBestString(
                $textSetup['starting_shape'] ?? '',
                $visualSetup['starting_shape'] ?? ''
            ),
            'player_structure' => $textSetup['player_structure'] ?? '',
            'area' => $this->pickBestString(
                $textSetup['area'] ?? '',
                $this->buildVisualArea($visualSetup)
            ),
            'equipment' => $this->fuseStringLists(
                $textSetup['equipment'] ?? [],
                $visualSetup['equipment'] ?? [],
                'transcript',
                'visual'
            ),
            // New fields from visual analysis
            'field_shape' => $visualSetup['field_shape'] ?? null,
            'field_markings' => $visualSetup['field_markings'] ?? null,
            'estimated_dimensions' => $visualSetup['estimated_dimensions'] ?? null,
            'player_count' => $this->pickBestString(
                '', // text facts don't have explicit player_count
                $visualSetup['player_count'] ?? ''
            ),
            'player_roles' => $this->fuseStringLists(
                [], // text facts don't have explicit player_roles
                $visualSetup['player_roles'] ?? [],
                'transcript',
                'visual'
            ),
        ];

        return $fused;
    }

    /**
     * Build a visual area description from visual setup fields.
     */
    private function buildVisualArea(array $visualSetup): string {
        $parts = [];
        $shape = trim((string)($visualSetup['field_shape'] ?? ''));
        $dims = trim((string)($visualSetup['estimated_dimensions'] ?? ''));

        if ($shape !== '') {
            $parts[] = $shape;
        }
        if ($dims !== '') {
            $parts[] = $dims;
        }

        return implode(', ', $parts);
    }

    /**
     * Merge text sequence (step strings) with visual sequence (frame observations).
     * Returns an enriched sequence where visual observations are appended as
     * provenance-annotated steps.
     */
    private function fuseSequence(array $textSequence, array $visualSequence): array {
        $fused = [];

        // Text steps keep their provenance
        foreach ($textSequence as $step) {
            if (is_string($step) && trim($step) !== '') {
                $fused[] = [
                    'description' => trim($step),
                    'source' => 'transcript',
                ];
            }
        }

        // Visual sequence entries are added with frame provenance
        foreach ($visualSequence as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $action = trim((string)($entry['action'] ?? ''));
            if ($action === '') {
                continue;
            }

            $frame = $entry['frame'] ?? null;
            $timestamp = trim((string)($entry['timestamp'] ?? ''));
            $source = 'visual';
            if ($frame !== null || $timestamp !== '') {
                $source = 'frame';
                if ($timestamp !== '') {
                    $source .= '@' . $timestamp;
                } elseif (is_int($frame)) {
                    $source .= '#' . $frame;
                }
            }

            $desc = $action;
            $movement = trim((string)($entry['movement_patterns'] ?? ''));
            if ($movement !== '') {
                $desc .= ' (' . $movement . ')';
            }

            $fused[] = [
                'description' => $desc,
                'source' => $source,
            ];
        }

        return $fused;
    }

    /**
     * Merge recognition points from text facts with high-certainty visual evidence.
     */
    private function fuseRecognitionPoints(array $textPoints, array $visualEvidenceItems): array {
        $fused = [];
        foreach ($textPoints as $point) {
            if (is_string($point) && trim($point) !== '') {
                $fused[] = [
                    'point' => trim($point),
                    'source' => 'transcript',
                ];
            }
        }

        // Add high/medium certainty visual evidence as recognition points
        foreach ($visualEvidenceItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $certainty = strtolower(trim((string)($item['certainty'] ?? 'low')));
            if ($certainty === 'low') {
                continue;
            }
            $fact = trim((string)($item['fact'] ?? ''));
            if ($fact === '') {
                continue;
            }

            $frame = $item['frame'] ?? null;
            $source = 'visual';
            if (is_int($frame)) {
                $source = 'frame#' . $frame;
            }

            $fused[] = [
                'point' => $fact,
                'source' => $source,
            ];
        }

        return array_slice($fused, 0, 8); // cap at 8 recognition points
    }

    /**
     * Combine textual missing details with visual uncertainties.
     */
    private function fuseMissingDetails(array $textMissing, array $visualUncertainties): array {
        $all = [];
        foreach ($textMissing as $item) {
            if (is_string($item) && trim($item) !== '') {
                $all[] = trim($item);
            }
        }
        foreach ($visualUncertainties as $item) {
            if (is_string($item) && trim($item) !== '') {
                $all[] = '[visueel] ' . trim($item);
            }
        }
        return array_values(array_unique($all));
    }

    /**
     * Compute combined confidence from text and visual confidence levels.
     *
     * Logic:
     * - If both high: high
     * - If one high + one medium: high
     * - If both medium: medium
     * - If one medium + one low: medium
     * - Otherwise: low
     */
    private function fuseConfidence(string $textConf, string $visualConf): string {
        $levels = ['high' => 3, 'medium' => 2, 'low' => 1];
        $textScore = $levels[$textConf] ?? 1;
        $visualScore = $levels[$visualConf] ?? 1;

        $avg = ($textScore + $visualScore) / 2.0;

        if ($avg >= 2.5) {
            return 'high';
        }
        if ($avg >= 1.5) {
            return 'medium';
        }
        return 'low';
    }

    /**
     * Merge evidence items from text and visual, preserving provenance.
     */
    private function fuseEvidenceItems(array $textItems, array $visualItems): array {
        $fused = [];

        foreach ($textItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $fact = trim((string)($item['fact'] ?? ''));
            if ($fact === '') {
                continue;
            }
            $fused[] = [
                'fact' => $fact,
                'source' => $item['source'] ?? 'transcript',
                'snippet' => $item['snippet'] ?? '',
                'origin' => 'text',
            ];
        }

        foreach ($visualItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $fact = trim((string)($item['fact'] ?? ''));
            if ($fact === '') {
                continue;
            }
            $frame = $item['frame'] ?? null;
            $certainty = $item['certainty'] ?? 'medium';
            $source = 'visual';
            if (is_int($frame)) {
                $source = 'frame#' . $frame;
            }
            $fused[] = [
                'fact' => $fact,
                'source' => $source,
                'snippet' => '',
                'origin' => 'visual',
                'certainty' => $certainty,
            ];
        }

        return $fused;
    }

    /**
     * Detect contradictions between text and visual facts.
     * Returns a list of contradiction descriptions.
     */
    private function detectContradictions(array $textFacts, array $visualFacts): array {
        $contradictions = [];

        $textSetup = $textFacts['setup'] ?? [];
        $visualSetup = $visualFacts['setup'] ?? [];

        // Equipment contradiction: items in one but not the other when both have items
        $textEquip = array_map('strtolower', $textSetup['equipment'] ?? []);
        $visualEquip = array_map('strtolower', $visualSetup['equipment'] ?? []);

        if (!empty($textEquip) && !empty($visualEquip)) {
            $textOnly = array_diff($textEquip, $visualEquip);
            $visualOnly = array_diff($visualEquip, $textEquip);

            if (!empty($textOnly) && !empty($visualOnly)) {
                $contradictions[] = [
                    'field' => 'setup.equipment',
                    'text_value' => implode(', ', $textOnly),
                    'visual_value' => implode(', ', $visualOnly),
                    'description' => 'Materiaal verschilt: transcript noemt ' . implode(', ', $textOnly) .
                        ' maar visueel zijn ' . implode(', ', $visualOnly) . ' zichtbaar.',
                    'severity' => 'info',
                ];
            }
        }

        // Player count contradiction
        $textPlayerCount = $this->extractNumberFromString($textSetup['player_structure'] ?? '');
        $visualPlayerCount = $this->extractNumberFromString($visualSetup['player_count'] ?? '');

        if ($textPlayerCount !== null && $visualPlayerCount !== null && $textPlayerCount !== $visualPlayerCount) {
            $contradictions[] = [
                'field' => 'setup.player_count',
                'text_value' => (string)$textPlayerCount,
                'visual_value' => (string)$visualPlayerCount,
                'description' => 'Speleraantal verschilt: transcript suggereert ' . $textPlayerCount .
                    ', visueel zijn ' . $visualPlayerCount . ' spelers zichtbaar.',
                'severity' => 'warning',
            ];
        }

        // Rotation contradiction: text says rotation, visual says not (or vice versa)
        $textRotation = trim((string)($textFacts['rotation'] ?? ''));
        $visualRotation = trim((string)(($visualFacts['patterns'] ?? [])['rotation_visible'] ?? ''));

        if ($textRotation !== '' && $visualRotation !== '') {
            $textHasRotation = $this->mentionsRotation($textRotation);
            $visualHasRotation = $this->mentionsRotation($visualRotation);

            if ($textHasRotation !== $visualHasRotation && $textHasRotation !== null && $visualHasRotation !== null) {
                $contradictions[] = [
                    'field' => 'rotation',
                    'text_value' => $textRotation,
                    'visual_value' => $visualRotation,
                    'description' => 'Rotatie/doorwisseling: transcript zegt "' . $textRotation .
                        '", visueel: "' . $visualRotation . '".',
                    'severity' => 'info',
                ];
            }
        }

        return $contradictions;
    }

    /**
     * Pick the best non-empty string. Prefers $primary, falls back to $secondary.
     */
    private function pickBestString(string $primary, string $secondary): string {
        $p = trim($primary);
        $s = trim($secondary);

        if ($p !== '') {
            return $p;
        }
        return $s;
    }

    /**
     * Merge two string lists, deduplicating case-insensitively.
     */
    private function fuseStringLists(array $listA, array $listB, string $sourceA, string $sourceB): array {
        $seen = [];
        $fused = [];

        foreach ($listA as $item) {
            $item = trim((string)$item);
            $key = strtolower($item);
            if ($item !== '' && !isset($seen[$key])) {
                $seen[$key] = true;
                $fused[] = $item;
            }
        }

        foreach ($listB as $item) {
            $item = trim((string)$item);
            $key = strtolower($item);
            if ($item !== '' && !isset($seen[$key])) {
                $seen[$key] = true;
                $fused[] = $item;
            }
        }

        return $fused;
    }

    /**
     * Try to extract a leading number from a string like "6 spelers" or "4 vs 2".
     */
    private function extractNumberFromString(string $value): ?int {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^(\d+)/', $value, $m)) {
            return (int)$m[1];
        }
        return null;
    }

    /**
     * Detect whether a string mentions rotation positively or negatively.
     * Returns true for positive, false for negative, null if unclear.
     */
    private function mentionsRotation(string $text): ?bool {
        $text = strtolower($text);
        $positive = ['ja', 'yes', 'wisselen', 'roteren', 'doorschuiven', 'doorwisselen', 'rotation'];
        $negative = ['nee', 'no', 'geen', 'niet'];

        foreach ($negative as $word) {
            if (str_contains($text, $word)) {
                return false;
            }
        }
        foreach ($positive as $word) {
            if (str_contains($text, $word)) {
                return true;
            }
        }
        return null;
    }
}
