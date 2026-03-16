<?php
declare(strict_types=1);

class KonvaSanitizer {
    private const MAX_NODES = 50;
    private const MAX_ZONE_RECTS = 2;
    private const FALLBACK_MIN_PLAYERS = 4;
    private const FALLBACK_MAX_PLAYERS = 6;

    private const FIELD_DIMENSIONS = [
        'portrait' => ['width' => 400, 'height' => 600],
        'landscape' => ['width' => 600, 'height' => 400],
        'square' => ['width' => 400, 'height' => 400],
    ];

    private const ASSET_SPECS = [
        '/images/assets/shirt_red_black.svg' => ['width' => 65, 'height' => 50],
        '/images/assets/shirt_red_white.svg' => ['width' => 65, 'height' => 50],
        '/images/assets/shirt_orange.svg' => ['width' => 65, 'height' => 50],
        '/images/assets/pawn.svg' => ['width' => 25, 'height' => 25],
        '/images/assets/cone_white.svg' => ['width' => 25, 'height' => 25],
        '/images/assets/cone_yellow.svg' => ['width' => 25, 'height' => 25],
        '/images/assets/cone_orange.svg' => ['width' => 25, 'height' => 25],
        '/images/assets/goal.svg' => ['width' => 80, 'height' => 40],
        '/images/assets/ball.svg' => ['width' => 24, 'height' => 24],
    ];

    public function sanitize(array $dsl, string $fieldType = 'portrait'): array {
        if (!isset(self::FIELD_DIMENSIONS[$fieldType])) {
            $fieldType = 'portrait';
        }

        $width = self::FIELD_DIMENSIONS[$fieldType]['width'];
        $height = self::FIELD_DIMENSIONS[$fieldType]['height'];

        $warnings = [];
        $children = [];
        $imageKeys = [];
        $zoneRectCount = 0;

        $inputNodes = array_slice($dsl, 0, self::MAX_NODES);
        if (count($dsl) > self::MAX_NODES) {
            $warnings[] = 'De tekening was te druk. Ik heb een paar extra objecten weggelaten.';
        }

        foreach ($inputNodes as $node) {
            if (!is_array($node)) {
                $warnings[] = 'Een deel van de tekening kon ik niet gebruiken.';
                continue;
            }

            $type = (string)($node['type'] ?? '');
            if ($type === 'image') {
                $mapped = $this->mapImageNode($node, $width, $height);
                if ($mapped === null) {
                    $warnings[] = 'Een deel van de tekening kon ik niet gebruiken.';
                    continue;
                }
                if (!$this->appendNode($children, $mapped, $imageKeys)) {
                    $warnings[] = 'De tekening was te druk. Ik heb een paar extra objecten weggelaten.';
                    break;
                }
            } elseif ($type === 'arrow') {
                $mapped = $this->mapArrowNode($node, $width, $height);
                if ($mapped === null) {
                    $warnings[] = 'Een deel van de tekening kon ik niet gebruiken.';
                    continue;
                }
                if (!$this->appendNode($children, $mapped, $imageKeys)) {
                    $warnings[] = 'De tekening was te druk. Ik heb een paar extra objecten weggelaten.';
                    break;
                }
            } elseif ($type === 'rect') {
                if ($zoneRectCount >= self::MAX_ZONE_RECTS) {
                    $warnings[] = 'Ik heb extra vaklijnen weggelaten om de tekening overzichtelijk te houden.';
                    continue;
                }
                $mappedNodes = $this->mapRectNodeToConeNodes($node, $width, $height);
                if (empty($mappedNodes)) {
                    $warnings[] = 'Een deel van de tekening kon ik niet gebruiken.';
                    continue;
                }
                $zoneRectCount++;
                foreach ($mappedNodes as $mappedNode) {
                    if (!$this->appendNode($children, $mappedNode, $imageKeys)) {
                        $warnings[] = 'De tekening was te druk. Ik heb een paar extra objecten weggelaten.';
                        break 2;
                    }
                }
            } else {
                $warnings[] = 'Een deel van de tekening kon ik niet gebruiken.';
            }
        }

        $layer = [
            'attrs' => new stdClass(),
            'className' => 'Layer',
            'children' => $children,
        ];

        $warnings = array_values(array_unique(array_filter(array_map(
            static fn(mixed $warning): string => trim((string)$warning),
            $warnings
        ))));

        return [
            'field_type' => $fieldType,
            'field_width' => $width,
            'field_height' => $height,
            'layer' => $layer,
            'drawing_data' => json_encode($layer, JSON_UNESCAPED_SLASHES),
            'warnings' => $warnings,
            'node_count' => count($children),
        ];
    }

