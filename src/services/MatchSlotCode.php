<?php
declare(strict_types=1);

final class MatchSlotCode {
    public static function sanitize(string $slotCode): string {
        $slotCode = strtoupper(trim($slotCode));
        if ($slotCode === '') {
            return '';
        }

        $slotCode = preg_replace('/[^A-Z0-9_-]/', '', $slotCode) ?? '';
        return substr($slotCode, 0, 32);
    }
}
