<?php

namespace App\Services\Meetings;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** DeepSeek → OpenAI для отчётов по собраниям. */
class MeetingLlmService
{
    /**
     * @return array{content: string|null, provider: string|null}
     */
    public function chat(string $systemPrompt, string $userPrompt, float $temperature = 0.2, int $maxTokens = 4096): array
    {
        $deepseekKey = config('services.deepseek.api_key');
        if ($deepseekKey !== '' && $deepseekKey !== null) {
            $content = $this->request(
                'https://api.deepseek.com/v1/chat/completions',
                $deepseekKey,
                config('services.deepseek.model', 'deepseek-chat'),
                $systemPrompt,
                $userPrompt,
                $temperature,
                $maxTokens,
            );
            if ($content !== null) {
                return ['content' => $content, 'provider' => 'deepseek'];
            }
        }

        $openaiKey = config('services.openai.api_key');
        if ($openaiKey !== '' && $openaiKey !== null) {
            $content = $this->request(
                'https://api.openai.com/v1/chat/completions',
                $openaiKey,
                config('services.openai.model', 'gpt-4o-mini'),
                $systemPrompt,
                $userPrompt,
                $temperature,
                $maxTokens,
                useProxy: true,
            );
            if ($content !== null) {
                return ['content' => $content, 'provider' => 'openai'];
            }
        }

        return ['content' => null, 'provider' => null];
    }

    private function request(
        string $url,
        string $apiKey,
        string $model,
        string $systemPrompt,
        string $userPrompt,
        float $temperature,
        int $maxTokens,
        bool $useProxy = false,
    ): ?string {
        try {
            $request = Http::timeout(180)->withToken($apiKey);
            if ($useProxy) {
                $proxy = trim((string) config('services.openai.http_proxy', ''));
                if ($proxy !== '') {
                    $request = $request->withOptions(['proxy' => $proxy]);
                }
            }
            $response = $request->post($url, [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                ]);

            if (! $response->successful()) {
                Log::warning('meeting llm error', ['url' => $url, 'status' => $response->status(), 'body' => $response->body()]);

                return null;
            }

            $content = $response->json('choices.0.message.content');

            return is_string($content) && trim($content) !== '' ? trim($content) : null;
        } catch (\Throwable $e) {
            Log::warning('meeting llm exception', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
