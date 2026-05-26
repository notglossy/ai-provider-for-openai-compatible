# AI Provider for OpenAI-Compatible Endpoints

A WordPress plugin that adds an AI Provider for **any OpenAI-compatible endpoint** — OpenAI itself, Together.ai, Groq, OpenRouter, Ollama, vLLM, LM Studio, and others — to the [PHP AI Client](https://github.com/WordPress/php-ai-client) SDK that ships with WordPress 7.0.

> Forked from [`WordPress/ai-provider-for-openai`](https://github.com/WordPress/ai-provider-for-openai), which targets OpenAI's hosted API specifically. This fork generalizes the provider to any OpenAI-compatible endpoint and adds a WP 7.0 Connectors-API integration with an inline Base URL + API Key panel.

On WordPress 7.0+ the plugin registers itself as a [Connector](https://make.wordpress.org/core/2026/03/18/introducing-the-connectors-api-in-wordpress-7-0/) — the API key lives on the core **Settings → Connectors** screen alongside the Base URL, and the plugin's own settings page handles the rest (provider label, API style, model classification).

## Requirements

- WordPress 7.0 or higher
- PHP 7.4 or higher

## Installation

1. Download the plugin zip
2. In **wp-admin → Plugins → Add New → Upload Plugin**, choose the zip and install
3. Activate the plugin
4. Visit **Settings → Connectors**, expand the **OpenAI-Compatible** card, and enter your Base URL and API Key
5. **Important:** visit **Settings → AI**, click the kebab menu (top-right), and turn on **Model selection** ("Select a specific provider and model per feature") — see [Per-feature model selection](#per-feature-model-selection) below for why this matters
6. Optional: for provider label, API style, and model classification, visit **Settings → AI Provider (OpenAI-Compatible)**

### Per-feature model selection

The WordPress AI plugin hides the per-feature provider/model picker behind a developer-tools toggle. With the toggle **off**, AI features (Alt Text Generation, Meta Description, etc.) consult a hardcoded preference list that only references the built-in `anthropic` / `google` / `openai` providers — your `openai-compatible` connector will not be considered, and features will report "no supported provider" even with a working API key.

To enable the picker:

1. Open **Settings → AI** in the WordPress admin
2. Click the three-dot kebab menu in the top-right
3. Under **Developer tools**, turn on **Model selection**

Per-feature dropdowns will then appear inline under each AI feature toggle. Pick **OpenAI-Compatible** as the provider and any specific model you want to use for that feature.

## Configuration

Configuration is split across two admin screens:

**Settings → Connectors** (WP core's Connectors screen)

| Setting | Description |
|---------|-------------|
| Base URL | Full URL including the version path. Default: `https://api.openai.com/v1`. |
| API Key | Sent as a `Bearer` token on every request. Stored at option `connectors_ai_openai_compatible_api_key`. |

**Settings → AI Provider (OpenAI-Compatible)** (this plugin's own page)

| Setting | Description |
|---------|-------------|
| Provider Label | Display name shown in connector / provider listings. Default: `OpenAI-Compatible`. |
| API Style | `Auto` (recommended) / `Responses` / `Chat Completions`. Auto picks Responses for `api.openai.com` and Chat Completions for everything else. |
| Model Classification | Strict (only OpenAI-named models) vs. permissive (default — unknown IDs become text-generation models). |

### Constant overrides

Any of these constants defined in `wp-config.php` override the corresponding option and display as read-only in the UI:

- `AI_PROVIDER_FOR_OPENAI_COMPATIBLE_BASE_URL`
- `AI_PROVIDER_FOR_OPENAI_COMPATIBLE_API_KEY`
- `AI_PROVIDER_FOR_OPENAI_COMPATIBLE_PROVIDER_LABEL`
- `AI_PROVIDER_FOR_OPENAI_COMPATIBLE_API_STYLE`
- `AI_PROVIDER_FOR_OPENAI_COMPATIBLE_STRICT_MODELS`

### Legacy `OPENAI_API_KEY` fallback

For upgrade continuity, the legacy `OPENAI_API_KEY` env var / PHP constant is still consulted when no other API key source is set. This fallback may be removed in a future major release.

## Supported Endpoints

Any server that implements either OpenAI's `POST /v1/chat/completions` (Chat Completions API) or `POST /v1/responses` (Responses API) over a Bearer-token-authenticated endpoint should work. Examples:

- **OpenAI** (`https://api.openai.com/v1`) — supports both Responses and Chat Completions.
- **Together.ai** (`https://api.together.xyz/v1`) — Chat Completions.
- **Groq** (`https://api.groq.com/openai/v1`) — Chat Completions.
- **OpenRouter** (`https://openrouter.ai/api/v1`) — Chat Completions.
- **Ollama** (`http://localhost:11434/v1`) — Chat Completions.
- **LM Studio** (`http://localhost:1234/v1`) — Chat Completions.

Models are discovered dynamically from the configured server's `GET /v1/models` endpoint.

### Model capability detection

Models are classified by ID, with vendor-prefix awareness (so `openai/gpt-4o` from OpenRouter is treated the same as `gpt-4o` direct from OpenAI):

- **Vision (image input)** — auto-detected for the major multimodal families: OpenAI GPT-4o / GPT-4.1 / GPT-5 / o1-o4, Anthropic Claude 3+, Google Gemini 1.5+, Mistral Pixtral, plus any model with `vision`, `-vl-`, `llava`, `cogvlm`, `fuyu`, or `moondream` in its ID.
- **Image generation** — auto-detected only for `dall-e-*` and `gpt-image-*` IDs. The plugin uses OpenAI's `/v1/images/generations` request shape, which most third-party aggregators don't implement, so this stays conservative.
- **Text-to-speech** — auto-detected for `tts-*` IDs; model class is not yet implemented.
- **Everything else** — in permissive mode (the default), unknown model IDs are exposed as text-generation models. Strict mode filters them out.

## Known limitations

- **Image generation** assumes OpenAI's `/images/generations` request/response shape. OpenRouter doesn't ship that endpoint; other aggregators vary.
- **Text-to-speech** is not yet implemented.
- **Auto API-style detection** uses a smart default (Responses for `api.openai.com`, Chat Completions for everything else). Runtime probe-and-cache for unusual servers is a follow-up; switch the API Style setting explicitly to override.

## Upgrading from 1.0.x

- **Provider ID renamed:** `'openai'` → `'openai-compatible'`. Any code calling `->usingProvider('openai')` must be updated to `'openai-compatible'` to keep talking to *this* plugin.
- **`'openai'` still works** — but it now resolves to the SDK's *built-in* `OpenAiProvider` that the SDK auto-registers in `AiClient::defaultRegistry()` since version 0.4. The built-in is a leaner OpenAI-only provider (hardcoded base URL, no cross-provider model detection). To get this plugin's behavior, switch the call site to `'openai-compatible'`.
- **Existing `OPENAI_API_KEY`** is still honored as a fallback — no immediate action required, but configure the new connector or `AI_PROVIDER_FOR_OPENAI_COMPATIBLE_API_KEY` constant before the fallback is removed in a future major.

## License

GPL-2.0-or-later
