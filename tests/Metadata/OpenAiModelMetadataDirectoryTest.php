<?php

declare(strict_types=1);

namespace NotGlossy\AiProviderForOpenAiCompatible\Tests\Metadata;

use NotGlossy\AiProviderForOpenAiCompatible\Metadata\OpenAiModelMetadataDirectory;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\EmbeddingGeneration\Contracts\EmbeddingGenerationModelInterface;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

/**
 * Tests for the OpenAI model metadata directory.
 *
 * @since 1.1.0
 */
class OpenAiModelMetadataDirectoryTest extends TestCase
{
    /**
     * Tests that sampling option support is advertised for exactly the right models.
     *
     * The sampling options (`temperature`, `top_p`, `logprobs`, and `top_logprobs`) are supported
     * by the currently documented standard GPT models and by reasoning models whose default
     * reasoning effort is `none`. Other reasoning models must not advertise these options, since a
     * configured option becomes a required option during requirement-based model resolution.
     *
     * @dataProvider samplingOptionSupportProvider
     *
     * @param string $modelId The model ID returned by the OpenAI models endpoint.
     * @param bool $expectedSupport Whether the sampling options should be advertised as supported.
     */
    public function testSamplingOptionSupport(string $modelId, bool $expectedSupport): void
    {
        $modelMetadata = $this->parseSingleModelMetadata($modelId);
        $optionNames = $this->getSupportedOptionNames($modelMetadata);

        // Sanity check: the model must be classified as a text generation model.
        $this->assertContains(
            OptionEnum::maxTokens()->value,
            $optionNames,
            sprintf('Expected model "%s" to be classified as a text generation model.', $modelId)
        );

        $samplingOptions = [
            OptionEnum::temperature(),
            OptionEnum::topP(),
            OptionEnum::logprobs(),
            OptionEnum::topLogprobs(),
        ];
        foreach ($samplingOptions as $option) {
            if ($expectedSupport) {
                $this->assertContains(
                    $option->value,
                    $optionNames,
                    sprintf('Expected model "%s" to support the "%s" option.', $modelId, $option->value)
                );
            } else {
                $this->assertNotContains(
                    $option->value,
                    $optionNames,
                    sprintf('Expected model "%s" to not support the "%s" option.', $modelId, $option->value)
                );
            }
        }
    }

    /**
     * Data provider for currently documented models' sampling option support.
     *
     * @return array<string, array{string, bool}> Test cases with model ID and expected support.
     */
    public static function samplingOptionSupportProvider(): array
    {
        return [
            'gpt-5 (reasoning always enabled)' => ['gpt-5', false],
            'gpt-5-mini (reasoning always enabled)' => ['gpt-5-mini', false],
            'gpt-5-pro (reasoning always enabled)' => ['gpt-5-pro', false],
            'gpt-5.1 (default reasoning effort none)' => ['gpt-5.1', true],
            'gpt-5.1 dated snapshot' => ['gpt-5.1-2025-11-13', true],
            'gpt-5.2 (default reasoning effort none)' => ['gpt-5.2', true],
            'gpt-5.2 dated snapshot' => ['gpt-5.2-2025-12-11', true],
            'gpt-5.4 (default reasoning effort none)' => ['gpt-5.4', true],
            'gpt-5.4 dated snapshot' => ['gpt-5.4-2026-03-05', true],
            'gpt-5.4-mini (default reasoning effort none)' => ['gpt-5.4-mini', true],
            'gpt-5.4-mini dated snapshot' => ['gpt-5.4-mini-2026-03-17', true],
            'gpt-5.4-nano (default reasoning effort none)' => ['gpt-5.4-nano', true],
            'gpt-5.4-nano dated snapshot' => ['gpt-5.4-nano-2026-03-17', true],
            'gpt-5.2-pro (cannot disable reasoning)' => ['gpt-5.2-pro', false],
            'gpt-5.2-codex (cannot disable reasoning)' => ['gpt-5.2-codex', false],
            'gpt-5-chat-latest (non-reasoning chat alias)' => ['gpt-5-chat-latest', true],
            'gpt-5.1-chat-latest (live API rejected temperature)' => ['gpt-5.1-chat-latest', false],
            'gpt-5.2-chat-latest (live API rejected temperature)' => ['gpt-5.2-chat-latest', false],
            'fine-tuned gpt-5.2' => ['ft:gpt-5.2:example-org:example-model', true],
            'fine-tuned gpt-5.2-codex' => ['ft:gpt-5.2-codex:example-org:example-model', false],
            'codex-mini-latest (reasoning always enabled)' => ['codex-mini-latest', false],
            'o3 (reasoning always enabled)' => ['o3', false],
            'o4-mini (reasoning always enabled)' => ['o4-mini', false],
            'gpt-4o (standard GPT model)' => ['gpt-4o', true],
            'gpt-4.1 (standard GPT model)' => ['gpt-4.1', true],
        ];
    }

