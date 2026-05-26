<?php

/**
 * Uninstall handler — deletes plugin options when the user removes the plugin.
 *
 * @package NotGlossy\AiProviderForOpenAiCompatible
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// The API key option (`connectors_ai_openai_compatible_api_key`) is intentionally
// NOT deleted here — it belongs to core's Connectors API, which manages its
// lifecycle. Removing it on plugin uninstall would orphan a user-entered secret in
// a way the Connectors UI couldn't anticipate.
$options = [
    'ai_provider_for_openai_compatible_base_url',
    'ai_provider_for_openai_compatible_default_model',
    'ai_provider_for_openai_compatible_provider_label',
    'ai_provider_for_openai_compatible_strict_models',
    'ai_provider_for_openai_compatible_api_style',
    'ai_provider_for_openai_compatible_detected_api_style',
];

foreach ($options as $option) {
    delete_option($option);
}
