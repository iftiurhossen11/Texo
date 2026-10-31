<?php

namespace App\Services\Llm;

interface LlmClientInterface
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function generate(array $messages, float $temperature = 0.1, int $maxTokens = 512): string;

    public function health(): string;
}
