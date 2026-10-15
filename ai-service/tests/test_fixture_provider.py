import pytest

from app.llm.fixture import FixtureMissingError, FixtureProvider, fixture_key
from tests.conftest import ScriptedProvider

SCHEMA = {"type": "object"}


def test_key_is_stable_and_sensitive_to_every_input():
    base = fixture_key("json", "sys", "user", SCHEMA)

    assert base == fixture_key("json", "sys", "user", {"type": "object"})
    assert base != fixture_key("json", "sys2", "user", SCHEMA)
    assert base != fixture_key("json", "sys", "user2", SCHEMA)
    assert base != fixture_key("json", "sys", "user", {"type": "array"})
    assert base != fixture_key("text", "sys", "user", SCHEMA)


def test_missing_fixture_fails_loudly_with_the_key(tmp_path):
    provider = FixtureProvider(fixture_dir=tmp_path)

    with pytest.raises(FixtureMissingError) as error:
        provider.complete_json("sys", "user", SCHEMA)

    assert fixture_key("json", "sys", "user", SCHEMA) in str(error.value)


def test_saved_fixture_is_replayed(tmp_path):
    provider = FixtureProvider(fixture_dir=tmp_path)
    provider.save("json", "sys", "user", SCHEMA, {"answer": 42})

    assert provider.complete_json("sys", "user", SCHEMA) == {"answer": 42}


def test_record_mode_calls_the_real_provider_once_then_replays(tmp_path):
    real = ScriptedProvider({"answer": 1})
    provider = FixtureProvider(fixture_dir=tmp_path, record_with=real)

    assert provider.complete_json("sys", "user", SCHEMA) == {"answer": 1}
    assert provider.complete_json("sys", "user", SCHEMA) == {"answer": 1}
    assert len(real.calls) == 1
    assert len(list(tmp_path.glob("*.json"))) == 1
