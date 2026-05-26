<?php

// phpcs:disable Generic.Files.LineLength.TooLong
/**
 * Plugin Name: AI Provider for OpenAI-Compatible Endpoints
 * Plugin URI: https://github.com/notglossy/ai-provider-for-openai-compatible
 * Description: AI Provider for any OpenAI-compatible endpoint (OpenAI, Together.ai, Groq, OpenRouter, Ollama, vLLM, LM Studio, etc.) for the WordPress AI Client.
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Version: 1.1.0
 * Author: Not Glossy
 * Author URI: https://github.com/notglossy
 * License: GPL-2.0-or-later
 * License URI: https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain: ai-provider-for-openai-compatible
 *
 * @package NotGlossy\AiProviderForOpenAiCompatible
 */
// phpcs:enable Generic.Files.LineLength.TooLong

declare(strict_types=1);

namespace NotGlossy\AiProviderForOpenAiCompatible;

use NotGlossy\AiProviderForOpenAiCompatible\Admin\SettingsPage;
use NotGlossy\AiProviderForOpenAiCompatible\Provider\OpenAiProvider;
use NotGlossy\AiProviderForOpenAiCompatible\Settings\Settings;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

if (!defined('ABSPATH')) {
    return;
}

// Only load a bundled SDK on pre-7.0 WordPress. WP 7.0 ships the PHP AI Client
// in core; loading our own copy here would shadow core's newer classes (e.g. the
// older ProviderMetadata that the Connectors API calls getDescription() on),
// causing fatals from `wp-includes/connectors.php`. The presence of
// `wp-includes/connectors.php` is the cleanest probe for "this is WP 7.0+".
if (
    !file_exists(ABSPATH . 'wp-includes/connectors.php')
    && file_exists(__DIR__ . '/vendor/autoload.php')
) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/src/autoload.php';

/**
 * Registers the AI Provider for OpenAI-compatible endpoints with the AI Client.
 *
 * @since 1.0.0
 *
 * @return void
 */
function register_provider(): void
{
    if (!class_exists(AiClient::class)) {
        return;
    }

    $registry = AiClient::defaultRegistry();

    if ($registry->hasProvider(OpenAiProvider::class)) {
        return;
    }

    $registry->registerProvider(OpenAiProvider::class);

    // Inject the configured API key (settings page, constant, or legacy env var).
    // The registry's default env-var resolver would look for OPENAI_COMPATIBLE_API_KEY
    // given the new provider ID, but the explicit injection here covers all sources
    // including the settings page option and the legacy OPENAI_API_KEY fallback.
    $apiKey = Settings::getApiKey();
    if ($apiKey !== '' && method_exists($registry, 'setProviderRequestAuthentication')) {
        $registry->setProviderRequestAuthentication(
            OpenAiProvider::class,
            new ApiKeyRequestAuthentication($apiKey)
        );
    }
}

add_action('init', __NAMESPACE__ . '\\register_provider', 5);

/**
 * Enriches the auto-discovered connector entry for this provider with stable
 * metadata: an explicit `setting_name` for the API key, the plugin file path, a
 * richer description, and the logo. Runs on `wp_connectors_init` (WP 7.0+); on
 * older releases the hook simply never fires and this callback is a no-op.
 *
 * @since 1.1.0
 *
 * @param mixed $registry The WP_Connector_Registry instance.
 * @return void
 */
