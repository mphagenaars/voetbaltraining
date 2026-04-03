<?php
declare(strict_types=1);

interface SttServiceInterface {
    /**
     * Interpret an audio command for a live match.
     *
     * The LLM listens to the audio, interprets intent, matches player names
     * against the provided context, and returns structured events as JSON.
     *
     * @param string $audioBase64  Base64-encoded audio data.
     * @param string $mimeType     MIME type of audio (e.g. 'audio/webm', 'audio/wav').
     * @param array  $context      Match context for interpretation:
     *                             - field_players: array of {id, name, number, slot_code}
     *                             - bench_players: array of {id, name, number}
     *                             - aliases: array of {player_id, alias} for alternative names
     *                             - period: current period number
     *                             - locale: language code (default 'nl')
     * @param int    $userId       User ID for access/usage tracking.
     * @return array{ok: bool, transcript?: string, events?: array, model_id?: string, usage?: array, error?: string, error_code?: string}
     */
    public function interpretAudio(string $audioBase64, string $mimeType, array $context, int $userId): array;
}
