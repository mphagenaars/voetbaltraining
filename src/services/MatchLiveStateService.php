<?php
declare(strict_types=1);

class MatchLiveStateService {
    public function __construct(
        private PDO $pdo,
        private Game $gameModel
    ) {}

    public function getLiveState(int $matchId): array {
        $timerState = $this->gameModel->getTimerState($matchId);
        $period = (int)($timerState['current_period'] ?? 0);
        if ($period <= 0) {
            $period = 1;
        }
        $clockSeconds = (int)($timerState['total_seconds'] ?? 0);

        return $this->getLiveStateAt($matchId, $period, $clockSeconds, $timerState);
    }

    public function getLiveStateAt(int $matchId, int $period, int $clockSeconds, ?array $timerState = null): array {
        if ($period <= 0) {
            $period = 1;
        }
        if ($clockSeconds < 0) {
            $clockSeconds = 0;
        }

        $catalog = $this->buildPlayerCatalog($matchId);
        $savedLineups = $this->groupSavedLineupsByPeriod($this->gameModel->getPeriodLineups($matchId), $catalog);
        $substitutions = $this->gameModel->getSubstitutions($matchId);
        $lineupMap = $this->resolveLineupMap($catalog, $savedLineups, $substitutions, $period, $clockSeconds);

        // Load template positions if a formation template is linked
        $templatePositionMap = $this->loadTemplatePositionsForMatch($matchId);
        $slotPositions = $this->buildSlotPositionMap($lineupMap, $catalog, $savedLineups, $period, $templatePositionMap);

        $activeLineup = $this->buildActiveLineupList($lineupMap, $catalog['players_by_id'], $slotPositions);
        $bench = $this->buildBenchList($lineupMap, $catalog['players_by_id'], $catalog['eligible_player_ids']);

        $minutesSummary = $this->calculatePlayerMinutesWithContext(
            $matchId,
            $catalog,
            $savedLineups,
            $substitutions,
            $timerState
        );

        return [
            'period' => $period,
            'clock_seconds' => $clockSeconds,
            'active_lineup' => $activeLineup,
            'bench' => $bench,
            'lineup_map' => $lineupMap,
            'lineup_saved_periods' => array_map('intval', array_keys($savedLineups)),
            'period_lineup_saved' => isset($savedLineups[$period]) && !empty($savedLineups[$period]),
            'minutes_summary' => $minutesSummary,
        ];
    }

    public function savePeriodLineup(int $matchId, int $period, array $slots, int $userId): array {
        if ($period <= 0) {
            throw new InvalidArgumentException('Ongeldige periode.');
        }

        $catalog = $this->buildPlayerCatalog($matchId);
        $allowedPlayerIds = array_flip($catalog['eligible_player_ids']);
        $normalizedSlots = [];
        $seenSlots = [];
        $seenPlayers = [];

        foreach ($slots as $slot) {
            if (!is_array($slot)) {
                throw new InvalidArgumentException('Ongeldige slot-data.');
            }

            $rawSlotCode = (string)($slot['slot_code'] ?? '');
            $slotCode = MatchSlotCode::sanitize($rawSlotCode);
            if ($slotCode === '') {
                throw new InvalidArgumentException('Slotcode ontbreekt of is ongeldig.');
            }
            if (isset($seenSlots[$slotCode])) {
                throw new InvalidArgumentException('Dubbele slotcode in periode-opstelling.');
            }

            $playerId = (int)($slot['player_id'] ?? 0);
            if ($playerId <= 0) {
                throw new InvalidArgumentException('Ongeldige speler-ID in periode-opstelling.');
            }
            if (!isset($allowedPlayerIds[$playerId])) {
                throw new InvalidArgumentException('Speler is niet beschikbaar voor deze wedstrijd.');
            }
            if (isset($seenPlayers[$playerId])) {
                throw new InvalidArgumentException('Een speler mag maar op een slot staan.');
            }

            $seenSlots[$slotCode] = true;
            $seenPlayers[$playerId] = true;
            $normalizedSlots[] = [
                'slot_code' => $slotCode,
                'player_id' => $playerId,
            ];
        }

        if (empty($normalizedSlots)) {
            throw new InvalidArgumentException('Periode-opstelling mag niet leeg zijn.');
        }

        usort($normalizedSlots, static function (array $a, array $b): int {
            return strcmp((string)$a['slot_code'], (string)$b['slot_code']);
        });

        $this->gameModel->replacePeriodLineup($matchId, $period, $normalizedSlots, $userId);
        $liveState = $this->getLiveState($matchId);

        return [
            'period' => $period,
            'saved_lineup' => $this->buildSavedLineupRows($normalizedSlots, $catalog['players_by_id']),
            'live_state' => $liveState,
        ];
    }

