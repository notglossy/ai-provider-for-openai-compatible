# Project instructions for automated PR reviews

This file is appended to the AI reviewers' system prompt on every run
(see `.github/scripts/ai_review.py`, `AI_INSTRUCTIONS_FILE`). It records
settled design decisions so review rounds do not re-litigate them. Keep
entries short and factual; delete them when the code they describe is gone.

## Review posture

- This is a fork of `WordPress/ai-provider-for-openai` generalised to any
  OpenAI-compatible endpoint (OpenAI, OpenRouter, Together, Groq, Ollama,
  vLLM, LM Studio, LiteLLM, ...). Behaviour that assumes OpenAI's hosted API
  specifically (model-ID allowlists, OpenAI-only request fields sent
  unconditionally) is a defect for this project, not a safety feature.
- Upstream is merged periodically. Code that arrives via an upstream merge
  keeps upstream's structure; the fork layers its own behaviour on top
  (configurable base URL, `openai-compatible` provider id, the
  `NotGlossy\AiProviderForOpenAiCompatible` namespace, permissive model
  classification with vendor-prefix and `ft:` normalisation). Prefer fixes
  that survive the next merge over restructuring upstream code.
- Supported floor is PHP 7.4 and PHP AI Client 1.3.1. PHP 8-only syntax and
  unconditional use of newer SDK classes are bugs even when CI's latest
  matrix passes; CI also runs a lowest-dependencies matrix.

## Settled decisions — do not re-flag

Decided on PR #1 (upstream merge through a8a2e2b):

- Do not gate image edits, or any request, on hard-coded OpenAI model IDs
  (for example rejecting `dall-e-3` before calling `/images/edits`). Model
  metadata capabilities drive requirement-based model resolution, and the
  server's own error is the fallback for a direct call.
- `reasoning.encrypted_content` is not requested unconditionally, because
  some OpenAI-compatible servers reject unknown Responses API `include`
  values. Multi-turn reasoning replay currently relies on the server
  resolving reasoning items by `id`. A follow-up may request the encrypted
  content for reasoning models only, merged with the logprobs `include`
  entry.
- Passing a `mask` through custom options to `/images/edits` is
  unsupported: the multipart body carries scalar fields only, and the code
  comment says so. File-typed custom options are a separate feature.
- Tests that exercise capabilities missing from the lowest supported PHP AI
  Client (embedding generation before 1.4.0, for example) skip via
  `interface_exists()` rather than raising the minimum SDK version.
- `.distignore` is the single source of truth for what ships:
  `bin/build.sh` reads it and CI verifies it. The `.wordpress-org/` assets
  stay in the repo, but the plugin is not published on WordPress.org, so
  there is no deploy workflow and no Playground blueprint.
- `.github/workflows/ci.yml` runs on pushes to `trunk` and on pull requests
  with no upstream-repository gate.

Decided on PR #2:

- Upstream's Props Bot and WordPress.org deploy workflows were removed on
  purpose. Do not suggest restoring them after an upstream merge.
