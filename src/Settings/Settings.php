<?php

declare(strict_types=1);

namespace NotGlossy\AiProviderForOpenAiCompatible\Settings;

/**
 * Static resolver for plugin settings.
 *
 * Resolution order for every setting is constant → WordPress option → default. The
 * API key additionally falls through to the legacy `OPENAI_API_KEY` env/constant so
 * upgrades from 1.0.x continue to authenticate until the user moves credentials to
 * the new settings page.
 *
 * The class is safe to load outside WordPress: every `get_option()` and option-write
 * call is guarded with `function_exists()`.
 *
 * @since 1.1.0
 */
class Settings
{
    public const OPTION_BASE_URL = 'ai_provider_for_openai_compatible_base_url';
    public const OPTION_DEFAULT_MODEL = 'ai_provider_for_openai_compatible_default_model';
    /**
     * Core Connectors-API option. The Settings → Connectors screen reads/writes the
     * same name, so the two UIs share a single field. We declare it explicitly here
     * via `setting_name` in `wp_connectors_init` rather than relying on core's
     * auto-generated value, so the option name is stable across core revisions.
     */
    public const OPTION_API_KEY = 'connectors_ai_openai_compatible_api_key';
    public const OPTION_PROVIDER_LABEL = 'ai_provider_for_openai_compatible_provider_label';
    public const OPTION_STRICT_MODELS = 'ai_provider_for_openai_compatible_strict_models';
    public const OPTION_API_STYLE = 'ai_provider_for_openai_compatible_api_style';
    public const OPTION_DETECTED_STYLE = 'ai_provider_for_openai_compatible_detected_api_style';

    public const CONST_BASE_URL = 'AI_PROVIDER_FOR_OPENAI_COMPATIBLE_BASE_URL';
    public const CONST_DEFAULT_MODEL = 'AI_PROVIDER_FOR_OPENAI_COMPATIBLE_DEFAULT_MODEL';
    public const CONST_API_KEY = 'AI_PROVIDER_FOR_OPENAI_COMPATIBLE_API_KEY';
    public const CONST_PROVIDER_LABEL = 'AI_PROVIDER_FOR_OPENAI_COMPATIBLE_PROVIDER_LABEL';
    public const CONST_STRICT_MODELS = 'AI_PROVIDER_FOR_OPENAI_COMPATIBLE_STRICT_MODELS';
    public const CONST_API_STYLE = 'AI_PROVIDER_FOR_OPENAI_COMPATIBLE_API_STYLE';

    public const DEFAULT_BASE_URL = 'https://api.openai.com/v1';
    public const DEFAULT_PROVIDER_LABEL = 'OpenAI-Compatible';
    public const DEFAULT_API_STYLE = 'auto';

    public const API_STYLE_AUTO = 'auto';
    public const API_STYLE_RESPONSES = 'responses';
    public const API_STYLE_CHAT_COMPLETIONS = 'chat_completions';

    /**
     * Returns the configured base URL, trimmed of trailing slashes and validated.
     *
     * Invalid values fall back to the default. `AbstractApiProvider::url()` appends
     * `/$path`, so a stored value ending in `/` would produce `//path`; stripping on
     * read keeps the storage forgiving.
     *
     * @since 1.1.0
     */
    public static function getBaseUrl(): string
    {
        $value = self::resolveString(self::CONST_BASE_URL, self::OPTION_BASE_URL, self::DEFAULT_BASE_URL);
        $value = rtrim(trim($value), '/');

        if ($value === '' || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return self::DEFAULT_BASE_URL;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return self::DEFAULT_BASE_URL;
        }

        return $value;
    }

    /**
     * Returns the configured API key, falling back to the legacy `OPENAI_API_KEY`.
     *
     * Returns an empty string when nothing is configured.
     *
     * @since 1.1.0
     */
    public static function getApiKey(): string
    {
        if (defined(self::CONST_API_KEY)) {
            $constValue = constant(self::CONST_API_KEY);
            if (is_string($constValue) && $constValue !== '') {
                return $constValue;
            }
        }

        $optionValue = self::readOption(self::OPTION_API_KEY);
        if (is_string($optionValue) && $optionValue !== '') {
            return $optionValue;
        }

        // Legacy fallback so 1.0.x installs keep authenticating on upgrade.
        if (defined('OPENAI_API_KEY')) {
            $legacyConst = constant('OPENAI_API_KEY');
            if (is_string($legacyConst) && $legacyConst !== '') {
                return $legacyConst;
            }
        }

        $legacyEnv = getenv('OPENAI_API_KEY');
        if (is_string($legacyEnv) && $legacyEnv !== '') {
            return $legacyEnv;
        }

        return '';
    }

    /**
     * Returns the configured default model ID, or `''` if none is set.
     *
     * Used by {@see OpenAiModelMetadataDirectory::modelSortCallback()} to sort
     * the chosen model to position 0 in the candidate list. When a caller does
     * `AiClient::prompt(...)->usingProvider('openai-compatible')->generateTextResult()`
     * with no explicit model, the AI Client picks the first candidate — which
     * will be this one.
     *
     * @since 1.1.0
     */
    public static function getDefaultModel(): string
    {
        $value = self::resolveString(self::CONST_DEFAULT_MODEL, self::OPTION_DEFAULT_MODEL, '');
        return trim($value);
    }

    /**
     * Returns the display label shown for this provider.
     *
     * @since 1.1.0
     */
    public static function getProviderLabel(): string
    {
        $value = self::resolveString(
            self::CONST_PROVIDER_LABEL,
            self::OPTION_PROVIDER_LABEL,
            self::DEFAULT_PROVIDER_LABEL
        );
        $value = trim($value);
        return $value !== '' ? $value : self::DEFAULT_PROVIDER_LABEL;
    }

