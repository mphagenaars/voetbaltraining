<?php
declare(strict_types=1);

class OpenRouterClient {
    private AppSetting $settings;

    public function __construct(private PDO $pdo) {
        $this->settings = new AppSetting($pdo);
    }

    /**
     * Build a multimodal content array with text and base64-encoded images.
     *
     * @param string $text      The text part of the message.
     * @param array  $imageUrls Array of data-URIs ("data:image/jpeg;base64,...") or HTTPS URLs.
     * @return array Content array in OpenAI vision format.
     */
    public static function buildImageContent(string $text, array $imageUrls): array {
        $content = [];

        if ($text !== '') {
            $content[] = ['type' => 'text', 'text' => $text];
        }

        foreach ($imageUrls as $url) {
            if (!is_string($url) || $url === '') {
                continue;
            }
            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => $url],
            ];
        }

        return $content;
    }

    /**
     * Vision completion: same as chatCompletion but with higher timeout and max_tokens.
     * Messages may contain multimodal content arrays.
     */
    public function visionCompletion(array $messages, string $modelId, int $userId, int $maxTokens = 4096): array {
        return $this->doCompletion($messages, $modelId, $userId, [
            'timeout' => 180,
            'max_tokens' => $maxTokens,
        ]);
    }

    public function chatCompletion(array $messages, string $modelId, int $userId): array {
        return $this->doCompletion($messages, $modelId, $userId);
    }

    private function doCompletion(array $messages, string $modelId, int $userId, array $options = []): array {
        $apiKey = $this->getApiKey();
        if ($apiKey === null) {
            return [
                'ok' => false,
                'error' => 'OpenRouter API key ontbreekt of is ongeldig.',
                'http_status' => 503,
            ];
        }

        $appUrl = $this->resolveAppUrl();
        $timeout = (int)($options['timeout'] ?? 120);

        $payload = [
            'model' => $modelId,
            'messages' => $messages,
            'user' => 'app_user_' . $userId,
        ];

        if (isset($options['max_tokens']) && $options['max_tokens'] > 0) {
            $payload['max_tokens'] = (int)$options['max_tokens'];
        }

        $requestBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($requestBody)) {
            return [
                'ok' => false,
                'error' => 'Kon AI-verzoek niet opbouwen.',
                'http_status' => 500,
            ];
        }

        $requestHeaders = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
            'HTTP-Referer' => $appUrl,
        ];

        $transportResult = function_exists('curl_init')
            ? $this->requestViaCurl('https://openrouter.ai/api/v1/chat/completions', $requestHeaders, $requestBody, $timeout)
            : $this->requestViaStream('https://openrouter.ai/api/v1/chat/completions', $requestHeaders, $requestBody, $timeout);

        if (!$transportResult['ok']) {
            $error = trim((string)($transportResult['error'] ?? ''));
            return [
                'ok' => false,
                'error' => $error !== '' ? ('Netwerkfout: ' . $error) : 'Onbekende netwerkfout.',
                'http_status' => (int)($transportResult['http_status'] ?? 503) > 0 ? (int)$transportResult['http_status'] : 503,
            ];
        }

        $rawResponse = (string)($transportResult['body'] ?? '');
        $httpStatus = (int)($transportResult['http_status'] ?? 0);
        $responseHeaders = is_array($transportResult['headers'] ?? null) ? $transportResult['headers'] : [];

        $decoded = json_decode($rawResponse, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return [
                'ok' => false,
                'error' => 'Provider response kon niet worden gelezen.',
                'http_status' => $httpStatus > 0 ? $httpStatus : 500,
            ];
        }

        if ($httpStatus !== 200) {
            $retryAfter = null;
            if (isset($responseHeaders['retry-after']) && is_numeric($responseHeaders['retry-after'])) {
                $retryAfter = (int)$responseHeaders['retry-after'];
            }

            $errorMessage = $this->extractErrorMessage($decoded, $httpStatus);

            return [
                'ok' => false,
                'error' => $errorMessage,
                'http_status' => $httpStatus,
                'retry_after' => $retryAfter,
            ];
        }

        $content = $this->extractMessageContent($decoded);
        $usage = $this->extractUsage($decoded);

        return [
            'ok' => true,
            'content' => $content,
            'usage' => $usage,
            'generation_id' => $this->extractGenerationId($decoded, $responseHeaders),
        ];
    }

    private function getApiKey(): ?string {
        $encrypted = $this->settings->get('openrouter_api_key_enc');
        if ($encrypted === null || trim($encrypted) === '') {
            return null;
        }

        try {
            $decrypted = Encryption::decrypt($encrypted);
            $decrypted = trim($decrypted);
            return $decrypted !== '' ? $decrypted : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function resolveAppUrl(): string {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host;
    }

    private function requestViaCurl(string $url, array $requestHeaders, string $requestBody, int $timeout = 120): array {
        $ch = curl_init($url);
        if ($ch === false) {
            return [
                'ok' => false,
                'error' => 'Kon geen verbinding opzetten met OpenRouter.',
                'http_status' => 500,
                'headers' => [],
            ];
        }

        $responseHeaders = [];
        $headerLines = [];
        foreach ($requestHeaders as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_POSTFIELDS => $requestBody,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HEADERFUNCTION => function ($curl, string $headerLine) use (&$responseHeaders): int {
                $len = strlen($headerLine);
                $parts = explode(':', $headerLine, 2);
                if (count($parts) === 2) {
                    $name = strtolower(trim($parts[0]));
                    $value = trim($parts[1]);
                    if ($name !== '') {
                        $responseHeaders[$name] = $value;
                    }
                }
                return $len;
            },
        ]);

        $rawResponse = curl_exec($ch);
        $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($rawResponse === false) {
            return [
                'ok' => false,
                'error' => $curlError !== '' ? $curlError : 'Onbekende netwerkfout.',
                'http_status' => $httpStatus > 0 ? $httpStatus : 503,
                'headers' => $responseHeaders,
            ];
        }

        return [
            'ok' => true,
            'body' => (string)$rawResponse,
            'http_status' => $httpStatus,
            'headers' => $responseHeaders,
        ];
    }

    private function requestViaStream(string $url, array $requestHeaders, string $requestBody, int $timeout = 120): array {
        $headerLines = [];
        foreach ($requestHeaders as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headerLines),
                'content' => $requestBody,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);

        $rawResponse = @file_get_contents($url, false, $context);
        $responseHeaderLines = isset($http_response_header) && is_array($http_response_header) ? $http_response_header : [];
        ['http_status' => $httpStatus, 'headers' => $responseHeaders] = $this->parseResponseHeaders($responseHeaderLines);

        if ($rawResponse === false) {
            $lastError = error_get_last();
            return [
                'ok' => false,
                'error' => is_array($lastError) ? (string)($lastError['message'] ?? 'Onbekende netwerkfout.') : 'Onbekende netwerkfout.',
                'http_status' => $httpStatus > 0 ? $httpStatus : 503,
                'headers' => $responseHeaders,
            ];
        }

        return [
            'ok' => true,
            'body' => (string)$rawResponse,
            'http_status' => $httpStatus,
            'headers' => $responseHeaders,
        ];
    }

    private function parseResponseHeaders(array $headerLines): array {
        $httpStatus = 0;
        $headers = [];

        foreach ($headerLines as $line) {
            if (preg_match('/^HTTP\/\d+(?:\.\d+)?\s+(\d{3})/i', $line, $matches) === 1) {
                $httpStatus = (int)$matches[1];
                continue;
            }

            $parts = explode(':', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $name = strtolower(trim($parts[0]));
            $value = trim($parts[1]);
            if ($name === '') {
                continue;
            }

            if (!isset($headers[$name])) {
                $headers[$name] = $value;
                continue;
            }

            $headers[$name] .= ', ' . $value;
        }

        return [
            'http_status' => $httpStatus,
            'headers' => $headers,
        ];
    }

    private function extractMessageContent(array $response): string {
        $choices = $response['choices'] ?? [];
        if (!is_array($choices) || empty($choices) || !is_array($choices[0])) {
            return '';
        }

        $message = $choices[0]['message']['content'] ?? '';
        if (is_string($message)) {
            return $message;
        }

        if (is_array($message)) {
            $parts = [];
            foreach ($message as $piece) {
                if (is_array($piece) && isset($piece['text']) && is_string($piece['text'])) {
                    $parts[] = $piece['text'];
                }
            }
            return implode("\n", $parts);
        }

        return '';
    }

    private function extractUsage(array $response): array {
        $usage = is_array($response['usage'] ?? null) ? $response['usage'] : [];

        $inputTokens = (int)($usage['prompt_tokens'] ?? 0);
        $outputTokens = (int)($usage['completion_tokens'] ?? 0);
        $totalTokens = (int)($usage['total_tokens'] ?? ($inputTokens + $outputTokens));

        $supplierCostUsd = 0.0;
        foreach (['cost', 'total_cost', 'cost_usd'] as $key) {
            if (isset($usage[$key]) && is_numeric((string)$usage[$key])) {
                $supplierCostUsd = (float)$usage[$key];
                break;
            }
        }
        if ($supplierCostUsd === 0.0 && isset($response['total_cost']) && is_numeric((string)$response['total_cost'])) {
            $supplierCostUsd = (float)$response['total_cost'];
        }

        return [
            'prompt_tokens' => max(0, $inputTokens),
            'completion_tokens' => max(0, $outputTokens),
            'total_tokens' => max(0, $totalTokens),
            'supplier_cost_usd' => round(max(0.0, $supplierCostUsd), 6),
        ];
    }

    private function extractGenerationId(array $response, array $headers): ?string {
        if (!empty($response['id']) && is_string($response['id'])) {
            return $response['id'];
        }

        foreach (['x-openrouter-generation-id', 'x-request-id'] as $header) {
            if (!empty($headers[$header])) {
                return (string)$headers[$header];
            }
        }

        return null;
    }

    private function extractErrorMessage(array $response, int $httpStatus): string {
        $message = null;
        if (isset($response['error']) && is_array($response['error'])) {
            $message = $response['error']['message'] ?? null;
        }

        if (!is_string($message) || trim($message) === '') {
            if (isset($response['message']) && is_string($response['message'])) {
                $message = $response['message'];
            }
        }

        if (!is_string($message) || trim($message) === '') {
            if ($httpStatus === 401) {
                return 'OpenRouter API key is ongeldig of verlopen.';
            }
            if ($httpStatus === 429) {
                return 'Rate limit bereikt bij AI-provider. Probeer het later opnieuw.';
            }
            if ($httpStatus >= 500) {
                return 'AI-provider is tijdelijk niet beschikbaar.';
            }
            return 'AI-provider gaf een fout terug.';
        }

        return trim($message);
    }
}
