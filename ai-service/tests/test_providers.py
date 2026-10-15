"""Adapter tests: assert the exact request each vendor receives and how responses map back. No network."""

import json
from types import SimpleNamespace
from unittest.mock import MagicMock

import httpx
import pytest

from app.llm.anthropic_provider import FALLBACK_BETA, AnthropicProvider
from app.llm.base import ProviderError, ProviderRefusal
from app.llm.ollama_provider import OllamaProvider
from app.llm.openai_provider import OpenAIProvider

SCHEMA = {"type": "object", "properties": {"x": {"type": "integer"}}, "required": ["x"], "additionalProperties": False}


def _anthropic_response(stop_reason="end_turn", text='{"x": 1}', stop_details=None):
    return SimpleNamespace(
        stop_reason=stop_reason,
        stop_details=stop_details,
        content=[SimpleNamespace(type="thinking", thinking=""), SimpleNamespace(type="text", text=text)],
    )


def test_anthropic_requests_structured_output_with_default_fallbacks():
    client = MagicMock()
    client.beta.messages.create.return_value = _anthropic_response()

    result = AnthropicProvider(model="claude-opus-5-5", client=client).complete_json("sys", "doc", SCHEMA)

    assert result == {"x": 1}
    kwargs = client.beta.messages.create.call_args.kwargs
    assert kwargs["model"] == "claude-opus-5-5"
    assert kwargs["betas"] == [FALLBACK_BETA]
    assert kwargs["fallbacks"] == "default"
    assert kwargs["output_config"]["format"] == {"type": "json_schema", "schema": SCHEMA}
    assert kwargs["system"] == "sys"
    assert kwargs["messages"] == [{"role": "user", "content": "doc"}]


def test_anthropic_refusal_is_raised_before_reading_content():
    client = MagicMock()
    client.beta.messages.create.return_value = _anthropic_response(
        stop_reason="refusal", text="", stop_details=SimpleNamespace(category="cyber")
    )

    with pytest.raises(ProviderRefusal, match="category=cyber"):
        AnthropicProvider(model="claude-opus-5-5", client=client).complete_json("sys", "doc", SCHEMA)


def test_anthropic_truncation_is_an_error_not_partial_json():
    client = MagicMock()
    client.beta.messages.create.return_value = _anthropic_response(stop_reason="max_tokens", text='{"x"')

    with pytest.raises(ProviderError, match="max_tokens"):
        AnthropicProvider(model="claude-opus-5-5", client=client).complete_json("sys", "doc", SCHEMA)


def _mock_client(base_url, handler):
    return httpx.Client(base_url=base_url, transport=httpx.MockTransport(handler))


def test_openai_uses_strict_json_schema_response_format():
    seen = {}

    def handler(request):
        seen["body"] = json.loads(request.content)
        return httpx.Response(200, json={"choices": [{"message": {"content": '{"x": 2}', "refusal": None}}]})

    provider = OpenAIProvider(model="m", api_key="k", client=_mock_client("https://api.openai.com/v1", handler))

    assert provider.complete_json("sys", "doc", SCHEMA) == {"x": 2}
    response_format = seen["body"]["response_format"]
    assert response_format["type"] == "json_schema"
    assert response_format["json_schema"]["strict"] is True
    assert response_format["json_schema"]["schema"] == SCHEMA


def test_openai_refusal_maps_to_provider_refusal():
    def handler(request):
        return httpx.Response(200, json={"choices": [{"message": {"content": None, "refusal": "no"}}]})

    provider = OpenAIProvider(model="m", api_key="k", client=_mock_client("https://api.openai.com/v1", handler))

    with pytest.raises(ProviderRefusal):
        provider.complete_json("sys", "doc", SCHEMA)


def test_openai_requires_a_key():
    with pytest.raises(ProviderError, match="OPENAI_API_KEY"):
        OpenAIProvider(model="m", api_key=None)


def test_ollama_constrains_decoding_to_the_schema_at_temperature_zero():
    seen = {}

    def handler(request):
        seen["body"] = json.loads(request.content)
        return httpx.Response(200, json={"message": {"content": '{"x": 3}'}})

    provider = OllamaProvider(model="llama3.2:3b", base_url="http://ollama", client=_mock_client("http://ollama", handler))

    assert provider.complete_json("sys", "doc", SCHEMA) == {"x": 3}
    assert seen["body"]["format"] == SCHEMA
    assert seen["body"]["stream"] is False
    assert seen["body"]["options"]["temperature"] == 0


def test_ollama_http_error_explains_likely_cause():
    provider = OllamaProvider(
        model="llama3.2:3b", base_url="http://ollama", client=_mock_client("http://ollama", lambda r: httpx.Response(404))
    )

    with pytest.raises(ProviderError, match="pulled"):
        provider.complete_json("sys", "doc", SCHEMA)
