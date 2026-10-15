from functools import lru_cache

from app.config import Settings, settings
from app.llm.base import LLMProvider


def build_provider(config: Settings) -> LLMProvider:
    # Imports are local so the fixture path (CI, tests) never needs vendor credentials.
    if config.llm_provider == "fixture":
        from app.llm.fixture import FixtureProvider

        return FixtureProvider()
    if config.llm_provider == "anthropic":
        from app.llm.anthropic_provider import AnthropicProvider

        return AnthropicProvider(model=config.anthropic_model, api_key=config.anthropic_api_key)
    if config.llm_provider == "openai":
        from app.llm.openai_provider import OpenAIProvider

        return OpenAIProvider(model=config.openai_model, api_key=config.openai_api_key)
    if config.llm_provider == "ollama":
        from app.llm.ollama_provider import OllamaProvider

        return OllamaProvider(model=config.ollama_model, base_url=config.ollama_url)
    raise ValueError(f"unknown LLM_PROVIDER {config.llm_provider!r}")


@lru_cache(maxsize=1)
def get_provider() -> LLMProvider:
    """FastAPI dependency: one provider (and one HTTP connection pool) per process."""
    return build_provider(settings)