    public function buildFallbackDrawing(array $fields, string $fieldType = 'portrait'): array {
        if (!isset(self::FIELD_DIMENSIONS[$fieldType])) {
            $fieldType = 'portrait';
        }

        $dims = self::FIELD_DIMENSIONS[$fieldType];
        $dsl = $this->buildFallbackDsl($fields, $dims['width'], $dims['height']);
        $sanitized = $this->sanitize($dsl, $fieldType);
        $sanitized['fallback_generated'] = true;
        return $sanitized;
    }

    public static function allowedAssets(): array {
        return array_keys(self::ASSET_SPECS);
    }

    public static function fieldDimensions(string $fieldType): array {
        return self::FIELD_DIMENSIONS[$fieldType] ?? self::FIELD_DIMENSIONS['portrait'];
    }

    private function buildFallbackDsl(array $fields, int $fieldWidth, int $fieldHeight): array {
        $zoneX = (float)round($fieldWidth * 0.15);
        $zoneY = (float)round($fieldHeight * 0.18);
        $zoneWidth = (float)max(180, round($fieldWidth * 0.70));
        $zoneHeight = (float)max(180, round($fieldHeight * 0.56));
        if (($zoneX + $zoneWidth) > $fieldWidth) {
            $zoneWidth = max(120.0, $fieldWidth - $zoneX - 10.0);
        }
        if (($zoneY + $zoneHeight) > $fieldHeight) {
            $zoneHeight = max(120.0, $fieldHeight - $zoneY - 10.0);
        }

        $centerX = $zoneX + ($zoneWidth / 2.0);
        $centerY = $zoneY + ($zoneHeight / 2.0);

        $nodes = [
            [
                'type' => 'rect',
                'x' => $zoneX,
                'y' => $zoneY,
                'width' => $zoneWidth,
                'height' => $zoneHeight,
            ],
        ];

        foreach ($this->fallbackConePositions($zoneX, $zoneY, $zoneWidth, $zoneHeight) as $cone) {
            $nodes[] = [
                'type' => 'image',
                'x' => $cone[0],
                'y' => $cone[1],
                'imageSrc' => '/images/assets/cone_orange.svg',
            ];
        }

        $playerAssets = [
            '/images/assets/shirt_orange.svg',
            '/images/assets/shirt_red_white.svg',
        ];
        $visiblePlayers = $this->fallbackVisiblePlayerCount($fields);
        $playerPositions = $this->fallbackPlayerPositions($visiblePlayers, $zoneX, $zoneY, $zoneWidth, $zoneHeight);
        foreach ($playerPositions as $index => $position) {
            $nodes[] = [
                'type' => 'image',
                'x' => $position[0],
                'y' => $position[1],
                'imageSrc' => $playerAssets[$index % count($playerAssets)],
            ];
        }

        $nodes[] = [
            'type' => 'image',
            'x' => max(24.0, $centerX - ($zoneWidth * 0.12)),
            'y' => $centerY,
            'imageSrc' => '/images/assets/ball.svg',
        ];

        if ($this->fallbackShouldIncludeGoals($fields)) {
            $nodes[] = [
                'type' => 'image',
                'x' => max(40.0, $zoneX - 18.0),
                'y' => $centerY,
                'imageSrc' => '/images/assets/goal.svg',
            ];
            $nodes[] = [
                'type' => 'image',
                'x' => min($fieldWidth - 40.0, $zoneX + $zoneWidth + 18.0),
                'y' => $centerY,
                'imageSrc' => '/images/assets/goal.svg',
            ];
        }

        $startY = $centerY - ($zoneHeight * 0.10);
        $endY = $centerY - ($zoneHeight * 0.10);
        $nodes[] = [
            'type' => 'arrow',
            'points' => [
                max(10.0, $zoneX + ($zoneWidth * 0.18)),
                $startY,
                min($fieldWidth - 10.0, $zoneX + ($zoneWidth * 0.78)),
                $endY,
            ],
            'dash' => [],
            'strokeWidth' => 3,
        ];
        $nodes[] = [
            'type' => 'arrow',
            'points' => [
                max(10.0, $zoneX + ($zoneWidth * 0.25)),
                min($fieldHeight - 10.0, $zoneY + ($zoneHeight * 0.72)),
                min($fieldWidth - 10.0, $zoneX + ($zoneWidth * 0.58)),
                min($fieldHeight - 10.0, $zoneY + ($zoneHeight * 0.48)),
                min($fieldWidth - 10.0, $zoneX + ($zoneWidth * 0.78)),
                min($fieldHeight - 10.0, $zoneY + ($zoneHeight * 0.28)),
            ],
            'dash' => [2, 4],
            'strokeWidth' => 3,
        ];

        return $nodes;
    }

