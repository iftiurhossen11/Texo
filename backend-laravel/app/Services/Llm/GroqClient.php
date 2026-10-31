<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GroqClient implements LlmClientInterface
{
    public function generate(array $messages, float $temperature = 0.1, int $maxTokens = 512): string
    {
        $apiKey = config('services.llm.groq_api_key');

        if (empty($apiKey)) {
            throw new RuntimeException('Groq API key is not configured.');
        }

        $response = Http::connectTimeout(3)
            ->timeout(10)
            ->withToken($apiKey)
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => config('services.llm.groq_model'),
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'stop' => ["\nUser:", "\nTexo:"],
            ]);

        if ($response->failed()) {
            Log::error('Groq API error.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('Groq generation failed.');
        }

        $text = trim((string) data_get($response->json(), 'choices.0.message.content', ''));

        if ($text === '') {
            throw new RuntimeException('Groq returned an empty response.');
        }

        return $text;
    }

    public function health(): string
    {
        return empty(config('services.llm.groq_api_key')) ? 'not_configured' : 'configured';
    }
}
