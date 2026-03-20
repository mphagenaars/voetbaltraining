<?php
declare(strict_types=1);

class MatchTacticInputValidator {
    private const DEFAULT_TITLE = 'Nieuwe situatie';
    private const DEFAULT_PHASE = 'open_play';
    private const DEFAULT_FIELD_TYPE = 'standard_30x42_5';
    private const MAX_TITLE_LENGTH = 120;
    private const MAX_MINUTE = 130;
    private const MAX_DRAWING_DATA_BYTES = 250000;

    /**
     * @return array{
     *     title: string,
     *     phase: string,
     *     minute: ?int,
     *     field_type: string,
     *     drawing_data: ?string
     * }
     */
    public function validateForSave(array $data): array {
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            $title = self::DEFAULT_TITLE;
        }
        if (function_exists('mb_substr')) {
            $title = (string)mb_substr($title, 0, self::MAX_TITLE_LENGTH, 'UTF-8');
        } else {
            $title = substr($title, 0, self::MAX_TITLE_LENGTH);
        }

        $minute = null;
        $minuteRaw = $data['minute'] ?? null;
        if ($minuteRaw !== null && $minuteRaw !== '') {
            if (!is_numeric($minuteRaw)) {
                throw new InvalidArgumentException('Minuut moet een getal zijn.');
            }
            $minute = (int)$minuteRaw;
            if ($minute < 0 || $minute > self::MAX_MINUTE) {
                throw new InvalidArgumentException('Minuut moet tussen 0 en 130 liggen.');
            }
        }

        $drawingData = isset($data['drawing_data']) && is_string($data['drawing_data'])
            ? trim($data['drawing_data'])
            : '';
        $drawingData = $drawingData !== '' ? $drawingData : null;
        if ($drawingData !== null && strlen($drawingData) > self::MAX_DRAWING_DATA_BYTES) {
            throw new InvalidArgumentException('De tekening is te groot om op te slaan.');
        }

        return [
            'title' => $title,
            'phase' => self::DEFAULT_PHASE,
            'minute' => $minute,
            'field_type' => self::DEFAULT_FIELD_TYPE,
            'drawing_data' => $drawingData,
        ];
    }
}