    public function calculatePlayerMinutes(int $matchId): array {
        $catalog = $this->buildPlayerCatalog($matchId);
        $savedLineups = $this->groupSavedLineupsByPeriod($this->gameModel->getPeriodLineups($matchId), $catalog);
        $substitutions = $this->gameModel->getSubstitutions($matchId);

        return $this->calculatePlayerMinutesWithContext(
            $matchId,
            $catalog,
            $savedLineups,
            $substitutions,
            null
        );
    }

    private function calculatePlayerMinutesWithContext(
        int $matchId,
        array $catalog,
        array $savedLineups,
        array $substitutions,
        ?array $timerState
    ): array {
        $timerState = $timerState ?? $this->gameModel->getTimerState($matchId);
        $ranges = $this->buildPeriodClockRanges($matchId, $timerState);
        if (empty($ranges)) {
            return [];
        }

        $stats = [];
        foreach ($catalog['players_by_id'] as $playerId => $player) {
            $stats[$playerId] = [
                'player_id' => (int)$playerId,
                'player_name' => (string)($player['player_name'] ?? ''),
                'number' => isset($player['number']) ? (int)$player['number'] : null,
                'total_seconds_played' => 0,
                'total_minutes_played' => 0.0,
                'seconds_per_period' => [],
                'seconds_per_slot' => [],
            ];
        }

        foreach ($ranges as $period => $range) {
            $period = (int)$period;
            $start = (int)($range['start'] ?? 0);
            $end = (int)($range['end'] ?? 0);
            if ($end <= $start) {
                continue;
            }

            $lineup = $this->resolveLineupMap($catalog, $savedLineups, $substitutions, $period, $start);
            if (empty($lineup)) {
                continue;
            }

            $openStarts = [];
            foreach ($lineup as $slotCode => $playerId) {
                $openStarts[$slotCode] = $start;
            }

            foreach ($substitutions as $sub) {
                $subPeriod = (int)($sub['period'] ?? 0);
                if ($subPeriod !== $period) {
                    continue;
                }

                $clock = (int)($sub['clock_seconds'] ?? 0);
                if ($clock <= $start || $clock > $end) {
                    continue;
                }

                $slotCode = $this->normalizeSlotCodeForCatalog((string)($sub['slot_code'] ?? ''), $catalog);
                $outPlayerId = (int)($sub['player_out_id'] ?? 0);
                $inPlayerId = (int)($sub['player_in_id'] ?? 0);
                $transition = $this->resolveSubstitutionTransition($lineup, $slotCode, $outPlayerId, $inPlayerId);
                if ($transition === null) {
                    continue;
                }

                $outSlotCode = (string)$transition['out_slot'];
                $currentOutPlayerId = (int)$lineup[$outSlotCode];
                $outSegmentStart = (int)($openStarts[$outSlotCode] ?? $start);
                $this->addMinutesSegment($stats, $currentOutPlayerId, $outSlotCode, $outSegmentStart, $clock, $period);

                if (($transition['mode'] ?? '') === 'swap') {
                    $inSlotCode = (string)($transition['in_slot'] ?? '');
                    if ($inSlotCode !== '' && isset($lineup[$inSlotCode])) {
                        $currentInPlayerId = (int)$lineup[$inSlotCode];
                        $inSegmentStart = (int)($openStarts[$inSlotCode] ?? $start);
                        $this->addMinutesSegment($stats, $currentInPlayerId, $inSlotCode, $inSegmentStart, $clock, $period);
                    }
                }

                $this->applySubstitutionTransition($lineup, $transition);
                $openStarts[$outSlotCode] = $clock;
                if (($transition['mode'] ?? '') === 'swap') {
                    $inSlotCode = (string)($transition['in_slot'] ?? '');
                    if ($inSlotCode !== '') {
                        $openStarts[$inSlotCode] = $clock;
                    }
                }
            }

            foreach ($lineup as $slotCode => $playerId) {
                $segmentStart = (int)($openStarts[$slotCode] ?? $start);
                $this->addMinutesSegment($stats, (int)$playerId, (string)$slotCode, $segmentStart, $end, $period);
            }
        }

        foreach ($stats as &$playerStats) {
            $playerStats['total_minutes_played'] = round(((int)$playerStats['total_seconds_played']) / 60, 1);
            ksort($playerStats['seconds_per_period']);
            ksort($playerStats['seconds_per_slot']);
        }
        unset($playerStats);

        $summary = array_values($stats);
        usort($summary, static function (array $a, array $b): int {
            $cmp = ((int)$b['total_seconds_played']) <=> ((int)$a['total_seconds_played']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp((string)$a['player_name'], (string)$b['player_name']);
        });

        return $summary;
    }

    private function addMinutesSegment(
        array &$stats,
        int $playerId,
        string $slotCode,
        int $start,
        int $end,
        int $period
    ): void {
        if (!isset($stats[$playerId])) {
            return;
        }

        $seconds = $end - $start;
        if ($seconds <= 0) {
            return;
        }

        $stats[$playerId]['total_seconds_played'] += $seconds;
        if (!isset($stats[$playerId]['seconds_per_slot'][$slotCode])) {
            $stats[$playerId]['seconds_per_slot'][$slotCode] = 0;
        }
        $stats[$playerId]['seconds_per_slot'][$slotCode] += $seconds;

        if ($period > 0) {
            if (!isset($stats[$playerId]['seconds_per_period'][$period])) {
                $stats[$playerId]['seconds_per_period'][$period] = 0;
            }
            $stats[$playerId]['seconds_per_period'][$period] += $seconds;
        }
    }

    private function buildPeriodClockRanges(int $matchId, array $timerState): array {
        $whistles = $this->gameModel->getWhistleEvents($matchId);
        $ranges = [];

        $elapsed = 0;
        $isPlaying = false;
        $activePeriod = 0;
        $lastStartTimestamp = null;

        foreach ($whistles as $whistle) {
            $timestamp = strtotime((string)($whistle['created_at'] ?? ''));
            if ($timestamp === false) {
                continue;
            }

            $description = (string)($whistle['description'] ?? '');
            $period = (int)($whistle['period'] ?? 0);

            if ($description === 'start_period') {
                if ($isPlaying && $lastStartTimestamp !== null) {
                    $elapsed += max(0, $timestamp - $lastStartTimestamp);
                    if ($activePeriod > 0) {
                        $ranges[$activePeriod]['end'] = $elapsed;
                    }
                }

                $activePeriod = max(1, $period);
                if (!isset($ranges[$activePeriod]['start'])) {
                    $ranges[$activePeriod]['start'] = $elapsed;
                }
                $isPlaying = true;
                $lastStartTimestamp = $timestamp;
                continue;
            }

            if ($description !== 'end_period') {
                continue;
            }

            if ($period <= 0) {
                continue;
            }

            if ($isPlaying && $lastStartTimestamp !== null) {
                $elapsed += max(0, $timestamp - $lastStartTimestamp);
            }

            if (!isset($ranges[$period]['start'])) {
                $ranges[$period]['start'] = $elapsed;
            }
            $ranges[$period]['end'] = $elapsed;

            if ($activePeriod === $period) {
                $isPlaying = false;
                $lastStartTimestamp = null;
                $activePeriod = 0;
            }
        }

        if ($isPlaying && $lastStartTimestamp !== null && $activePeriod > 0) {
            $currentClock = (int)($timerState['total_seconds'] ?? 0);
            if ($currentClock < $elapsed) {
                $currentClock = $elapsed;
            }
            if (!isset($ranges[$activePeriod]['start'])) {
                $ranges[$activePeriod]['start'] = $elapsed;
            }
            $ranges[$activePeriod]['end'] = $currentClock;
        }

        ksort($ranges);
        return $ranges;
    }

    private function buildPlayerCatalog(int $matchId): array {
        $match = $this->gameModel->getById($matchId);
        $formation = is_array($match) ? (string)($match['formation'] ?? '') : '';

        $rows = $this->gameModel->getMatchPlayersForLive($matchId);
        $playersById = [];
        $eligibleRows = [];

        foreach ($rows as $row) {
            $playerId = (int)($row['player_id'] ?? 0);
            if ($playerId <= 0) {
                continue;
            }
            $playersById[$playerId] = [
                'player_id' => $playerId,
                'player_name' => (string)($row['player_name'] ?? ''),
                'number' => isset($row['number']) ? (int)$row['number'] : null,
                'is_keeper' => (int)($row['is_keeper'] ?? 0) === 1,
                'is_substitute' => (int)($row['is_substitute'] ?? 0) === 1,
                'is_absent' => (int)($row['is_absent'] ?? 0) === 1,
                'position_x' => isset($row['position_x']) ? (float)$row['position_x'] : 0.0,
                'position_y' => isset($row['position_y']) ? (float)$row['position_y'] : 0.0,
            ];

            if ((int)($row['is_absent'] ?? 0) !== 1) {
                $eligibleRows[] = $row;
            }
        }

        $starters = array_values(array_filter($eligibleRows, static function (array $row): bool {
            return (int)($row['is_substitute'] ?? 0) !== 1;
        }));
        if (empty($starters)) {
            $starters = $eligibleRows;
        }

        usort($starters, static function (array $a, array $b): int {
            $aKeeper = (int)($a['is_keeper'] ?? 0);
            $bKeeper = (int)($b['is_keeper'] ?? 0);
            if ($aKeeper !== $bKeeper) {
                return $bKeeper <=> $aKeeper;
            }

            $aY = isset($a['position_y']) ? (float)$a['position_y'] : 0.0;
            $bY = isset($b['position_y']) ? (float)$b['position_y'] : 0.0;
            if ($aY !== $bY) {
                return $aY <=> $bY;
            }

            $aX = isset($a['position_x']) ? (float)$a['position_x'] : 0.0;
            $bX = isset($b['position_x']) ? (float)$b['position_x'] : 0.0;
            if ($aX !== $bX) {
                return $aX <=> $bX;
            }

            return strcmp((string)($a['player_name'] ?? ''), (string)($b['player_name'] ?? ''));
        });

        $defaultLineup = [];
        $goalkeeperId = 0;
        foreach ($starters as $starter) {
            $playerId = (int)($starter['player_id'] ?? 0);
            if ($playerId <= 0) {
                continue;
            }
            if ((int)($starter['is_keeper'] ?? 0) === 1) {
                $goalkeeperId = $playerId;
                break;
            }
        }

        if ($goalkeeperId > 0) {
            $defaultLineup['GK'] = $goalkeeperId;
        }

        $fieldStarters = [];
        foreach ($starters as $starter) {
            $playerId = (int)($starter['player_id'] ?? 0);
            if ($playerId <= 0) {
                continue;
            }
            if ($goalkeeperId > 0 && $playerId === $goalkeeperId) {
                continue;
            }
            $fieldStarters[] = $starter;
        }

        $namedFieldSlots = $this->buildNamedFieldSlotsForFormation($fieldStarters, $formation);
        if (!empty($namedFieldSlots)) {
            foreach ($namedFieldSlots as $slotCode => $playerId) {
                $defaultLineup[(string)$slotCode] = (int)$playerId;
            }
        } else {
            $slotCounter = 1;
            foreach ($fieldStarters as $starter) {
                $playerId = (int)($starter['player_id'] ?? 0);
                if ($playerId <= 0) {
                    continue;
                }
                $slotCode = sprintf('S%02d', $slotCounter);
                $slotCounter++;
                $defaultLineup[$slotCode] = $playerId;
            }
        }

        $eligiblePlayerIds = array_values(array_map(
            static fn(array $row): int => (int)$row['player_id'],
            $eligibleRows
        ));
        sort($eligiblePlayerIds);
        $eligiblePlayerIds = array_values(array_unique($eligiblePlayerIds));

        return [
            'formation' => $formation,
            'players_by_id' => $playersById,
            'eligible_player_ids' => $eligiblePlayerIds,
            'default_lineup' => $defaultLineup,
        ];
    }

    private function groupSavedLineupsByPeriod(array $rows, array $catalog): array {
        $result = [];
        foreach ($rows as $row) {
            $period = (int)($row['period'] ?? 0);
            $slotCode = $this->normalizeSlotCodeForCatalog((string)($row['slot_code'] ?? ''), $catalog);
            $playerId = (int)($row['player_id'] ?? 0);

            if ($period <= 0 || $slotCode === '' || $playerId <= 0) {
                continue;
            }

            if (!isset($result[$period])) {
                $result[$period] = [];
            }
            $result[$period][$slotCode] = $playerId;
        }

        ksort($result);
        return $result;
    }

    private function resolveLineupMap(
        array $catalog,
        array $savedLineups,
        array $substitutions,
        int $targetPeriod,
        int $targetClockSeconds
    ): array {
        $defaultLineup = $catalog['default_lineup'];
        $seedLineup = $defaultLineup;
        $seedPeriod = 1;

        foreach ($savedLineups as $period => $lineup) {
            $period = (int)$period;
            if ($period > $targetPeriod) {
                continue;
            }
            $seedPeriod = $period;
            $seedLineup = $lineup;
        }

        $lineup = $seedLineup;
        foreach ($substitutions as $sub) {
            $subPeriod = (int)($sub['period'] ?? 0);
            $subClock = (int)($sub['clock_seconds'] ?? 0);

            if ($subPeriod < $seedPeriod) {
                continue;
            }
            if ($subPeriod > $targetPeriod) {
                break;
            }
            if ($subPeriod === $targetPeriod && $subClock > $targetClockSeconds) {
                break;
            }

            $slotCode = $this->normalizeSlotCodeForCatalog((string)($sub['slot_code'] ?? ''), $catalog);
            $outPlayerId = (int)($sub['player_out_id'] ?? 0);
            $inPlayerId = (int)($sub['player_in_id'] ?? 0);
            $transition = $this->resolveSubstitutionTransition($lineup, $slotCode, $outPlayerId, $inPlayerId);
            if ($transition === null) {
                continue;
            }

            $this->applySubstitutionTransition($lineup, $transition);
        }

        return $this->normalizeLineupMap($lineup, $catalog);
    }

    private function resolveSubstitutionTransition(
        array $lineup,
        string $slotCode,
        int $outPlayerId,
        int $inPlayerId
    ): ?array {
        if ($outPlayerId <= 0 || $inPlayerId <= 0 || $outPlayerId === $inPlayerId) {
            return null;
        }

        if ($slotCode === '' || !isset($lineup[$slotCode]) || (int)$lineup[$slotCode] !== $outPlayerId) {
            $fallbackSlot = array_search($outPlayerId, $lineup, true);
            if (is_string($fallbackSlot) && $fallbackSlot !== '') {
                $slotCode = $fallbackSlot;
            }
        }

        if ($slotCode === '' || !isset($lineup[$slotCode])) {
            return null;
        }

        if ((int)$lineup[$slotCode] !== $outPlayerId) {
            return null;
        }

        $inSlot = array_search($inPlayerId, $lineup, true);
        if (is_string($inSlot) && $inSlot !== '' && $inSlot !== $slotCode) {
            return [
                'mode' => 'swap',
                'out_slot' => $slotCode,
                'in_slot' => $inSlot,
                'out_player_id' => $outPlayerId,
                'in_player_id' => $inPlayerId,
            ];
        }

        return [
            'mode' => 'sub',
            'out_slot' => $slotCode,
            'out_player_id' => $outPlayerId,
            'in_player_id' => $inPlayerId,
        ];
    }

    private function applySubstitutionTransition(array &$lineup, array $transition): void {
        $mode = (string)($transition['mode'] ?? 'sub');
        $outSlot = (string)($transition['out_slot'] ?? '');
        $inPlayerId = (int)($transition['in_player_id'] ?? 0);
        $outPlayerId = (int)($transition['out_player_id'] ?? 0);

        if ($outSlot === '' || !isset($lineup[$outSlot])) {
            return;
        }
        if ($inPlayerId <= 0 || $outPlayerId <= 0) {
            return;
        }

        if ($mode === 'swap') {
            $inSlot = (string)($transition['in_slot'] ?? '');
            if ($inSlot === '' || !isset($lineup[$inSlot])) {
                return;
            }

            $lineup[$outSlot] = $inPlayerId;
            $lineup[$inSlot] = $outPlayerId;
            return;
        }

        $lineup[$outSlot] = $inPlayerId;
    }

    private function normalizeLineupMap(array $lineup, array $catalog): array {
        $playersById = $catalog['players_by_id'];
        $eligibleLookup = array_flip($catalog['eligible_player_ids']);

        $slotOrder = array_keys($lineup);
        if (empty($slotOrder)) {
            $slotOrder = array_keys($catalog['default_lineup']);
            $lineup = $catalog['default_lineup'];
        }

        $normalized = [];
        $usedPlayers = [];

        foreach ($slotOrder as $slotCode) {
            $slotCode = $this->normalizeSlotCodeForCatalog((string)$slotCode, $catalog);
            if ($slotCode === '') {
                continue;
            }
            if (isset($normalized[$slotCode])) {
                continue;
            }

            $playerId = (int)($lineup[$slotCode] ?? 0);
            if (
                $playerId <= 0 ||
                !isset($playersById[$playerId]) ||
                !isset($eligibleLookup[$playerId]) ||
                isset($usedPlayers[$playerId])
            ) {
                $playerId = $this->findNextAvailableEligiblePlayer($catalog['eligible_player_ids'], $usedPlayers);
                if ($playerId <= 0) {
                    continue;
                }
            }

            $normalized[$slotCode] = $playerId;
            $usedPlayers[$playerId] = true;
        }

        ksort($normalized);
        return $normalized;
    }

    private function buildNamedFieldSlotsForFormation(array $fieldStarters, string $formation): array {
        if (!$this->isSixVsSixFormation($formation)) {
            return [];
        }
        if (count($fieldStarters) !== 5) {
            return [];
        }

        $players = [];
        foreach ($fieldStarters as $starter) {
            $playerId = (int)($starter['player_id'] ?? 0);
            $x = isset($starter['position_x']) ? (float)$starter['position_x'] : 0.0;
            $y = isset($starter['position_y']) ? (float)$starter['position_y'] : 0.0;
            if ($playerId <= 0 || !$this->isValidFieldPosition($x, $y)) {
                return [];
            }
            $players[] = [
                'player_id' => $playerId,
                'x' => $x,
                'y' => $y,
                'name' => (string)($starter['player_name'] ?? ''),
            ];
        }

        // Own goal is at the bottom (higher Y). Map deepest row to defenders.
        usort($players, static function (array $a, array $b): int {
            if ((float)$a['y'] !== (float)$b['y']) {
                return ((float)$b['y']) <=> ((float)$a['y']);
            }
            if ((float)$a['x'] !== (float)$b['x']) {
                return ((float)$a['x']) <=> ((float)$b['x']);
            }
            return strcmp((string)$a['name'], (string)$b['name']);
        });

        $backs = [$players[0], $players[1]];
        usort($backs, static function (array $a, array $b): int {
            if ((float)$a['x'] !== (float)$b['x']) {
                return ((float)$a['x']) <=> ((float)$b['x']);
            }
            return strcmp((string)$a['name'], (string)$b['name']);
        });

        $forwards = [$players[3], $players[4]];
        usort($forwards, static function (array $a, array $b): int {
            if ((float)$a['x'] !== (float)$b['x']) {
                return ((float)$a['x']) <=> ((float)$b['x']);
            }
            return strcmp((string)$a['name'], (string)$b['name']);
        });

        return [
            'LA' => (int)$backs[0]['player_id'],
            'RA' => (int)$backs[1]['player_id'],
            'M' => (int)$players[2]['player_id'],
            'LV' => (int)$forwards[0]['player_id'],
            'RV' => (int)$forwards[1]['player_id'],
        ];
    }

    private function isSixVsSixFormation(string $formation): bool {
        $normalized = strtolower(trim($formation));
        if ($normalized === '') {
            return false;
        }
        return $normalized === '6-vs-6' || $normalized === '6v6';
    }

    private function shouldUseNamedSixVsSixSlots(array $catalog): bool {
        if (!$this->isSixVsSixFormation((string)($catalog['formation'] ?? ''))) {
            return false;
        }
        $defaultLineup = is_array($catalog['default_lineup'] ?? null) ? $catalog['default_lineup'] : [];
        return isset($defaultLineup['LA'], $defaultLineup['RA'], $defaultLineup['M'], $defaultLineup['LV'], $defaultLineup['RV']);
    }

    private function normalizeSlotCodeForCatalog(string $slotCode, array $catalog): string {
        $slotCode = MatchSlotCode::sanitize($slotCode);
        if ($slotCode === '') {
            return '';
        }
        if (!$this->shouldUseNamedSixVsSixSlots($catalog)) {
            return $slotCode;
        }

        $legacyMap = [
            'S01' => 'LV',
            'S02' => 'RV',
            'S03' => 'M',
            'S04' => 'LA',
            'S05' => 'RA',
            'K' => 'GK',
        ];
        return $legacyMap[$slotCode] ?? $slotCode;
    }

    private function findNextAvailableEligiblePlayer(array $eligiblePlayerIds, array $usedPlayers): int {
        foreach ($eligiblePlayerIds as $playerId) {
            $playerId = (int)$playerId;
            if ($playerId > 0 && !isset($usedPlayers[$playerId])) {
                return $playerId;
            }
        }
        return 0;
    }

    private function buildActiveLineupList(array $lineupMap, array $playersById, array $slotPositions): array {
        $result = [];
        ksort($lineupMap);
        foreach ($lineupMap as $slotCode => $playerId) {
            $playerId = (int)$playerId;
            if (!isset($playersById[$playerId])) {
                continue;
            }
            $player = $playersById[$playerId];
            $slotPosition = $slotPositions[(string)$slotCode] ?? ['x' => 50.0, 'y' => 50.0];
            $result[] = [
                'slot_code' => (string)$slotCode,
                'player_id' => $playerId,
                'player_name' => (string)($player['player_name'] ?? ''),
                'number' => isset($player['number']) ? (int)$player['number'] : null,
                'is_keeper' => !empty($player['is_keeper']),
                'position_x' => (float)($slotPosition['x'] ?? 50.0),
                'position_y' => (float)($slotPosition['y'] ?? 50.0),
            ];
        }
        return $result;
    }

    private function buildSlotPositionMap(array $lineupMap, array $catalog, array $savedLineups, int $targetPeriod, ?array $templatePositionMap = null): array {
        $slotCodes = array_keys($lineupMap);
        sort($slotCodes);
        $slotCount = max(1, count($slotCodes));
        $useSixVsSixGridFallback = $this->shouldUseSixVsSixGridFallback($slotCodes, $catalog);

        $result = [];
        $fallbackIndex = 0;
        $usedPositionKeys = [];

        foreach ($slotCodes as $slotCode) {
            $slotCode = (string)$slotCode;
            $position = null;

            // Template positions take priority over all other resolution methods
            if ($templatePositionMap !== null && isset($templatePositionMap[$slotCode])) {
                $position = $templatePositionMap[$slotCode];
            } elseif ($useSixVsSixGridFallback) {
                // Live 6v6 view must follow slot anchors, not historical player coordinates.
                $position = $this->resolveCanonicalSlotPosition($slotCode, true);
                if ($position === null) {
                    $position = $this->buildFallbackFieldPosition($fallbackIndex, $slotCount);
                }
            } else {
                // Default slot owner defines canonical coordinates for non-6v6.
                if (isset($catalog['default_lineup'][$slotCode])) {
                    $candidatePlayerId = (int)$catalog['default_lineup'][$slotCode];
                    $position = $this->resolvePlayerFieldPosition($candidatePlayerId, $catalog['players_by_id']);
                }

                // Fallback: derive coordinates from previously saved slot mappings.
                if ($position === null) {
                    for ($period = $targetPeriod; $period >= 1; $period--) {
                        if (!isset($savedLineups[$period][$slotCode])) {
                            continue;
                        }
                        $candidatePlayerId = (int)$savedLineups[$period][$slotCode];
                        $position = $this->resolvePlayerFieldPosition($candidatePlayerId, $catalog['players_by_id']);
                        if ($position !== null) {
                            break;
                        }
                    }
                }

                // Last fallback: current player occupying the slot.
                if ($position === null && isset($lineupMap[$slotCode])) {
                    $candidatePlayerId = (int)$lineupMap[$slotCode];
                    $position = $this->resolvePlayerFieldPosition($candidatePlayerId, $catalog['players_by_id']);
                }

                if ($position === null) {
                    $position = $this->buildFallbackFieldPosition($fallbackIndex, $slotCount);
                }
            }

            $positionKey = $this->buildPositionKey($position);
            if (isset($usedPositionKeys[$positionKey])) {
                // Avoid stacked tokens: choose the next free fallback cell.
                $attempt = 0;
                do {
                    $candidate = $this->buildFallbackFieldPosition(
                        $fallbackIndex + $attempt,
                        $slotCount
                    );
                    $candidateKey = $this->buildPositionKey($candidate);
                    $attempt++;
                } while (isset($usedPositionKeys[$candidateKey]) && $attempt <= (count($slotCodes) + 6));

                if (isset($usedPositionKeys[$candidateKey])) {
                    $candidate = ['x' => 50.0, 'y' => 50.0];
                    $candidateKey = $this->buildPositionKey($candidate);
                }

                $position = $candidate;
                $positionKey = $candidateKey;
            }

            $result[$slotCode] = $position;
            $usedPositionKeys[$positionKey] = true;
            $fallbackIndex++;
        }

        return $result;
    }

    private function buildPositionKey(array $position): string {
        $x = isset($position['x']) ? (float)$position['x'] : 0.0;
        $y = isset($position['y']) ? (float)$position['y'] : 0.0;
        return sprintf('%.2f:%.2f', $x, $y);
    }

    private function shouldUseSixVsSixGridFallback(array $slotCodes, array $catalog): bool {
        if ($this->isSixVsSixFormation((string)($catalog['formation'] ?? ''))) {
            return true;
        }

        if (empty($slotCodes) || count($slotCodes) > 6) {
            return false;
        }

        $allowedSlots = [
            'GK' => true,
            'K' => true,
            'LA' => true,
            'RA' => true,
            'M' => true,
            'LV' => true,
            'RV' => true,
            'S01' => true,
            'S02' => true,
            'S03' => true,
            'S04' => true,
            'S05' => true,
            'S06' => true,
        ];

        $hasGoalkeeperSlot = false;
        foreach ($slotCodes as $slotCode) {
            $normalized = MatchSlotCode::sanitize((string)$slotCode);
            if ($normalized === '' || !isset($allowedSlots[$normalized])) {
                return false;
            }
            if ($normalized === 'GK' || $normalized === 'K') {
                $hasGoalkeeperSlot = true;
            }
        }

        return $hasGoalkeeperSlot;
    }

    /**
     * Load template positions for a match, keyed by slot_code.
     * Returns null if no template is linked, or an associative array of slot_code => ['x' => float, 'y' => float].
     */
    private function loadTemplatePositionsForMatch(int $matchId): ?array {
        $match = $this->gameModel->getById($matchId);
        $templateId = (int)($match['formation_template_id'] ?? 0);
        if ($templateId <= 0) {
            return null;
        }

        $ftModel = new FormationTemplate($this->pdo);
        $template = $ftModel->getById($templateId);
        if (!$template) {
            return null;
        }

        $positions = json_decode($template['positions'], true);
        if (!is_array($positions)) {
            return null;
        }

        $map = [];
        foreach ($positions as $pos) {
            $code = (string)($pos['slot_code'] ?? '');
            if ($code !== '') {
                $map[$code] = [
                    'x' => (float)($pos['x'] ?? 50),
                    'y' => (float)($pos['y'] ?? 50),
                ];
            }
        }

        return !empty($map) ? $map : null;
    }

    private function resolveCanonicalSlotPosition(string $slotCode, bool $useSixVsSixGridFallback): ?array {
        if (!$useSixVsSixGridFallback) {
            return null;
        }

        $normalized = MatchSlotCode::sanitize($slotCode);
        if ($normalized === '') {
            return null;
        }

        $slotAlias = [
            'K' => 'GK',
            'S01' => 'LV',
            'S02' => 'RV',
            'S03' => 'M',
            'S04' => 'LA',
            'S05' => 'RA',
            'S06' => 'GK',
        ];
        $canonicalSlot = $slotAlias[$normalized] ?? $normalized;

        $anchors = [
            'GK' => ['x' => 50.0, 'y' => 88.0],
            'LA' => ['x' => 20.0, 'y' => 65.0],
            'RA' => ['x' => 80.0, 'y' => 65.0],
            'M' => ['x' => 50.0, 'y' => 45.0],
            'LV' => ['x' => 20.0, 'y' => 20.0],
            'RV' => ['x' => 80.0, 'y' => 20.0],
        ];

        if (!isset($anchors[$canonicalSlot])) {
            return null;
        }
        return $anchors[$canonicalSlot];
    }

    private function resolvePlayerFieldPosition(int $playerId, array $playersById): ?array {
        if ($playerId <= 0 || !isset($playersById[$playerId])) {
            return null;
        }

        $player = $playersById[$playerId];
        $x = isset($player['position_x']) ? (float)$player['position_x'] : 0.0;
        $y = isset($player['position_y']) ? (float)$player['position_y'] : 0.0;

        if (!$this->isValidFieldPosition($x, $y)) {
            return null;
        }

        return [
            'x' => $x,
            'y' => $y,
        ];
    }

    private function isValidFieldPosition(float $x, float $y): bool {
        return $x >= 1.0 && $x <= 99.0 && $y >= 1.0 && $y <= 99.0;
    }

    private function buildFallbackFieldPosition(int $index, int $total): array {
        $candidates = $this->buildFallbackFieldCandidates($total);
        if (empty($candidates)) {
            return ['x' => 50.0, 'y' => 50.0];
        }

        $candidateCount = count($candidates);
        $normalizedIndex = $candidateCount > 0 ? ($index % $candidateCount) : 0;
        if ($normalizedIndex < 0) {
            $normalizedIndex += $candidateCount;
        }

        return $candidates[$normalizedIndex] ?? ['x' => 50.0, 'y' => 50.0];
    }

    private function buildFallbackFieldCandidates(int $total): array {
        $total = max(1, $total);
        $columns = (int)min(3, max(1, $total));
        $rows = (int)max(1, ceil($total / $columns));

        $xMin = 20.0;
        $xMax = 80.0;
        $yMin = 15.0;
        $yMax = 85.0;
        $xStep = $columns > 1 ? (($xMax - $xMin) / (float)($columns - 1)) : 0.0;
        $yStep = $rows > 1 ? (($yMax - $yMin) / (float)($rows - 1)) : 0.0;

        $positions = [];
        for ($row = 0; $row < $rows; $row++) {
            for ($column = 0; $column < $columns; $column++) {
                $positions[] = [
                    'x' => $xMin + ((float)$column * $xStep),
                    'y' => $yMin + ((float)$row * $yStep),
                ];
            }
        }

        return $positions;
    }

    private function buildBenchList(array $lineupMap, array $playersById, array $eligiblePlayerIds): array {
        $onFieldLookup = [];
        foreach ($lineupMap as $playerId) {
            $onFieldLookup[(int)$playerId] = true;
        }

        $bench = [];
        foreach ($eligiblePlayerIds as $playerId) {
            $playerId = (int)$playerId;
            if ($playerId <= 0 || isset($onFieldLookup[$playerId]) || !isset($playersById[$playerId])) {
                continue;
            }
            $player = $playersById[$playerId];
            $bench[] = [
                'player_id' => $playerId,
                'player_name' => (string)($player['player_name'] ?? ''),
                'number' => isset($player['number']) ? (int)$player['number'] : null,
                'is_keeper' => !empty($player['is_keeper']),
            ];
        }

        usort($bench, static function (array $a, array $b): int {
            return strcmp((string)$a['player_name'], (string)$b['player_name']);
        });

        return $bench;
    }

    private function buildSavedLineupRows(array $normalizedSlots, array $playersById): array {
        $rows = [];
        foreach ($normalizedSlots as $slot) {
            $playerId = (int)($slot['player_id'] ?? 0);
            $player = $playersById[$playerId] ?? null;
            $rows[] = [
                'slot_code' => (string)($slot['slot_code'] ?? ''),
                'player_id' => $playerId,
                'player_name' => (string)($player['player_name'] ?? ''),
            ];
        }
        return $rows;
    }

}
