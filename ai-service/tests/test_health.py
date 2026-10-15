from fastapi.testclient import TestClient

from app.config import Settings
from app.main import app

client = TestClient(app)


def test_health_reports_ok_and_active_provider():
    response = client.get("/health")

    assert response.status_code == 200
    assert response.json() == {"status": "ok", "provider": "fixture"}


def test_fixture_is_the_default_provider(monkeypatch):
    monkeypatch.delenv("LLM_PROVIDER", raising=False)
    assert Settings(_env_file=None).llm_provider == "fixture"


def test_provider_is_configurable_from_environment(monkeypatch):
    monkeypatch.setenv("LLM_PROVIDER", "ollama")
    assert Settings(_env_file=None).llm_provider == "ollama"