function register_connector_metadata($registry): void
{
    if (!is_object($registry) || !method_exists($registry, 'is_registered')) {
        return;
    }

    if (!$registry->is_registered('openai-compatible')) {
        return;
    }

    $connector = $registry->unregister('openai-compatible');
    if (!is_array($connector)) {
        return;
    }

    $connector['name'] = Settings::getProviderLabel();
    $connector['description'] = function_exists('__')
        // phpcs:ignore Generic.Files.LineLength.TooLong
        ? __('Talk to any OpenAI-compatible endpoint — OpenAI, Together.ai, Groq, OpenRouter, Ollama, vLLM, LM Studio, and others.', 'ai-provider-for-openai-compatible')
        // phpcs:ignore Generic.Files.LineLength.TooLong
        : 'Talk to any OpenAI-compatible endpoint — OpenAI, Together.ai, Groq, OpenRouter, Ollama, vLLM, LM Studio, and others.';

    if (!isset($connector['authentication']) || !is_array($connector['authentication'])) {
        $connector['authentication'] = [];
    }
    $connector['authentication']['method'] = 'api_key';
    $connector['authentication']['setting_name'] = Settings::OPTION_API_KEY;

    $connector['plugin'] = [
        'file' => 'ai-provider-for-openai-compatible/plugin.php',
        // Core's `_wp_register_default_connector_settings()` only registers the
        // API-key option for REST if this callback returns true. Since this code
        // only runs when our plugin is loaded and active, we can return true.
        'is_active' => static function (): bool {
            return true;
        },
    ];

    if (!isset($connector['logo_url'])) {
        $logoPath = dirname(__FILE__) . '/assets/images/openai.svg';
        if (file_exists($logoPath) && function_exists('plugins_url')) {
            $connector['logo_url'] = plugins_url('assets/images/openai.svg', __FILE__);
        }
    }

    $registry->register('openai-compatible', $connector);
}

add_action('wp_connectors_init', __NAMESPACE__ . '\\register_connector_metadata');

// Register settings on `init` so they're available on REST requests too — not
// just admin requests. The `useEntityProp` hook in our connector-ui JS reads
// from `/wp/v2/settings`, which only exposes options whose `register_setting()`
// call has happened by the time that REST request is handled.
add_action('init', [SettingsPage::class, 'registerSettings']);

if (is_admin()) {
    add_action('admin_menu', [SettingsPage::class, 'registerMenu']);
    // Admin UI fields (add_settings_section / add_settings_field) live in
    // wp-admin/includes/template.php and are not defined on front-end or REST
    // requests — keep them on admin_init.
    add_action('admin_init', [SettingsPage::class, 'registerAdminFields']);
    add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_connector_ui');
}

/**
 * Enqueues the custom Connectors-screen UI on Settings → Connectors.
 *
 * Registers a script module that calls `__experimentalRegisterConnector` to
 * replace the default API-key panel with a custom one that includes the Base
 * URL field. The script module is loaded only on `options-connectors.php` so
 * it has zero overhead elsewhere. If the experimental exports change shape in
 * a future WP, the JS module fails gracefully (see its feature-detect).
 *
 * @since 1.1.0
 *
 * @param string $hook_suffix Current admin page hook suffix.
 * @return void
 */
function enqueue_connector_ui(string $hook_suffix): void
{
    // WP 7.0 exposes the Connectors screen via two URLs: the direct admin file
    // wp-admin/options-connectors.php (hook suffix 'options-connectors.php')
    // and the route-based wp-admin/admin.php?page=options-connectors-wp-admin
    // (the pattern documented in Gutenberg's e2e test plugin). Accept either.
    $is_connectors_page =
        $hook_suffix === 'options-connectors.php'
        || $hook_suffix === 'options-connectors'
        || (isset($_GET['page']) && $_GET['page'] === 'options-connectors-wp-admin');

    if (!$is_connectors_page) {
        return;
    }
    if (!function_exists('wp_register_script_module') || !function_exists('wp_enqueue_script_module')) {
        return;
    }

    // Only `@wordpress/connectors` is a real script module in WP 7.0. The other
    // @wordpress/* packages we use (element, components, data, core-data, i18n)
    // are classic scripts exposing `window.wp.*` globals, already loaded by the
    // Connectors page. Declaring them as script-module deps would prevent WP
    // from emitting this module (the dep resolver finds no matching module).
    wp_register_script_module(
        'ai-provider-for-openai-compatible/connector-ui',
        plugins_url('assets/js/connector-ui.js', __FILE__),
        [
            ['id' => '@wordpress/connectors', 'import' => 'static'],
        ],
        '1.1.0'
    );
    wp_enqueue_script_module('ai-provider-for-openai-compatible/connector-ui');
}
