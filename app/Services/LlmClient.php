<?php

namespace App\Services;

use App\Exceptions\LlmException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LlmClient
{
    /**
     * Call an OpenAI-compatible /chat/completions endpoint and return the
     * assistant text plus usage metadata. Never throws Laravel HTTP errors
     * directly - normalizes them into LlmException so callers can retry.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{content: string, model: string, usage: array, latency_ms: int}
     */
    public function complete(array $messages): array
    {
        $url = rtrim(config('ai.url'), '/').'/chat/completions';

        $payload = [
            'model' => config('ai.model'),
            'messages' => $messages,
            'temperature' => config('ai.temperature'),
            'max_tokens' => config('ai.max_tokens'),
        ];

        if (config('ai.json_mode')) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $request = Http::acceptJson()
            ->timeout(config('ai.timeout'));

        if ($key = config('ai.api_key')) {
            $request = $request->withToken($key);
        }

        $startedAt = hrtime(true);

        try {
            $response = $request->post($url, $payload);
        } catch (\Throwable $e) {
            throw new LlmException('LLM request failed: '.$e->getMessage(), 0, $e);
        }

        $latencyMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        if ($response->failed()) {
            throw new LlmException(
                sprintf('LLM responded with HTTP %d: %s', $response->status(), Str::limit($response->body(), 500))
            );
        }

        $json = $response->json();
        $content = $json['choices'][0]['message']['content'] ?? null;

        if (! is_string($content) || trim($content) === '') {
            throw new LlmException('LLM returned an empty or unrecognized response body.');
        }

        return [
            'content' => $content,
            'model' => $json['model'] ?? config('ai.model'),
            'usage' => [
                'prompt_tokens' => $json['usage']['prompt_tokens'] ?? null,
                'completion_tokens' => $json['usage']['completion_tokens'] ?? null,
                'total_tokens' => $json['usage']['total_tokens'] ?? null,
            ],
            'latency_ms' => $latencyMs,
        ];
    }
}
