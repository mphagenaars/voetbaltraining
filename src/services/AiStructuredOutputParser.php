<?php
declare(strict_types=1);

class AiStructuredOutputParser {
    public function parse(string $content): array {
        $warnings = [];

        $exerciseBlock = $this->extractBlock($content, 'exercise_json');
        $drawingBlock = $this->extractBlock($content, 'drawing_json');

        $exerciseRaw = null;
        $drawingRaw = null;

        if ($exerciseBlock !== null) {
            $parsed = json_decode($exerciseBlock, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                $exerciseRaw = $parsed;
            } else {
                $warnings[] = 'Een deel van het antwoord was niet goed leesbaar.';
            }
        }

        if ($drawingBlock !== null) {
            $parsed = json_decode($drawingBlock, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                $drawingRaw = $parsed;
            } else {
                $warnings[] = 'De tekening uit het antwoord was niet goed leesbaar.';
            }
        }

        $freeText = preg_replace('/```exercise_json\s*.*?```/is', '', $content);
        $freeText = preg_replace('/```drawing_json\s*.*?```/is', '', (string)$freeText);
        $freeText = trim((string)$freeText);

        return [
            'chat_text' => $freeText,
            'exercise_raw' => $exerciseRaw,
            'drawing_raw' => $drawingRaw,
            'warnings' => $warnings,
        ];
    }

    private function extractBlock(string $content, string $tag): ?string {
        $pattern = '/```' . preg_quote($tag, '/') . '\s*(.*?)```/is';
        if (preg_match($pattern, $content, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }
}
