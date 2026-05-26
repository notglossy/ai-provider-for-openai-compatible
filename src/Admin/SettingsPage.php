<?php

declare(strict_types=1);

namespace NotGlossy\AiProviderForOpenAiCompatible\Admin;

use NotGlossy\AiProviderForOpenAiCompatible\Settings\Settings;

/**
 * WordPress admin Settings page for the OpenAI-compatible provider.
 *
 * All values render and persist through the WP Settings API, which supplies the
 * nonce, the `options.php` write path, and the standard error-display mechanism.
 * Constants take precedence over options; when a constant is defined the field is
 * rendered disabled with an inline notice naming the constant.
 *
 * @since 1.1.0
 */
class SettingsPage
{
    private const OPTION_GROUP = 'ai_provider_for_openai_compatible';
    private const MENU_SLUG = 'ai-provider-for-openai-compatible';
    private const SETTINGS_SECTION = 'ai_provider_for_openai_compatible_main';
    private const TEXT_DOMAIN = 'ai-provider-for-openai-compatible';

    /**
     * Registers the Settings → AI Provider … submenu page.
     *
     * @since 1.1.0
     */
    public static function registerMenu(): void
    {
        add_options_page(
            __('AI Provider for OpenAI-Compatible Endpoints', self::TEXT_DOMAIN),
            __('AI Provider (OpenAI-Compatible)', self::TEXT_DOMAIN),
            'manage_options',
            self::MENU_SLUG,
            [self::class, 'renderPage']
        );
    }

    /**
     * Registers options, settings section, and fields.
     *
     * @since 1.1.0
     */
    public static function registerSettings(): void
    {
        register_setting(
            self::OPTION_GROUP,
            Settings::OPTION_BASE_URL,
            [
                'type' => 'string',
                'sanitize_callback' => [self::class, 'sanitizeBaseUrl'],
                'default' => Settings::DEFAULT_BASE_URL,
                // Surface to the REST settings endpoint so the Connectors-screen
                // JS can read/write this option via `useEntityProp` on `root.site`.
                'show_in_rest' => [
                    'name' => Settings::OPTION_BASE_URL,
                ],
            ]
        );
        register_setting(
            self::OPTION_GROUP,
            Settings::OPTION_DEFAULT_MODEL,
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
                // Surfaced to REST so the Connectors-screen JS can read/write it.
                'show_in_rest' => [
                    'name' => Settings::OPTION_DEFAULT_MODEL,
                ],
            ]
        );
        // The API key is owned by core's Connectors API (Settings → Connectors).
        // We deliberately do NOT register it from here — core handles its lifecycle.
        register_setting(
            self::OPTION_GROUP,
            Settings::OPTION_PROVIDER_LABEL,
            [
                'type' => 'string',
                'sanitize_callback' => [self::class, 'sanitizeLabel'],
                'default' => Settings::DEFAULT_PROVIDER_LABEL,
            ]
        );
        register_setting(
            self::OPTION_GROUP,
            Settings::OPTION_STRICT_MODELS,
            [
                'type' => 'boolean',
                'sanitize_callback' => [self::class, 'sanitizeBool'],
                'default' => false,
            ]
        );
        register_setting(
            self::OPTION_GROUP,
            Settings::OPTION_API_STYLE,
            [
                'type' => 'string',
                'sanitize_callback' => [self::class, 'sanitizeApiStyle'],
                'default' => Settings::DEFAULT_API_STYLE,
            ]
        );

