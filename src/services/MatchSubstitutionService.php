<?php
declare(strict_types=1);

class MatchSubstitutionService {
    public function __construct(
        private PDO $pdo,
        private Game $gameModel,
        private MatchLiveStateService $liveStateService
    ) {}

    public function applyManualSubstitution(
        int $matchId,
        int $playerOutId,
        int $playerInId,
        ?string $slotCode,
        int $userId
    ): array {
        if ($playerOutId <= 0 || $playerInId <= 0) {
            throw new InvalidArgumentException('Kies geldige spelers voor de wissel.');
        }
        if ($playerOutId === $playerInId) {
            throw new InvalidArgumentException('Speler IN en UIT mogen niet hetzelfde zijn.');
        }

        $timerState = $this->gameModel->getTimerState($matchId);
        $isPlaying = (bool)($timerState['is_playing'] ?? false);
        if (!$isPlaying) {
            return $this->applyNextPeriodLineupChange(
                $matchId,
                $playerOutId,
                $playerInId,
                $slotCode,
                $userId,
                $timerState
            );
        }

        $period = (int)($timerState['current_period'] ?? 0);
        if ($period <= 0) {
            $period = 1;
        }
        $clockSeconds = max(0, (int)($timerState['total_seconds'] ?? 0));
        $minuteDisplay = max(1, (int)floor($clockSeconds / 60) + 1);

        $liveState = $this->liveStateService->getLiveStateAt($matchId, $period, $clockSeconds, $timerState);
        $lineupMap = is_array($liveState['lineup_map'] ?? null) ? $liveState['lineup_map'] : [];
        $bench = is_array($liveState['bench'] ?? null) ? $liveState['bench'] : [];
        $changeContext = $this->resolveLineupChangeContext($lineupMap, $bench, $playerOutId, $playerInId, $slotCode);
        $resolvedSlotCode = (string)$changeContext['resolved_slot_code'];
        $isFieldSwap = !empty($changeContext['is_field_swap']);
        $swapSlotCode = (string)($changeContext['swap_slot_code'] ?? '');

        $this->pdo->beginTransaction();
        try {
            $substitutionId = $this->gameModel->createSubstitution([
                'match_id' => $matchId,
                'period' => $period,
                'clock_seconds' => $clockSeconds,
                'minute_display' => $minuteDisplay,
                'slot_code' => $resolvedSlotCode,
                'player_out_id' => $playerOutId,
                'player_in_id' => $playerInId,
                'source' => 'manual',
                'created_by' => $userId,
            ]);

            $substitution = $this->gameModel->getSubstitutionById($matchId, $substitutionId);
            if (!$substitution) {
                throw new RuntimeException('Wissel kon niet worden opgeslagen.');
            }

            if ($isFieldSwap) {
                $description = sprintf(
                    'POSITIEWISSEL: %s (%s) <-> %s (%s)',
                    (string)$substitution['player_out_name'],
                    $resolvedSlotCode,
                    (string)$substitution['player_in_name'],
                    $swapSlotCode
                );
            } else {
                $description = sprintf(
                    'UIT: %s -> IN: %s (%s)',
                    (string)$substitution['player_out_name'],
                    (string)$substitution['player_in_name'],
                    $resolvedSlotCode
                );
            }
            $this->gameModel->addSubstitutionEvent(
                $matchId,
                $minuteDisplay,
                $period,
                $playerInId,
                $substitutionId,
                $description
            );

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $newLiveState = $this->liveStateService->getLiveState($matchId);

        return [
            'substitution' => $this->gameModel->getSubstitutionById($matchId, (int)$substitutionId),
            'active_lineup' => $newLiveState['active_lineup'],
            'bench' => $newLiveState['bench'],
            'period' => (int)$newLiveState['period'],
            'clock_seconds' => (int)$newLiveState['clock_seconds'],
            'period_lineup_saved' => !empty($newLiveState['period_lineup_saved']),
            'minutes_summary' => $newLiveState['minutes_summary'],
            'events' => $this->gameModel->getEvents($matchId),
        ];
    }

    public function undoLastSubstitution(int $matchId): array {
        $latest = $this->gameModel->getLatestSubstitution($matchId);
        if (!$latest) {
            throw new InvalidArgumentException('Er is geen wissel om ongedaan te maken.');
        }

        $substitutionId = (int)($latest['id'] ?? 0);
        if ($substitutionId <= 0) {
            throw new RuntimeException('Ongeldige laatste wissel.');
        }

        $this->pdo->beginTransaction();
        try {
            $this->gameModel->deleteSubstitutionEventBySubstitutionId($matchId, $substitutionId);
            $this->gameModel->deleteSubstitution($matchId, $substitutionId);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $newLiveState = $this->liveStateService->getLiveState($matchId);

        return [
            'undone_substitution_id' => $substitutionId,
            'active_lineup' => $newLiveState['active_lineup'],
            'bench' => $newLiveState['bench'],
            'period' => (int)$newLiveState['period'],
            'clock_seconds' => (int)$newLiveState['clock_seconds'],
            'period_lineup_saved' => !empty($newLiveState['period_lineup_saved']),
            'minutes_summary' => $newLiveState['minutes_summary'],
            'events' => $this->gameModel->getEvents($matchId),
        ];
    }

    private function applyNextPeriodLineupChange(
        int $matchId,
        int $playerOutId,
        int $playerInId,
        ?string $slotCode,
        int $userId,
        array $timerState
    ): array {
        $currentPeriod = (int)($timerState['current_period'] ?? 0);
        $displayPeriod = $currentPeriod > 0 ? $currentPeriod : 1;
        $targetPeriod = $currentPeriod > 0 ? ($currentPeriod + 1) : 1;
        $clockSeconds = max(0, (int)($timerState['total_seconds'] ?? 0));

        $nextPeriodState = $this->liveStateService->getLiveStateAt($matchId, $targetPeriod, $clockSeconds, $timerState);
        $lineupMap = is_array($nextPeriodState['lineup_map'] ?? null) ? $nextPeriodState['lineup_map'] : [];
        $bench = is_array($nextPeriodState['bench'] ?? null) ? $nextPeriodState['bench'] : [];
        $changeContext = $this->resolveLineupChangeContext($lineupMap, $bench, $playerOutId, $playerInId, $slotCode);
        $resolvedSlotCode = (string)$changeContext['resolved_slot_code'];
        $isFieldSwap = !empty($changeContext['is_field_swap']);
        $swapSlotCode = (string)($changeContext['swap_slot_code'] ?? '');

        if ($isFieldSwap && $swapSlotCode !== '' && isset($lineupMap[$swapSlotCode])) {
            $lineupMap[$resolvedSlotCode] = $playerInId;
            $lineupMap[$swapSlotCode] = $playerOutId;
        } else {
            $lineupMap[$resolvedSlotCode] = $playerInId;
        }

        $slots = $this->buildSlotsFromLineupMap($lineupMap);
        if (empty($slots)) {
            throw new RuntimeException('Nieuwe periode-opstelling kon niet worden opgebouwd.');
        }

        $this->liveStateService->savePeriodLineup($matchId, $targetPeriod, $slots, $userId);
        $updatedNextPeriodState = $this->liveStateService->getLiveStateAt($matchId, $targetPeriod, $clockSeconds, $timerState);

        return [
            'substitution' => null,
            'active_lineup' => $updatedNextPeriodState['active_lineup'],
            'bench' => $updatedNextPeriodState['bench'],
            'period' => $displayPeriod,
            'clock_seconds' => $clockSeconds,
            'period_lineup_saved' => !empty($updatedNextPeriodState['period_lineup_saved']),
            'minutes_summary' => $updatedNextPeriodState['minutes_summary'],
            'events' => $this->gameModel->getEvents($matchId),
            'lineup_mode' => 'next_period_planning',
            'planned_period' => $targetPeriod,
        ];
    }

    private function resolveLineupChangeContext(
        array $lineupMap,
        array $bench,
        int $playerOutId,
        int $playerInId,
        ?string $slotCode
    ): array {
        $benchLookup = [];
        foreach ($bench as $benchPlayer) {
            if (!is_array($benchPlayer)) {
                continue;
            }
            $benchLookup[(int)($benchPlayer['player_id'] ?? 0)] = true;
        }

        $resolvedSlotCode = MatchSlotCode::sanitize((string)($slotCode ?? ''));
        $lineupSlotByPlayer = [];
        foreach ($lineupMap as $mapSlotCode => $mapPlayerId) {
            $lineupSlotByPlayer[(int)$mapPlayerId] = (string)$mapSlotCode;
        }

        if (!isset($lineupSlotByPlayer[$playerOutId])) {
            throw new InvalidArgumentException('Speler UIT staat niet op het veld.');
        }

        $isFieldSwap = isset($lineupSlotByPlayer[$playerInId]);
        $isBenchSubstitution = isset($benchLookup[$playerInId]);
        if (!$isFieldSwap && !$isBenchSubstitution) {
            throw new InvalidArgumentException('Speler IN moet op het veld of op de bank staan.');
        }

        $actualSlotCode = (string)$lineupSlotByPlayer[$playerOutId];
        if ($resolvedSlotCode !== '' && $resolvedSlotCode !== $actualSlotCode) {
            throw new InvalidArgumentException('Slotcode komt niet overeen met de huidige opstelling.');
        }
        if ($resolvedSlotCode === '') {
            $resolvedSlotCode = $actualSlotCode;
        }

        $swapSlotCode = $isFieldSwap ? (string)$lineupSlotByPlayer[$playerInId] : '';

        return [
            'resolved_slot_code' => $resolvedSlotCode,
            'is_field_swap' => $isFieldSwap,
            'swap_slot_code' => $swapSlotCode,
        ];
    }

    private function buildSlotsFromLineupMap(array $lineupMap): array {
        $slots = [];
        foreach ($lineupMap as $slotCode => $playerId) {
            $normalizedSlotCode = MatchSlotCode::sanitize((string)$slotCode);
            $normalizedPlayerId = (int)$playerId;
            if ($normalizedSlotCode === '' || $normalizedPlayerId <= 0) {
                continue;
            }
            $slots[] = [
                'slot_code' => $normalizedSlotCode,
                'player_id' => $normalizedPlayerId,
            ];
        }

        usort($slots, static function (array $a, array $b): int {
            return strcmp((string)$a['slot_code'], (string)$b['slot_code']);
        });

        return $slots;
    }

}
