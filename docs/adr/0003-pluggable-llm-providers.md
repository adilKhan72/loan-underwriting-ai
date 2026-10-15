# ADR-0003: Pluggable LLM providers with a deterministic fixture provider

**Status:** Accepted

## Context
Vendor choice changes with price, quality and data-residency requirements. CI must not depend on network
models, API keys or non-deterministic output.

## Decision
One `LLMProvider` interface (`complete_json(schema, messages)`, `complete_text(messages)`) with implementations
for Anthropic Claude, OpenAI, Ollama (local) and **Fixture**, which returns recorded responses keyed by a
hash of the request. Selected by `LLM_PROVIDER`.

## Consequences
- CI and contributors run the whole system with no keys and no network.
- Recording new fixtures is an explicit step, so prompt changes show up as fixture diffs in review.
- Provider-specific features (e.g. native structured output) are used inside each adapter, not leaked to callers.
