<?php

declare(strict_types=1);

namespace NotGlossy\AiProviderForOpenAiCompatible\Models;

use NotGlossy\AiProviderForOpenAiCompatible\Provider\OpenAiProvider;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * Text generation model that targets the OpenAI Chat Completions API
 * (`POST /v1/chat/completions`).
 *
 * Used when the configured (or auto-detected) API style is `chat_completions`. Most
 * OpenAI-compatible servers (Ollama, vLLM, LM Studio, Groq, OpenRouter, Together)
 * implement Chat Completions but not the newer Responses API. The SDK's
 * `AbstractOpenAiCompatibleTextGenerationModel` supplies the entire request and
 * response handling; this subclass only injects the provider-specific URL.
 *
 * @since 1.1.0
 */
class OpenAiChatCompletionsTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel
{
    /**
     * {@inheritDoc}
     *
     * @since 1.1.0
     */
    protected function createRequest(
        HttpMethodEnum $method,
        string $path,
        array $headers = [],
        $data = null
    ): Request {
        return new Request(
            $method,
            OpenAiProvider::url($path),
            $headers,
            $data,
            $this->getRequestOptions()
        );
    }
}
