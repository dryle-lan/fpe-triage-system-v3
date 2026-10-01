<?php
declare(strict_types=1);

/**
 * Minimal wrapper around Google's Gemini generateContent REST endpoint,
 * with retry + model fallback.
 *
 * Why the retry/fallback logic: Google's newer Flash models regularly
 * return HTTP 503 ("experiencing high demand"), even on paid tiers, and
 * retrying immediately usually fails too. So for each model we retry a
 * couple of times with a few seconds between attempts, and if it's still
 * busy we move on to the next model in config's 'fallback_models'.
 *
 * config.php:
 *   'gemini' => [
 *       'api_key'         => '...',
 *       'model'           => 'gemini-3.8-flash',
 *       'fallback_models' => ['gemini-3.5-flash', 'gemini-2.5-flash'],
 *   ]
 * Fallback models must be free-tier eligible for your key; ones that
 * aren't (404/429) are simply skipped.
 */
class GeminiClient
{
    private const ATTEMPTS_PER_MODEL = 2;
    private const RETRY_DELAY_SECONDS = 3;
    private const REQUEST_TIMEOUT_SECONDS = 25;

    /**
     * @return array{text: string, model: string} generated text + which model produced it
     */
    public static function generate(string $prompt): array
    {
        $config = require __DIR__ . '/../config/config.php';
        $apiKey = $config['gemini']['api_key'] ?? '';

        if ($apiKey === '') {
            throw new RuntimeException(
                "Gemini API key is not set. Add it to config/config.php ('gemini' => ['api_key' => '...'])."
            );
        }

        $models = array_values(array_unique(array_filter(array_merge(
            [$config['gemini']['model'] ?? 'gemini-2.5-flash'],
            $config['gemini']['fallback_models'] ?? []
        ))));

        $failures = [];

        foreach ($models as $model) {
            for ($attempt = 1; $attempt <= self::ATTEMPTS_PER_MODEL; $attempt++) {
                $result = self::callModel($apiKey, $model, $prompt);

                if ($result['ok']) {
                    return ['text' => $result['text'], 'model' => $model];
                }

                $failures[$model] = $result['error'];

                // Bad key / bad request: no point trying anything else.
                if ($result['fatal']) {
                    throw new RuntimeException($result['error']);
                }

                // Only a temporary overload is worth retrying the same model;
                // anything else (404, quota) -> go straight to the next model.
                if (!$result['retryable']) {
                    break;
                }

                if ($attempt < self::ATTEMPTS_PER_MODEL) {
                    sleep(self::RETRY_DELAY_SECONDS);
                }
            }
        }

        $summary = [];
        foreach ($failures as $model => $error) {
            $summary[] = "{$model}: {$error}";
        }
        throw new RuntimeException(
            'All configured Gemini models failed. ' . implode(' | ', $summary)
        );
    }

    /**
     * @return array{ok: bool, text?: string, error?: string, retryable?: bool, fatal?: bool}
     */
    private static function callModel(string $apiKey, string $model, string $prompt): array
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'contents' => [['parts' => [['text' => $prompt]]]],
            ]),
            CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT_SECONDS,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Network failure / timeout: treat like a temporary overload.
        if ($response === false) {
            return ['ok' => false, 'error' => 'Could not reach Gemini: ' . $curlError, 'retryable' => true, 'fatal' => false];
        }

        $data = json_decode($response, true);

        if ($httpCode === 200) {
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($text === null) {
                // e.g. blocked by a safety filter or empty candidate: try another model.
                return ['ok' => false, 'error' => 'Response contained no text', 'retryable' => false, 'fatal' => false];
            }
            return ['ok' => true, 'text' => trim($text)];
        }

        $message = $data['error']['message'] ?? "HTTP {$httpCode}";

        return [
            'ok'        => false,
            'error'     => $message,
            // 500/503/504 = Google-side trouble, worth a second try.
            'retryable' => in_array($httpCode, [500, 503, 504], true),
            // 400/401/403 = our request or key is wrong; other models won't help.
            'fatal'     => in_array($httpCode, [400, 401, 403], true),
        ];
    }
}
