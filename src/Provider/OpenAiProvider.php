<?php

declare(strict_types=1);

namespace NotGlossy\AiProviderForOpenAiCompatible\Provider;

use NotGlossy\AiProviderForOpenAiCompatible\Metadata\OpenAiModelMetadataDirectory;
use NotGlossy\AiProviderForOpenAiCompatible\Models\OpenAiChatCompletionsTextGenerationModel;
use NotGlossy\AiProviderForOpenAiCompatible\Models\OpenAiImageGenerationModel;
use NotGlossy\AiProviderForOpenAiCompatible\Models\OpenAiTextGenerationModel;
use NotGlossy\AiProviderForOpenAiCompatible\Settings\Settings;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * Class for the AI Provider for OpenAI-compatible endpoints.
 *
 * Despite the historical "OpenAI" class name, this provider now targets any
 * OpenAI-compatible endpoint configured via {@see Settings} (OpenAI, Together.ai,
 * Groq, OpenRouter, Ollama, vLLM, LM Studio, etc.).
 *
 * @since 1.0.0
 */
class OpenAiProvider extends AbstractApiProvider
{
    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function baseUrl(): string
    {
        return Settings::getBaseUrl();
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createModel(
        ModelMetadata $modelMetadata,
        ProviderMetadata $providerMetadata
    ): ModelInterface {
        $capabilities = $modelMetadata->getSupportedCapabilities();
        foreach ($capabilities as $capability) {
            if ($capability->isTextGeneration()) {
                if (Settings::getResolvedApiStyle() === Settings::API_STYLE_CHAT_COMPLETIONS) {
                    return new OpenAiChatCompletionsTextGenerationModel($modelMetadata, $providerMetadata);
                }
                return new OpenAiTextGenerationModel($modelMetadata, $providerMetadata);
            }
            if ($capability->isImageGeneration()) {
                return new OpenAiImageGenerationModel($modelMetadata, $providerMetadata);
            }
            if ($capability->isTextToSpeechConversion()) {
                // TODO: Implement OpenAiTextToSpeechConversionModel.
                throw new RuntimeException(
                    'OpenAI text to speech conversion model class is not yet implemented.'
                );
            }
        }

        throw new RuntimeException(
            'Unsupported model capabilities: ' . implode(', ', $capabilities)
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createProviderMetadata(): ProviderMetadata
    {
        $providerMetadataArgs = [
            'openai-compatible',
            Settings::getProviderLabel(),
            ProviderTypeEnum::cloud(),
            'https://platform.openai.com/api-keys',
            RequestAuthenticationMethod::apiKey()
        ];
        // Provider description support was added in 1.2.0.
        if (version_compare(AiClient::VERSION, '1.2.0', '>=')) {
            // For WordPress, we should translate the description.
            if (function_exists('__')) {
                // phpcs:ignore Generic.Files.LineLength.TooLong
                $providerMetadataArgs[] = __('Text and image generation against any OpenAI-compatible endpoint (OpenAI, Together.ai, Groq, OpenRouter, Ollama, vLLM, LM Studio, etc.).', 'ai-provider-for-openai-compatible');
            } else {
                // phpcs:ignore Generic.Files.LineLength.TooLong
                $providerMetadataArgs[] = 'Text and image generation against any OpenAI-compatible endpoint (OpenAI, Together.ai, Groq, OpenRouter, Ollama, vLLM, LM Studio, etc.).';
            }
        }
        // Provider logoPath support was added in 1.3.0.
        if (version_compare(AiClient::VERSION, '1.3.0', '>=')) {
            $providerMetadataArgs[] = dirname(__DIR__, 2) . '/assets/images/openai.svg';
        }
        return new ProviderMetadata(...$providerMetadataArgs);
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createProviderAvailability(): ProviderAvailabilityInterface
    {
        // Check valid API access by attempting to list models.
        return new ListModelsApiBasedProviderAvailability(
            static::modelMetadataDirectory()
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface
    {
        return new OpenAiModelMetadataDirectory();
    }
}
