from typing import Literal

from pydantic_settings import BaseSettings, SettingsConfigDict

Provider = Literal["fixture", "anthropic", "openai", "ollama"]


class Settings(BaseSettings):
    """All runtime configuration, from environment / .env.

    `fixture` is the default provider on purpose: the service starts and its tests run
    with no API keys and no network (see docs/adr/0003-pluggable-llm-providers.md).
    """

    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    llm_provider: Provider = "fixture"

    anthropic_api_key: str | None = None
    anthropic_model: str = "claude-opus-5-5"

    openai_api_key: str | None = None
    openai_model: str = "gpt-4.1-mini"

    ollama_url: str = "http://localhost:11434"
    ollama_model: str = "llama3.2:3b"


settings = Settings()