    private function fallbackConePositions(float $zoneX, float $zoneY, float $zoneWidth, float $zoneHeight): array {
        return [
            [$zoneX, $zoneY],
            [$zoneX + $zoneWidth, $zoneY],
            [$zoneX, $zoneY + $zoneHeight],
            [$zoneX + $zoneWidth, $zoneY + $zoneHeight],
        ];
    }

    private function fallbackVisiblePlayerCount(array $fields): int {
        $minPlayers = isset($fields['min_players']) && is_numeric($fields['min_players'])
            ? (int)$fields['min_players']
            : null;
        $maxPlayers = isset($fields['max_players']) && is_numeric($fields['max_players'])
            ? (int)$fields['max_players']
            : null;

        $target = $minPlayers ?? $maxPlayers ?? self::FALLBACK_MIN_PLAYERS;
        if ($maxPlayers !== null && $maxPlayers >= 6) {
            $target = max($target, 6);
        }

        return max(self::FALLBACK_MIN_PLAYERS, min(self::FALLBACK_MAX_PLAYERS, $target));
    }

    private function fallbackPlayerPositions(
        int $visiblePlayers,
        float $zoneX,
        float $zoneY,
        float $zoneWidth,
        float $zoneHeight
    ): array {
        $positions = [
            [$zoneX + ($zoneWidth * 0.18), $zoneY + ($zoneHeight * 0.22)],
            [$zoneX + ($zoneWidth * 0.18), $zoneY + ($zoneHeight * 0.78)],
            [$zoneX + ($zoneWidth * 0.82), $zoneY + ($zoneHeight * 0.22)],
            [$zoneX + ($zoneWidth * 0.82), $zoneY + ($zoneHeight * 0.78)],
            [$zoneX + ($zoneWidth * 0.50), $zoneY + ($zoneHeight * 0.20)],
            [$zoneX + ($zoneWidth * 0.50), $zoneY + ($zoneHeight * 0.80)],
        ];

        return array_slice($positions, 0, max(2, min(count($positions), $visiblePlayers)));
    }

