=== AI Provider for OpenAI-Compatible Endpoints ===
Contributors: notglossy
Tags: ai, openai, openai-compatible, ollama, llm
Requires at least: 7.0
Tested up to: 7.1
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI Provider for any OpenAI-compatible endpoint (OpenAI, Together.ai, Groq, OpenRouter, Ollama, vLLM, LM Studio, …) for the PHP AI Client SDK.

== Description ==

This plugin provides an OpenAI-compatible AI provider for the PHP AI Client SDK. Point it at any server that speaks OpenAI's HTTP API — OpenAI itself, hosted gateways like Together.ai, Groq, OpenRouter, or local servers like Ollama, vLLM, and LM Studio.

Forked from [WordPress/ai-provider-for-openai](https://github.com/WordPress/ai-provider-for-openai), which targets OpenAI's hosted API specifically. This fork generalizes the provider to any OpenAI-compatible endpoint and adds a WP 7.0 Connectors-API integration with an inline Base URL + API Key panel on Settings → Connectors.

**Features:**

* Registers as a WP 7.0 Connector — API key lives on the core **Settings → Connectors** screen
* Configurable base URL and other provider options via the plugin's own settings screen (with `wp-config.php` constant overrides)
* Text generation via either the Responses API or the older Chat Completions API
* Smart auto-selection of API style (Responses for api.openai.com, Chat Completions for everything else)
* Image generation with DALL-E and GPT-Image models (against servers that implement the OpenAI Images API)
* Function calling and web search support (where the server implements them)
* Permissive model classification by default — local server models like `llama3` or `mistral` appear as text-generation models without per-server tweaking
* Strict mode for OpenAI-only deployments

Models are dynamically discovered from the configured server's `GET /v1/models` endpoint.

**Requirements:**

* WordPress 7.0 or higher (the PHP AI Client SDK ships with core)
* PHP 7.4 or higher
* An API key from your chosen OpenAI-compatible service

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/ai-provider-for-openai-compatible/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Visit **Settings → Connectors**, expand the **OpenAI-Compatible** card, and enter your Base URL and API Key
4. **Important:** visit **Settings → AI**, open the kebab menu (top-right), and enable **Model selection** under Developer tools — without this toggle, AI features fall back to a hardcoded provider list that doesn't include this connector
5. Optional: visit **Settings → AI Provider (OpenAI-Compatible)** for provider label, API style, and model classification

== Frequently Asked Questions ==

= How do I get an API key? =

Depends on which endpoint you point at. OpenAI: [platform.openai.com](https://platform.openai.com/). Other providers: their respective dashboards. Local servers (Ollama, LM Studio) typically accept any string as the key.

= Does this plugin require anything else? =

WordPress 7.0 ships the PHP AI Client SDK in core, so no separate plugin install is needed. Just activate this plugin, configure the connector, and you're set.

= My AI feature says "no supported provider" even though I configured the connector — what's wrong? =

The WordPress AI plugin hides the per-feature provider/model picker behind a developer-tools toggle. Go to **Settings → AI**, open the kebab menu (top-right), and turn on **Model selection** under Developer tools. Per-feature dropdowns will then appear, and you can select **OpenAI-Compatible** plus a specific model for each AI feature.

= Can I still use my existing OPENAI_API_KEY constant? =

Yes. For upgrade continuity, the legacy `OPENAI_API_KEY` env/constant is honored as a fallback when no API key is configured on the new settings page or via `AI_PROVIDER_FOR_OPENAI_COMPATIBLE_API_KEY`. This fallback may be removed in a future major release.

= Does this support Ollama / LM Studio / vLLM? =

Yes — they all implement the OpenAI Chat Completions API. Set the Base URL to your server (e.g. `http://localhost:11434/v1`), leave API Style at `Auto`, and the smart default will pick Chat Completions.

== Upgrade Notice ==

= 1.1.0 =

**Breaking:** the registered provider ID is now `openai-compatible` (previously `openai`). Any code calling `->usingProvider('openai')` must be updated to `'openai-compatible'` to keep using this plugin's provider. Note that `'openai'` still resolves — to the PHP AI Client SDK's own built-in OpenAI provider, which is leaner (no multimodal auto-detection, no logo). The existing `OPENAI_API_KEY` env/constant continues to work as a fallback. Default request URL and behavior against OpenAI are otherwise unchanged.

== Known limitations ==

* Image generation only auto-classifies models with OpenAI naming (`dall-e-*`, `gpt-image-*`); compatible servers with custom image models won't expose them through this provider.
* Text-to-speech targets OpenAI naming (`tts-*`, `*-tts`); compatible servers with custom speech models need matching IDs.
* The `Auto` API-style mode uses a smart default (Responses for api.openai.com, Chat Completions elsewhere). Runtime probe-and-cache is a follow-up; override explicitly via the API Style setting if needed.

== Changelog ==

= 1.1.0 =

* Make the plugin work with any OpenAI-compatible endpoint. Adds a Settings page (and `AI_PROVIDER_FOR_OPENAI_COMPATIBLE_*` constants) for Base URL, Provider Label, API Style, and Model Classification.
* Register as a WP 7.0 Connector. API key now lives on the core **Settings → Connectors** screen (option `connectors_ai_openai_compatible_api_key`). The plugin enriches the auto-discovered connector via `wp_connectors_init` with a richer description, the plugin file path, and a stable `setting_name`.
* Add Chat Completions API support (in addition to the existing Responses API). Auto selection picks the right one per host.
* Permissive model classification: unmatched model IDs are exposed as text-generation by default (toggle to strict on the settings page).
* Renames the registered provider ID from `openai` to `openai-compatible`. Legacy `OPENAI_API_KEY` continues to be honored as a fallback.
= 1.1.0 - 2026-08-17 =

**Added**

* Support for OpenAI embedding models, including batch inputs, custom dimensions, token usage, and result metadata ([#34](https://github.com/WordPress/ai-provider-for-openai/pull/34)).
* Support for fine-tuned OpenAI models by deriving capabilities from their underlying base models ([#17](https://github.com/WordPress/ai-provider-for-openai/pull/17)).
* Support for editing and refining generated images using reference images and OpenAI’s image-editing endpoint ([#29](https://github.com/WordPress/ai-provider-for-openai/pull/29)).
* A `TokenLimitReachedException` when OpenAI responses are incomplete because the maximum output-token limit was reached ([#10](https://github.com/WordPress/ai-provider-for-openai/pull/10)).

**Changed**

* Made sampling-option capabilities model-aware for reasoning models, added Responses API support for log probabilities, and reject incompatible sampling and reasoning configurations before sending a request ([#40](https://github.com/WordPress/ai-provider-for-openai/pull/40)).
* Bumped WordPress tested-up-to version 7.1 ([#43](https://github.com/WordPress/ai-provider-for-openai/pull/43)).

**Fixed**

* Function-call name handling for names that do not meet OpenAI’s naming requirements, while preserving the original PHP AI Client function names returned to callers ([#31](https://github.com/WordPress/ai-provider-for-openai/pull/31)).

= 1.0.3 =

* Add a provider logo to the metadata if the client version > 1.3.0 ([#19](https://github.com/WordPress/ai-provider-for-openai/pull/19)).
* Fix mapping of models that support multimodal inputs ([#22](https://github.com/WordPress/ai-provider-for-openai/pull/22)).

= 1.0.2 =

* Add plugin directory assets by @shaunandrews in https://github.com/WordPress/ai-provider-for-openai/pull/7
* Update tags in readme.txt by @jeffpaul in https://github.com/WordPress/ai-provider-for-openai/pull/9
* Fix missing input and output modality combinations. by @felixarntz in https://github.com/WordPress/ai-provider-for-openai/pull/11
* Add provider description by @felixarntz in https://github.com/WordPress/ai-provider-for-openai/pull/12

= 1.0.1 =

* Initial release of the plugin
* Support for GPT text generation models
* Support for DALL-E image generation models
* Function calling support
* Web search support

= 1.0.0 =

* Initial release of the Composer package
