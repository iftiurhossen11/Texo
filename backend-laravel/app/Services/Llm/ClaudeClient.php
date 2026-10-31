<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ClaudeClient implements LlmClientInterface
{
    public function generate(array $messages, float $temperature = 0.1, int $maxTokens = 512): string
    {
        $apiKey = config('services.llm.anthropic_api_key');

        if (empty($apiKey)) {
            throw new RuntimeException('Anthropic API key is not configured.');
        }

        $system = collect($messages)->firstWhere('role', 'system');
        $conversation = array_values(array_filter(
            $messages,
            static fn (array $message): bool => $message['role'] !== 'system',
        ));

        $response = Http::timeout(25)
            ->retry(2, 500, throw: false)
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => config('services.llm.claude_model'),
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
                'system' => $system['content'] ?? '',
                'messages' => $conversation,
            ]);

        if ($response->failed()) {
            Log::error('Claude API error.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('Claude generation failed.');
        }

        $text = collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");

        if ($text === '') {
            throw new RuntimeException('Claude returned an empty response.');
        }

        return trim($text);
    }

    public function health(): string
    {
        return empty(config('services.llm.anthropic_api_key')) ? 'not_configured' : 'configured';
    }
}
