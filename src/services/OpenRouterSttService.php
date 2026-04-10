<?php
declare(strict_types=1);

class OpenRouterSttService implements SttServiceInterface {
    private OpenRouterClient $client;
    private AiAccessService $accessService;
    private AiUsageService $usageService;
    private AiPricingEngine $pricingEngine;

    private const DEFAULT_MODEL = 'openai/gpt-4o-mini-audio-preview';

    public function __construct(
        private PDO $pdo,
        ?OpenRouterClient $client = null,
        ?AiAccessService $accessService = null,
        ?AiUsageService $usageService = null,
        ?AiPricingEngine $pricingEngine = null
    ) {
        $appSetting = new AppSetting($pdo);
        $this->usageService = $usageService ?? new AiUsageService($pdo);
        $this->accessService = $accessService ?? new AiAccessService($pdo, $appSetting, $this->usageService);
        $this->client = $client ?? new OpenRouterClient($pdo);
        $this->pricingEngine = $pricingEngine ?? new AiPricingEngine();
    }

    public function interpretAudio(string $audioBase64, string $mimeType, array $context, int $userId): array {
        $modelId = $this->resolveSttModel();

        $accessCheck = $this->accessService->checkAiAccess(
            $userId,
            $modelId,
            checkRateLimit: true,
            checkConcurrency: true,
            requireProviderKey: true
        );

        if (!$accessCheck['ok']) {
            return [
                'ok' => false,
                'error' => $accessCheck['error'] ?? 'AI-toegang geweigerd.',
                'error_code' => $accessCheck['error_code'] ?? 'access_denied',
            ];
        }

        $resolvedModel = $accessCheck['model'] ?? null;
        $resolvedModelId = is_array($resolvedModel) ? (string)($resolvedModel['model_id'] ?? $modelId) : $modelId;
        $pricingRule = is_array($resolvedModel) ? ($resolvedModel['pricing'] ?? []) : [];

        $usageEventId = $this->usageService->createEvent(
            $userId,
            null,
            null,
            null,
            $resolvedModelId,
            'in_progress',
            null
        );

        try {
            $convertedAudio = $this->ensureCompatibleFormat($audioBase64, $mimeType);
        } catch (RuntimeException $e) {
            $this->usageService->failEvent($usageEventId, 'stt_audio_conversion_failed');

            return [
                'ok' => false,
                'error' => 'Audio kon niet worden geconverteerd naar een door de provider ondersteund formaat (wav/mp3).',
                'error_code' => 'stt_audio_conversion_failed',
                'model_id' => $resolvedModelId,
            ];
        }

        $messages = $this->buildMessages($convertedAudio['data'], $convertedAudio['mime'], $context);
        $result = $this->client->chatCompletion($messages, $resolvedModelId, $userId);

        if (!$result['ok']) {
            $errorCode = $this->mapErrorCode((int)($result['http_status'] ?? 500));
            $this->usageService->failEvent($usageEventId, $errorCode);

            return [
                'ok' => false,
                'error' => $result['error'] ?? 'Spraakherkenning mislukt.',
                'error_code' => $errorCode,
                'model_id' => $resolvedModelId,
            ];
        }

        $rawContent = trim((string)($result['content'] ?? ''));
        $usage = is_array($result['usage'] ?? null) ? $result['usage'] : [];
        $inputTokens = (int)($usage['prompt_tokens'] ?? 0);
        $outputTokens = (int)($usage['completion_tokens'] ?? 0);

        $billing = $this->pricingEngine->calculate($pricingRule, $inputTokens, $outputTokens);

        $this->usageService->updateEvent($usageEventId, [
            'status' => 'success',
            'generation_id' => $result['generation_id'] ?? null,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => (int)($usage['total_tokens'] ?? ($inputTokens + $outputTokens)),
            'supplier_cost_usd' => (float)($usage['supplier_cost_usd'] ?? 0),
            'billable_cost_eur' => $billing['billable_cost_eur'],
            'pricing_version' => null,
            'pricing_snapshot_json' => json_encode($billing['pricing_snapshot'], JSON_UNESCAPED_UNICODE),
            'error_code' => null,
        ]);

        if ($rawContent === '') {
            return [
                'ok' => false,
                'error' => 'Geen spraak herkend.',
                'error_code' => 'stt_no_speech',
                'model_id' => $resolvedModelId,
                'usage' => $usage,
            ];
        }

        $parsed = $this->parseResponse($rawContent);

        return [
            'ok' => true,
            'transcript' => $parsed['transcript'],
            'events' => $parsed['events'],
            'raw_response' => $rawContent,
            'model_id' => $resolvedModelId,
            'usage' => $usage,
        ];
    }