        // Reset the detected-API-style cache whenever the base URL changes.
        add_action('update_option_' . Settings::OPTION_BASE_URL, [self::class, 'onBaseUrlChanged'], 10, 0);
        add_action('add_option_' . Settings::OPTION_BASE_URL, [self::class, 'onBaseUrlChanged'], 10, 0);
    }

    /**
     * Registers settings sections and fields for the plugin's own settings page.
     *
     * Split out from {@see registerSettings()} so this method (which calls
     * `add_settings_section()` / `add_settings_field()` — both defined in
     * `wp-admin/includes/template.php`) only fires on `admin_init`, while the
     * REST-relevant `register_setting()` calls fire on `init` for every request.
     *
     * @since 1.1.0
     */
    public static function registerAdminFields(): void
    {
        add_settings_section(
            self::SETTINGS_SECTION,
            __('Endpoint configuration', self::TEXT_DOMAIN),
            [self::class, 'renderSectionIntro'],
            self::MENU_SLUG
        );

        add_settings_field(
            Settings::OPTION_BASE_URL,
            __('Base URL', self::TEXT_DOMAIN),
            [self::class, 'renderBaseUrlField'],
            self::MENU_SLUG,
            self::SETTINGS_SECTION
        );
        add_settings_field(
            'api_key_info',
            __('API Key', self::TEXT_DOMAIN),
            [self::class, 'renderApiKeyInfo'],
            self::MENU_SLUG,
            self::SETTINGS_SECTION
        );
        add_settings_field(
            Settings::OPTION_PROVIDER_LABEL,
            __('Provider Label', self::TEXT_DOMAIN),
            [self::class, 'renderProviderLabelField'],
            self::MENU_SLUG,
            self::SETTINGS_SECTION
        );
        add_settings_field(
            Settings::OPTION_API_STYLE,
            __('API Style', self::TEXT_DOMAIN),
            [self::class, 'renderApiStyleField'],
            self::MENU_SLUG,
            self::SETTINGS_SECTION
        );
        add_settings_field(
            Settings::OPTION_STRICT_MODELS,
            __('Model Classification', self::TEXT_DOMAIN),
            [self::class, 'renderStrictField'],
            self::MENU_SLUG,
            self::SETTINGS_SECTION
        );
    }

    /**
     * Renders the settings page.
     *
     * @since 1.1.0
     */
    public static function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', self::TEXT_DOMAIN));
        }

        $apiKey = Settings::getApiKey();
        $baseUrl = Settings::getBaseUrl();
        $resolvedStyle = Settings::getResolvedApiStyle();

        // Pre-compute every translatable string so the template body stays a plain
        // mixed-HTML/PHP block without phpcs ScopeIndent surprises.
        $pageTitle = esc_html__('AI Provider for OpenAI-Compatible Endpoints', self::TEXT_DOMAIN);
        // phpcs:ignore Generic.Files.LineLength.TooLong
        $missingKeyMsg = esc_html__('API key not configured. The provider is registered but cannot authenticate requests.', self::TEXT_DOMAIN);
        $providerIdLabel = esc_html__('Provider ID', self::TEXT_DOMAIN);
        $baseUrlLabel = esc_html__('Resolved Base URL', self::TEXT_DOMAIN);
        $apiStyleLabel = esc_html__('Resolved API Style', self::TEXT_DOMAIN);
        $examplesHeading = esc_html__('Example endpoints', self::TEXT_DOMAIN);
        $constantsHeading = esc_html__('Constant overrides', self::TEXT_DOMAIN);
        // phpcs:ignore Generic.Files.LineLength.TooLong
        $constantsIntro = esc_html__('Any of the following constants defined in wp-config.php override the corresponding option:', self::TEXT_DOMAIN);
        // phpcs:ignore Generic.Files.LineLength.TooLong
        $legacyTemplate = esc_html__('For upgrade continuity, the legacy %s env/constant is still honored when no API key is configured here.', self::TEXT_DOMAIN);
        /* translators: %s: legacy constant name. */
        $legacyNotice = sprintf($legacyTemplate, '<code>OPENAI_API_KEY</code>');

        ?>
        <div class="wrap">
            <h1><?php echo $pageTitle; ?></h1>

            <?php if ($apiKey === '') : ?>
                <div class="notice notice-warning">
                    <p><?php echo $missingKeyMsg; ?></p>
                </div>
            <?php endif; ?>

            <table class="widefat" style="margin-bottom: 1em; max-width: 760px;">
                <tbody>
                    <tr>
                        <th><?php echo $providerIdLabel; ?></th>
                        <td><code>openai-compatible</code></td>
                    </tr>
                    <tr>
                        <th><?php echo $baseUrlLabel; ?></th>
                        <td><code><?php echo esc_html($baseUrl); ?></code></td>
                    </tr>
                    <tr>
                        <th><?php echo $apiStyleLabel; ?></th>
                        <td><code><?php echo esc_html($resolvedStyle); ?></code></td>
                    </tr>
                </tbody>
            </table>

            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections(self::MENU_SLUG);
                submit_button();
                ?>
            </form>

            <h2><?php echo $examplesHeading; ?></h2>
            <ul>
                <li><strong>OpenAI:</strong> <code>https://api.openai.com/v1</code></li>
                <li><strong>Together.ai:</strong> <code>https://api.together.xyz/v1</code></li>
                <li><strong>Groq:</strong> <code>https://api.groq.com/openai/v1</code></li>
                <li><strong>OpenRouter:</strong> <code>https://openrouter.ai/api/v1</code></li>
                <li><strong>Ollama (local):</strong> <code>http://localhost:11434/v1</code></li>
                <li><strong>LM Studio (local):</strong> <code>http://localhost:1234/v1</code></li>
            </ul>

            <h2><?php echo $constantsHeading; ?></h2>
            <p><?php echo $constantsIntro; ?></p>
            <ul>
                <li><code><?php echo esc_html(Settings::CONST_BASE_URL); ?></code></li>
                <li><code><?php echo esc_html(Settings::CONST_API_KEY); ?></code></li>
                <li><code><?php echo esc_html(Settings::CONST_PROVIDER_LABEL); ?></code></li>
                <li><code><?php echo esc_html(Settings::CONST_API_STYLE); ?></code></li>
                <li><code><?php echo esc_html(Settings::CONST_STRICT_MODELS); ?></code></li>
            </ul>
            <p><em><?php echo $legacyNotice; ?></em></p>
        </div>
        <?php
    }

    /**
     * Renders the small intro shown under the section heading.
     *
     * @since 1.1.0
     */
    public static function renderSectionIntro(): void
    {
        echo '<p>' . esc_html__(
            'Configure the OpenAI-compatible endpoint this provider should talk to.',
            self::TEXT_DOMAIN
        ) . '</p>';
    }

    /**
     * Renders the Base URL field.
     *
     * @since 1.1.0
     */
    public static function renderBaseUrlField(): void
    {
        $overridden = defined(Settings::CONST_BASE_URL);
        // phpcs:ignore Generic.Files.LineLength.TooLong
        $value = $overridden ? Settings::getBaseUrl() : (string) get_option(Settings::OPTION_BASE_URL, Settings::DEFAULT_BASE_URL);

        printf(
            '<input type="url" class="regular-text code" name="%s" value="%s" placeholder="%s"%s />',
            esc_attr(Settings::OPTION_BASE_URL),
            esc_attr($value),
            esc_attr(Settings::DEFAULT_BASE_URL),
            $overridden ? ' disabled="disabled"' : ''
        );

        if ($overridden) {
            self::renderOverrideNotice(Settings::CONST_BASE_URL);
        } else {
            echo '<p class="description">' . esc_html__(
                'Full URL including the version path. Trailing slashes are stripped automatically.',
                self::TEXT_DOMAIN
            ) . '</p>';
        }
    }

    /**
     * Renders the API Key info row — a pointer at Settings → Connectors plus a
     * status indicator. The actual field is owned by core's Connectors API in WP
     * 7.0+ (which writes to `connectors_ai_openai_compatible_api_key`).
     *
     * @since 1.1.0
     */
    public static function renderApiKeyInfo(): void
    {
        $overridden = defined(Settings::CONST_API_KEY);
        $hasKey = Settings::getApiKey() !== '';

        if ($overridden) {
            self::renderOverrideNotice(Settings::CONST_API_KEY);
            return;
        }

        $statusLabel = $hasKey
            ? esc_html__('API key is configured.', self::TEXT_DOMAIN)
            : esc_html__('No API key configured.', self::TEXT_DOMAIN);
        $statusBadge = $hasKey ? '✔' : '⚠';

        $connectorsUrl = function_exists('admin_url')
            ? admin_url('options-general.php?page=connectors')
            : '#';

        printf(
            '<p><strong>%s</strong> %s</p>',
            esc_html($statusBadge),
            $statusLabel
        );
        printf(
            '<p><a href="%s" class="button">%s</a></p>',
            esc_url($connectorsUrl),
            esc_html__('Manage API key in Settings → Connectors', self::TEXT_DOMAIN)
        );
        echo '<p class="description">' . esc_html__(
            // phpcs:ignore Generic.Files.LineLength.TooLong
            'WordPress 7.0 hosts API keys for connectors on a unified screen. This plugin reads the same value, so a key entered there applies here automatically. The legacy OPENAI_API_KEY env var/constant is still honored as a fallback.',
            self::TEXT_DOMAIN
        ) . '</p>';
    }

    /**
     * Renders the Provider Label field.
     *
     * @since 1.1.0
     */
    public static function renderProviderLabelField(): void
    {
        $overridden = defined(Settings::CONST_PROVIDER_LABEL);
        $value = $overridden ? Settings::getProviderLabel() : (string) get_option(
            Settings::OPTION_PROVIDER_LABEL,
            Settings::DEFAULT_PROVIDER_LABEL
        );

        printf(
            '<input type="text" class="regular-text" name="%s" value="%s" placeholder="%s"%s />',
            esc_attr(Settings::OPTION_PROVIDER_LABEL),
            esc_attr($value),
            esc_attr(Settings::DEFAULT_PROVIDER_LABEL),
            $overridden ? ' disabled="disabled"' : ''
        );

        if ($overridden) {
            self::renderOverrideNotice(Settings::CONST_PROVIDER_LABEL);
        } else {
            echo '<p class="description">' . esc_html__(
                'Display name shown in any UI that lists registered providers.',
                self::TEXT_DOMAIN
            ) . '</p>';
        }
    }

    /**
     * Renders the API Style radio group.
     *
     * @since 1.1.0
     */
    public static function renderApiStyleField(): void
    {
        $overridden = defined(Settings::CONST_API_STYLE);
        $value = $overridden ? Settings::getApiStyle() : (string) get_option(
            Settings::OPTION_API_STYLE,
            Settings::DEFAULT_API_STYLE
        );

        $options = [
            // phpcs:ignore Generic.Files.LineLength.TooLong
            Settings::API_STYLE_AUTO => __('Auto (recommended) — Responses for api.openai.com, Chat Completions for everything else', self::TEXT_DOMAIN),
            Settings::API_STYLE_RESPONSES => __('Responses API (POST /responses)', self::TEXT_DOMAIN),
            // phpcs:ignore Generic.Files.LineLength.TooLong
            Settings::API_STYLE_CHAT_COMPLETIONS => __('Chat Completions API (POST /chat/completions)', self::TEXT_DOMAIN),
        ];

        echo '<fieldset>';
        foreach ($options as $key => $label) {
            printf(
                // phpcs:ignore Generic.Files.LineLength.TooLong
                '<label style="display:block;margin-bottom:.25em;"><input type="radio" name="%s" value="%s" %s%s /> %s</label>',
                esc_attr(Settings::OPTION_API_STYLE),
                esc_attr($key),
                checked($value, $key, false),
                $overridden ? ' disabled="disabled"' : '',
                esc_html($label)
            );
        }
        echo '</fieldset>';

        if ($overridden) {
            self::renderOverrideNotice(Settings::CONST_API_STYLE);
        } else {
            echo '<p class="description">' . esc_html__(
                // phpcs:ignore Generic.Files.LineLength.TooLong
                'Most OpenAI-compatible servers (Ollama, vLLM, LM Studio, Groq, OpenRouter) only implement Chat Completions. The OpenAI API itself supports both, but the SDK uses Responses by default for richer features.',
                self::TEXT_DOMAIN
            ) . '</p>';
        }
    }

    /**
     * Renders the strict-model-mode checkbox.
     *
     * @since 1.1.0
     */
    public static function renderStrictField(): void
    {
        $overridden = defined(Settings::CONST_STRICT_MODELS);
        $value = $overridden ? Settings::isStrictModelMode() : (bool) get_option(Settings::OPTION_STRICT_MODELS, false);

        printf(
            '<label><input type="checkbox" name="%s" value="1" %s%s /> %s</label>',
            esc_attr(Settings::OPTION_STRICT_MODELS),
            checked($value, true, false),
            $overridden ? ' disabled="disabled"' : '',
            // phpcs:ignore Generic.Files.LineLength.TooLong
            esc_html__('Strict mode: only expose models whose IDs match known OpenAI naming patterns.', self::TEXT_DOMAIN)
        );

        if ($overridden) {
            self::renderOverrideNotice(Settings::CONST_STRICT_MODELS);
        } else {
            echo '<p class="description">' . esc_html__(
                // phpcs:ignore Generic.Files.LineLength.TooLong
                'Off (default): unrecognized model IDs (e.g. llama3, mistral) are treated as text-generation models so non-OpenAI servers work out of the box.',
                self::TEXT_DOMAIN
            ) . '</p>';
        }
    }

    /**
     * Sanitize callback for the Base URL option.
     *
     * @since 1.1.0
     *
     * @param mixed $value Raw submitted value.
     */
    public static function sanitizeBaseUrl($value): string
    {
        $value = is_string($value) ? rtrim(trim(esc_url_raw($value)), '/') : '';

        if ($value === '') {
            return Settings::DEFAULT_BASE_URL;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);
        if ($scheme !== 'http' && $scheme !== 'https') {
            add_settings_error(
                Settings::OPTION_BASE_URL,
                'invalid_scheme',
                __('Base URL must start with http:// or https://. Keeping the previous value.', self::TEXT_DOMAIN)
            );
            return (string) get_option(Settings::OPTION_BASE_URL, Settings::DEFAULT_BASE_URL);
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            add_settings_error(
                Settings::OPTION_BASE_URL,
                'invalid_url',
                __('Base URL is not a valid URL. Keeping the previous value.', self::TEXT_DOMAIN)
            );
            return (string) get_option(Settings::OPTION_BASE_URL, Settings::DEFAULT_BASE_URL);
        }

        return $value;
    }

    /**
     * Sanitize callback for the Provider Label option.
     *
     * @since 1.1.0
     *
     * @param mixed $value Raw submitted value.
     */
    public static function sanitizeLabel($value): string
    {
        $value = is_string($value) ? trim(sanitize_text_field($value)) : '';
        return $value !== '' ? $value : Settings::DEFAULT_PROVIDER_LABEL;
    }

    /**
     * Sanitize callback for boolean options.
     *
     * @since 1.1.0
     *
     * @param mixed $value Raw submitted value.
     */
    public static function sanitizeBool($value): string
    {
        return $value ? '1' : '0';
    }

    /**
     * Sanitize callback for the API Style radio group.
     *
     * @since 1.1.0
     *
     * @param mixed $value Raw submitted value.
     */
    public static function sanitizeApiStyle($value): string
    {
        $allowed = [
            Settings::API_STYLE_AUTO,
            Settings::API_STYLE_RESPONSES,
            Settings::API_STYLE_CHAT_COMPLETIONS,
        ];
        $value = is_string($value) ? strtolower(trim($value)) : '';
        return in_array($value, $allowed, true) ? $value : Settings::DEFAULT_API_STYLE;
    }

    /**
     * Hook fired when the Base URL option is created or updated. Invalidates the
     * cached detected API style so the next call re-evaluates the smart default.
     *
     * @since 1.1.0
     */
    public static function onBaseUrlChanged(): void
    {
        Settings::clearDetectedApiStyle();
    }

    /**
     * Renders the "Overridden by constant …" inline notice under a disabled field.
     *
     * @since 1.1.0
     */
    private static function renderOverrideNotice(string $constantName): void
    {
        printf(
            '<p class="description"><strong>%s</strong> <code>%s</code></p>',
            esc_html__('Overridden by constant:', self::TEXT_DOMAIN),
            esc_html($constantName)
        );
    }
}