    /**
     * Tests that non-sampling base options remain supported for reasoning models.
     */
    public function testReasoningModelKeepsBaseOptions(): void
    {
        $modelMetadata = $this->parseSingleModelMetadata('gpt-5');
        $optionNames = $this->getSupportedOptionNames($modelMetadata);

        $expectedOptions = [
            OptionEnum::systemInstruction(),
            OptionEnum::maxTokens(),
            OptionEnum::functionDeclarations(),
            OptionEnum::customOptions(),
            OptionEnum::inputModalities(),
            OptionEnum::outputModalities(),
        ];
        foreach ($expectedOptions as $option) {
            $this->assertContains(
                $option->value,
                $optionNames,
                sprintf('Expected model "gpt-5" to support the "%s" option.', $option->value)
            );
        }
    }

    /**
     * Tests that vendor-prefixed and fine-tuned IDs classify via the normalized ID.
     *
     * Aggregators namespace IDs by vendor (`openai/gpt-4o`); fine-tuned models carry
     * an `ft:` envelope. Both must match the same branches as bare OpenAI IDs, while
     * the metadata keeps the full server ID so API requests use it verbatim.
     *
     * @dataProvider prefixedModelClassificationProvider
     *
     * @param string $modelId The model ID returned by the models endpoint.
     * @param string $expectedCapability The capability value the model must advertise.
     */
    public function testPrefixedModelClassification(string $modelId, string $expectedCapability): void
    {
        if (
            $expectedCapability === CapabilityEnum::EMBEDDING_GENERATION
            && !interface_exists(EmbeddingGenerationModelInterface::class)
        ) {
            $this->markTestSkipped('Embedding generation requires PHP AI Client 1.4.0 or later.');
        }

        $modelMetadata = $this->parseSingleModelMetadata($modelId);

        $this->assertSame($modelId, $modelMetadata->getId());

        $capabilityNames = array_map(
            static function ($capability): string {
                return $capability->value;
            },
            $modelMetadata->getSupportedCapabilities()
        );
        $this->assertContains(
            $expectedCapability,
            $capabilityNames,
            sprintf('Expected model "%s" to advertise the "%s" capability.', $modelId, $expectedCapability)
        );
    }

    /**
     * Data provider for vendor-prefixed and fine-tuned model classification.
     *
     * @return array<string, array{string, string}> Test cases with model ID and expected capability.
     */
    public static function prefixedModelClassificationProvider(): array
    {
        return [
            'vendor-prefixed embedding model' => [
                'openai/text-embedding-3-small',
                CapabilityEnum::EMBEDDING_GENERATION,
            ],
            'vendor-prefixed speech model' => [
                'openai/gpt-4o-mini-tts',
                CapabilityEnum::TEXT_TO_SPEECH_CONVERSION,
            ],
            'vendor-prefixed reasoning model keeps text generation' => [
                'openai/o4-mini',
                CapabilityEnum::TEXT_GENERATION,
            ],
            'fine-tuned embedding model uses base capabilities' => [
                'ft:text-embedding-ada-002:my-org::abc123',
                CapabilityEnum::EMBEDDING_GENERATION,
            ],
        ];
    }

    /**
     * Parses the model metadata for a single model ID via the models endpoint response parser.
     *
     * @param string $modelId The model ID.
     * @return ModelMetadata The parsed model metadata.
     */
    private function parseSingleModelMetadata(string $modelId): ModelMetadata
    {
        $directory = new OpenAiModelMetadataDirectory();

        $method = new ReflectionMethod($directory, 'parseResponseToModelMetadataList');
        $method->setAccessible(true);

        $response = new Response(200, [], (string) json_encode(['data' => [['id' => $modelId]]]));

        /** @var list<ModelMetadata> $modelMetadataList */
        $modelMetadataList = $method->invoke($directory, $response);

        $this->assertCount(1, $modelMetadataList);

        return $modelMetadataList[0];
    }

    /**
     * Returns the names of the supported options of the given model metadata.
     *
     * @param ModelMetadata $modelMetadata The model metadata.
     * @return list<string> The supported option names.
     */
    private function getSupportedOptionNames(ModelMetadata $modelMetadata): array
    {
        return array_map(
            static function (SupportedOption $supportedOption): string {
                return $supportedOption->getName()->value;
            },
            $modelMetadata->getSupportedOptions()
        );
    }
}
