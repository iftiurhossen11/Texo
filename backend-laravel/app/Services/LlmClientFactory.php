<?php

namespace App\Services;

use App\Services\Llm\ClaudeClient;
use App\Services\Llm\GroqClient;
use App\Services\Llm\LlmClientInterface;
use InvalidArgumentException;

class LlmClientFactory
{
    public static function make(?string $provider = null): LlmClientInterface
    {
        $provider = $provider ?? config('services.llm.provider', 'groq');

        return match ($provider) {
            'claude' => app(ClaudeClient::class),
            'groq' => app(GroqClient::class),
            default => throw new InvalidArgumentException("Unsupported LLM provider: {$provider}"),
        };
    }
}