    private function fallbackShouldIncludeGoals(array $fields): bool {
        $parts = [];
        foreach (['title', 'description', 'variation', 'coach_instructions', 'team_task'] as $key) {
            $value = trim((string)($fields[$key] ?? ''));
            if ($value !== '') {
                $parts[] = $value;
            }
        }
        foreach (['objectives', 'actions'] as $key) {
            if (!is_array($fields[$key] ?? null)) {
                continue;
            }
            foreach ($fields[$key] as $value) {
                $text = trim((string)$value);
                if ($text !== '') {
                    $parts[] = $text;
                }
            }
        }

        $haystack = strtolower(implode(' ', $parts));
        if ($haystack === '') {
            return false;
        }

        foreach (['doel', 'doeltjes', 'scoren', 'afwerken', 'aanvallen', '1-tegen-1', 'wedstrijd'] as $keyword) {
            if (strpos($haystack, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    private function mapImageNode(array $node, int $fieldWidth, int $fieldHeight): ?array {
        $imageSrc = (string)($node['imageSrc'] ?? '');
        return $this->buildImageNode(
            (float)($node['x'] ?? 0),
            (float)($node['y'] ?? 0),
            $imageSrc,
            $fieldWidth,
            $fieldHeight
        );
    }

    private function mapArrowNode(array $node, int $fieldWidth, int $fieldHeight): ?array {
        $points = $node['points'] ?? null;
        if (!is_array($points) || count($points) < 4 || (count($points) % 2) !== 0) {
            return null;
        }

        $mappedPoints = [];
        foreach ($points as $i => $value) {
            if (!is_numeric($value)) {
                return null;
            }

            $numeric = (float)$value;
            if (($i % 2) === 0) {
                $mappedPoints[] = $this->clamp($numeric, 0, $fieldWidth);
            } else {
                $mappedPoints[] = $this->clamp($numeric, 0, $fieldHeight);
            }
        }

        $stroke = '#ffffff';
        $strokeWidth = $this->clamp((float)($node['strokeWidth'] ?? 2), 1, 12);
        $dash = $this->sanitizeDash($node['dash'] ?? []);

        $attrs = [
            'points' => $mappedPoints,
            'stroke' => $stroke,
            'fill' => $stroke,
            'strokeWidth' => $strokeWidth,
            'dash' => $dash,
            'pointerLength' => 10,
            'pointerWidth' => 8,
            'name' => 'item',
            'draggable' => false,
        ];

        if ($dash === [2.0, 4.0]) {
            $attrs['tension'] = 0.4;
        }

        return [
            'attrs' => $attrs,
            'className' => 'Arrow',
        ];
    }

    private function mapRectNodeToConeNodes(array $node, int $fieldWidth, int $fieldHeight): array {
        $x = $this->clamp((float)($node['x'] ?? 0), 0, $fieldWidth);
        $y = $this->clamp((float)($node['y'] ?? 0), 0, $fieldHeight);
        $width = (float)($node['width'] ?? 0);
        $height = (float)($node['height'] ?? 0);

        if ($width <= 0 || $height <= 0) {
            return [];
        }

        $maxWidth = max(0.0, $fieldWidth - $x);
        $maxHeight = max(0.0, $fieldHeight - $y);

        $width = $this->clamp($width, 1, $maxWidth > 0 ? $maxWidth : 1);
        $height = $this->clamp($height, 1, $maxHeight > 0 ? $maxHeight : 1);
        if ($width < 20 || $height < 20) {
            return [];
        }

        $cones = [];
        foreach ([
            [$x, $y],
            [$x + $width, $y],
            [$x, $y + $height],
            [$x + $width, $y + $height],
        ] as $corner) {
            $cone = $this->buildImageNode(
                (float)$corner[0],
                (float)$corner[1],
                '/images/assets/cone_yellow.svg',
                $fieldWidth,
                $fieldHeight
            );
            if ($cone !== null) {
                $cones[] = $cone;
            }
        }

        return $cones;
    }

    private function appendNode(array &$children, array $node, array &$imageKeys): bool
    {
        if (($node['className'] ?? '') === 'Image') {
            $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
            $src = (string)($attrs['imageSrc'] ?? '');
            $x = round((float)($attrs['x'] ?? 0), 1);
            $y = round((float)($attrs['y'] ?? 0), 1);
            if ($src !== '') {
                $key = $src . '@' . $x . ':' . $y;
                if (isset($imageKeys[$key])) {
                    return true;
                }
                $imageKeys[$key] = true;
            }
        }

        if (count($children) >= self::MAX_NODES) {
            return false;
        }

        $children[] = $node;
        return true;
    }

    private function buildImageNode(
        float $x,
        float $y,
        string $imageSrc,
        int $fieldWidth,
        int $fieldHeight
    ): ?array {
        if (!isset(self::ASSET_SPECS[$imageSrc])) {
            return null;
        }

        $spec = self::ASSET_SPECS[$imageSrc];
        $halfW = $spec['width'] / 2.0;
        $halfH = $spec['height'] / 2.0;
        $mappedX = $this->clamp($x, $halfW, max($halfW, $fieldWidth - $halfW));
        $mappedY = $this->clamp($y, $halfH, max($halfH, $fieldHeight - $halfH));

        return [
            'attrs' => [
                'x' => $mappedX,
                'y' => $mappedY,
                'width' => $spec['width'],
                'height' => $spec['height'],
                'offsetX' => $halfW,
                'offsetY' => $halfH,
                'imageSrc' => $imageSrc,
                'name' => 'item',
                'draggable' => false,
            ],
            'className' => 'Image',
        ];
    }

    private function sanitizeColor(string $value, string $default): string {
        $value = trim($value);

        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/^rgba?\((\s*\d+\s*,){2,3}\s*[\d\.]+\s*\)$/', $value) === 1) {
            return $value;
        }

        return $default;
    }

    private function sanitizeDash(mixed $dash): array {
        if (!is_array($dash)) {
            return [];
        }

        $normalized = array_values(array_map('floatval', $dash));
        if ($normalized === [10.0, 5.0]) {
            return [10.0, 5.0];
        }

        if ($normalized === [2.0, 4.0]) {
            return [2.0, 4.0];
        }

        return [];
    }

    private function clamp(float $value, float $min, float $max): float {
        if ($value < $min) {
            return $min;
        }

        if ($value > $max) {
            return $max;
        }

        return $value;
    }
}