    /**
     * Whether models that don't match an OpenAI naming pattern should be discarded.
     *
     * Default `false`: unknown models are assumed to be text-generation, which lets
     * Ollama/vLLM/LM Studio models appear without per-server tweaking.
     *
     * @since 1.1.0
     */
    public static function isStrictModelMode(): bool
    {
        if (defined(self::CONST_STRICT_MODELS)) {
            return (bool) constant(self::CONST_STRICT_MODELS);
        }

        $optionValue = self::readOption(self::OPTION_STRICT_MODELS);
        if ($optionValue === null) {
            return false;
        }

        return (bool) $optionValue;
    }

    /**
     * Returns the configured API style: `auto`, `responses`, or `chat_completions`.
     *
     * @since 1.1.0
     */
    public static function getApiStyle(): string
    {
        $value = self::resolveString(self::CONST_API_STYLE, self::OPTION_API_STYLE, self::DEFAULT_API_STYLE);
        $value = strtolower(trim($value));

        $allowed = [self::API_STYLE_AUTO, self::API_STYLE_RESPONSES, self::API_STYLE_CHAT_COMPLETIONS];
        if (!in_array($value, $allowed, true)) {
            return self::DEFAULT_API_STYLE;
        }

        return $value;
    }

    /**
     * Returns the effective API style — `getApiStyle()` when set explicitly, the
     * cached detected style when valid for the current base URL, or a smart default
     * (Responses for `api.openai.com`, Chat Completions for everything else).
     *
     * @since 1.1.0
     */
    public static function getResolvedApiStyle(): string
    {
        $configured = self::getApiStyle();
        if ($configured !== self::API_STYLE_AUTO) {
            return $configured;
        }

        $detected = self::getDetectedApiStyle();
        if ($detected !== null) {
            return $detected;
        }

        return self::smartDefaultApiStyle(self::getBaseUrl());
    }

    /**
     * Persists a detected API style keyed by base URL hash. The hash means a base URL
     * change invalidates the cache automatically.
     *
     * @since 1.1.0
     */
    public static function setDetectedApiStyle(string $style): void
    {
        if (!in_array($style, [self::API_STYLE_RESPONSES, self::API_STYLE_CHAT_COMPLETIONS], true)) {
            return;
        }

        if (!function_exists('update_option')) {
            return;
        }

        update_option(
            self::OPTION_DETECTED_STYLE,
            [
                'base_url_hash' => self::hashBaseUrl(self::getBaseUrl()),
                'style' => $style,
            ]
        );
    }

    /**
     * Removes any cached detected API style.
     *
     * Called from `SettingsPage` when the base URL option changes so the next call
     * re-evaluates the smart default.
     *
     * @since 1.1.0
     */
    public static function clearDetectedApiStyle(): void
    {
        if (!function_exists('delete_option')) {
            return;
        }
        delete_option(self::OPTION_DETECTED_STYLE);
    }

    /**
     * Reads the cached detected style if it matches the current base URL.
     *
     * @since 1.1.0
     */
    public static function getDetectedApiStyle(): ?string
    {
        $cached = self::readOption(self::OPTION_DETECTED_STYLE);
        if (!is_array($cached)) {
            return null;
        }
        if (!isset($cached['base_url_hash'], $cached['style'])) {
            return null;
        }
        if ($cached['base_url_hash'] !== self::hashBaseUrl(self::getBaseUrl())) {
            return null;
        }
        $style = $cached['style'];
        if (!in_array($style, [self::API_STYLE_RESPONSES, self::API_STYLE_CHAT_COMPLETIONS], true)) {
            return null;
        }
        return $style;
    }

    /**
     * Smart default for auto mode: Responses for OpenAI proper, Chat Completions for
     * everything else. Most OpenAI-compatible servers (Ollama, vLLM, LM Studio, Groq,
     * OpenRouter) implement Chat Completions but not the Responses API.
     *
     * @since 1.1.0
     */
    public static function smartDefaultApiStyle(string $baseUrl): string
    {
        $host = parse_url($baseUrl, PHP_URL_HOST);
        if (is_string($host) && strtolower($host) === 'api.openai.com') {
            return self::API_STYLE_RESPONSES;
        }
        return self::API_STYLE_CHAT_COMPLETIONS;
    }

    /**
     * Resolves a string-valued setting: constant → option → default.
     *
     * @since 1.1.0
     */
    private static function resolveString(string $constName, string $optionName, string $default): string
    {
        if (defined($constName)) {
            $constValue = constant($constName);
            if (is_string($constValue)) {
                return $constValue;
            }
            if (is_scalar($constValue)) {
                return (string) $constValue;
            }
        }

        $optionValue = self::readOption($optionName);
        if (is_string($optionValue) && $optionValue !== '') {
            return $optionValue;
        }

        return $default;
    }

    /**
     * Reads an option, returning `null` if WordPress functions aren't available or
     * the option is unset.
     *
     * @since 1.1.0
     *
     * @return mixed
     */
    private static function readOption(string $optionName)
    {
        if (!function_exists('get_option')) {
            return null;
        }
        $value = get_option($optionName, null);
        return $value === false ? null : $value;
    }

    /**
     * Stable hash used to scope the detected-style cache to a specific base URL.
     *
     * @since 1.1.0
     */
    private static function hashBaseUrl(string $baseUrl): string
    {
        return md5($baseUrl);
    }
}