    private function buildMessages(string $audioBase64, string $mimeType, array $context): array {
        $fieldPlayers = is_array($context['field_players'] ?? null) ? $context['field_players'] : [];
        $benchPlayers = is_array($context['bench_players'] ?? null) ? $context['bench_players'] : [];
        $aliases = is_array($context['aliases'] ?? null) ? $context['aliases'] : [];
        $period = (int)($context['period'] ?? 1);

        $systemPrompt = $this->buildSystemPrompt($fieldPlayers, $benchPlayers, $aliases, $period);

        return [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Luister naar het volgende audiocommando en geef de gestructureerde events terug.',
                    ],
                    [
                        'type' => 'input_audio',
                        'input_audio' => [
                            'data' => $audioBase64,
                            'format' => $this->resolveAudioFormat($mimeType),
                        ],
                    ],
                ],
            ],
        ];
    }

    private function buildSystemPrompt(array $fieldPlayers, array $benchPlayers, array $aliases, int $period): string {
        $lines = [];
        $lines[] = 'Je bent een assistent voor een jeugdvoetbalcoach tijdens een live wedstrijd.';
        $lines[] = 'Luister naar het audiocommando en interpreteer welke wedstrijdevents de coach bedoelt.';
        $lines[] = '';
        $lines[] = 'Ondersteunde event-types:';
        $lines[] = '- "substitution": een wissel. Velden: player_in (naam of id van speler die erin komt), player_out (naam of id van speler die eruit gaat).';
        $lines[] = '- "goal": een doelpunt. Velden: player (naam of id van de scorer). Optioneel: assist (naam of id van aangever).';
        $lines[] = '- "card": een kaart. Velden: player (naam of id), card_type ("yellow" of "red").';
        $lines[] = '- "chance": een kans. Velden: player (naam of id). Optioneel: detail (korte omschrijving).';
        $lines[] = '- "note": een vrije notitie. Velden: text (de notitie).';
        $lines[] = '';

        // Field players
        $lines[] = 'SPELERS OP HET VELD (periode ' . $period . '):';
        if (!empty($fieldPlayers)) {
            foreach ($fieldPlayers as $p) {
                $num = isset($p['number']) ? '#' . $p['number'] . ' ' : '';
                $slot = !empty($p['slot_code']) ? ' [' . $p['slot_code'] . ']' : '';
                $lines[] = '  - id=' . (int)$p['id'] . ' ' . $num . (string)$p['name'] . $slot;
            }
        } else {
            $lines[] = '  (geen informatie beschikbaar)';
        }

        // Bench players
        $lines[] = '';
        $lines[] = 'SPELERS OP DE BANK:';
        if (!empty($benchPlayers)) {
            foreach ($benchPlayers as $p) {
                $num = isset($p['number']) ? '#' . $p['number'] . ' ' : '';
                $lines[] = '  - id=' . (int)$p['id'] . ' ' . $num . (string)$p['name'];
            }
        } else {
            $lines[] = '  (geen informatie beschikbaar)';
        }

        // Aliases
        if (!empty($aliases)) {
            $lines[] = '';
            $lines[] = 'BEKENDE BIJNAMEN/ALIASSEN:';
            foreach ($aliases as $a) {
                $lines[] = '  - "' . (string)$a['alias'] . '" = speler id=' . (int)$a['player_id'];
            }
        }

        $lines[] = '';
        $lines[] = 'INSTRUCTIES:';
        $lines[] = '1. Interpreteer wat de coach zegt, ongeacht exacte formulering.';
        $lines[] = '2. Match genoemde namen tegen de bovenstaande spelerslijsten. Gebruik het dichtstbijzijnde id.';
        $lines[] = '3. Bij wissels: player_in moet van de bank komen, player_out moet op het veld staan.';
        $lines[] = '4. Eén audiocommando kan meerdere events bevatten.';
        $lines[] = '5. Geef per event een confidence (0.0-1.0) aan: hoe zeker je bent van de interpretatie.';
        $lines[] = '6. Als je niets zinvols kunt herkennen, geef een leeg events-array terug.';
        $lines[] = '';
        $lines[] = 'Antwoord UITSLUITEND met geldig JSON in exact dit formaat, zonder uitleg of markdown:';
        $lines[] = '{';
        $lines[] = '  "transcript": "<letterlijke tekst die je hoort>",';
        $lines[] = '  "events": [';
        $lines[] = '    {';
        $lines[] = '      "type": "substitution",';
        $lines[] = '      "player_in_id": <id>,';
        $lines[] = '      "player_in_name": "<naam>",';
        $lines[] = '      "player_out_id": <id>,';
        $lines[] = '      "player_out_name": "<naam>",';
        $lines[] = '      "confidence": <0.0-1.0>';
        $lines[] = '    },';
        $lines[] = '    {';
        $lines[] = '      "type": "goal",';
        $lines[] = '      "player_id": <id>,';
        $lines[] = '      "player_name": "<naam>",';
        $lines[] = '      "assist_player_id": <id of null>,';
        $lines[] = '      "assist_player_name": "<naam of null>",';
        $lines[] = '      "confidence": <0.0-1.0>';
        $lines[] = '    },';
        $lines[] = '    {';
        $lines[] = '      "type": "card",';
        $lines[] = '      "player_id": <id>,';
        $lines[] = '      "player_name": "<naam>",';
        $lines[] = '      "card_type": "yellow",';
        $lines[] = '      "confidence": <0.0-1.0>';
        $lines[] = '    },';
        $lines[] = '    {';
        $lines[] = '      "type": "chance",';
        $lines[] = '      "player_id": <id>,';
        $lines[] = '      "player_name": "<naam>",';
        $lines[] = '      "detail": "<korte omschrijving>",';
        $lines[] = '      "confidence": <0.0-1.0>';
        $lines[] = '    },';
        $lines[] = '    {';
        $lines[] = '      "type": "note",';
        $lines[] = '      "text": "<vrije tekst>",';
        $lines[] = '      "confidence": <0.0-1.0>';
        $lines[] = '    }';
        $lines[] = '  ]';
        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * Parse LLM response: extract JSON, handle markdown fences and malformed output.
     */
    private function parseResponse(string $rawContent): array {
        $empty = ['transcript' => '', 'events' => []];

        // Strip markdown code fences if present
        $json = $rawContent;
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?\s*```/s', $json, $m) === 1) {
            $json = $m[1];
        }
        $json = trim($json);

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            // Fallback: treat entire response as transcript, no structured events
            return ['transcript' => $rawContent, 'events' => []];
        }

        $transcript = trim((string)($decoded['transcript'] ?? ''));
        $events = is_array($decoded['events'] ?? null) ? $decoded['events'] : [];

        // Normalize events: ensure required fields
        $normalized = [];
        foreach ($events as $event) {
            if (!is_array($event) || empty($event['type'])) {
                continue;
            }
            $event['confidence'] = isset($event['confidence']) ? (float)$event['confidence'] : 0.5;
            $event['confidence'] = max(0.0, min(1.0, $event['confidence']));
            $normalized[] = $event;
        }

        return [
            'transcript' => $transcript,
            'events' => $normalized,
        ];
    }

    /**
     * Guarantee the audio is in a format the provider actually accepts.
     *
     * OpenAI's input_audio endpoint only supports 'wav' and 'mp3'. Everything
     * else (mp4/m4a/webm/ogg/flac/...) must be transcoded to wav first.
     * We keep wav/mp3 input untouched and transcode anything else to 16 kHz
     * mono PCM wav via ffmpeg — optimal for speech STT and always available.
     */
    private function ensureCompatibleFormat(string $audioBase64, string $mimeType): array {
        $normalized = strtolower(trim(explode(';', $mimeType)[0]));
        $providerNativeFormats = ['audio/wav', 'audio/wave', 'audio/x-wav', 'audio/mp3', 'audio/mpeg'];

        if (in_array($normalized, $providerNativeFormats, true)) {
            return ['data' => $audioBase64, 'mime' => $normalized];
        }

        // Transcode everything else (mp4/m4a/webm/ogg/flac/...) to wav
        $tmpIn = tempnam(sys_get_temp_dir(), 'voice_in_');
        $tmpOut = tempnam(sys_get_temp_dir(), 'voice_out_') . '.wav';

        try {
            $rawAudio = base64_decode($audioBase64, true);
            if ($rawAudio === false || $rawAudio === '') {
                throw new RuntimeException('Kon audiopayload niet decoderen.');
            }
            if (file_put_contents($tmpIn, $rawAudio) === false) {
                throw new RuntimeException('Kon audio niet wegschrijven voor conversie.');
            }

            $cmd = sprintf(
                'ffmpeg -y -i %s -ar 16000 -ac 1 -f wav %s 2>&1',
                escapeshellarg($tmpIn),
                escapeshellarg($tmpOut)
            );
            exec($cmd, $output, $exitCode);

            if ($exitCode !== 0 || !file_exists($tmpOut) || filesize($tmpOut) === 0) {
                throw new RuntimeException('ffmpeg-conversie naar wav mislukt.');
            }

            $wavBytes = file_get_contents($tmpOut);
            if ($wavBytes === false || $wavBytes === '') {
                throw new RuntimeException('Geconverteerde wav kon niet worden gelezen.');
            }

            return ['data' => base64_encode($wavBytes), 'mime' => 'audio/wav'];
        } finally {
            @unlink($tmpIn);
            @unlink($tmpOut);
        }
    }

    /**
     * Map a mime type to the provider's input_audio.format value.
     * After ensureCompatibleFormat() the mime is always wav or mp3.
     */
    private function resolveAudioFormat(string $mimeType): string {
        $normalized = strtolower(trim(explode(';', $mimeType)[0]));

        if ($normalized === 'audio/mp3' || $normalized === 'audio/mpeg') {
            return 'mp3';
        }

        return 'wav';
    }

    private function resolveSttModel(): string {
        $stmt = $this->pdo->query(
            "SELECT m.model_id
             FROM ai_models m
             INNER JOIN ai_model_pricing p ON p.model_id = m.model_id
             WHERE m.enabled = 1
               AND m.supports_audio = 1
               AND p.is_active = 1
             ORDER BY m.sort_order ASC, m.id ASC
             LIMIT 1"
        );

        $row = $stmt ? $stmt->fetch() : false;
        if ($row && !empty($row['model_id'])) {
            return (string)$row['model_id'];
        }

        return self::DEFAULT_MODEL;
    }

    private function mapErrorCode(int $httpStatus): string {
        return match (true) {
            $httpStatus === 401 => 'stt_auth_failed',
            $httpStatus === 429 => 'stt_rate_limited',
            $httpStatus >= 500 => 'stt_provider_error',
            default => 'stt_request_failed',
        };
    }
}
