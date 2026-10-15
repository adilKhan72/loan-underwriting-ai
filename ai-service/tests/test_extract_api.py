from pathlib import Path

from fastapi.testclient import TestClient

from app.llm.base import ProviderError
from app.llm.factory import get_provider
from app.main import app
from tests.conftest import ScriptedProvider

SAMPLES = Path(__file__).parent / "samples"
client = TestClient(app)


def test_extracts_the_sample_paystub_end_to_end_via_fixtures():
    response = client.post(
        "/v1/extract", json={"document_type": "paystub", "text": (SAMPLES / "paystub_acme.txt").read_text()}
    )

    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "ok"
    assert body["provider"] == "fixture"
    assert body["data"]["pay_frequency"] == "biweekly"
    assert body["data"]["gross_pay_cents"] == 210700
    assert body["data"]["net_pay_cents"] == 157795


def test_extracts_the_sample_bank_statement_end_to_end_via_fixtures():
    response = client.post(
        "/v1/extract",
        json={"document_type": "bank_statement", "text": (SAMPLES / "bank_statement_cu.txt").read_text()},
    )

    assert response.status_code == 200
    data = response.json()["data"]
    payroll = [d for d in data["deposits"] if d["likely_payroll"]]
    assert [d["amount_cents"] for d in payroll] == [157140, 157795]
    assert data["overdraft_or_nsf_count"] == 1


def test_rejects_unknown_document_types_and_empty_text():
    assert client.post("/v1/extract", json={"document_type": "selfie", "text": "x"}).status_code == 422
    assert client.post("/v1/extract", json={"document_type": "paystub", "text": ""}).status_code == 422


def test_provider_outage_is_a_502_so_callers_retry():
    app.dependency_overrides[get_provider] = lambda: ScriptedProvider(ProviderError("anthropic unreachable"))
    try:
        response = client.post("/v1/extract", json={"document_type": "paystub", "text": "x"})
    finally:
        app.dependency_overrides.clear()

    assert response.status_code == 502
    assert response.json()["detail"] == "anthropic unreachable"


def test_fixture_miss_is_permanent_not_an_outage():
    response = client.post("/v1/extract", json={"document_type": "paystub", "text": "a document with no fixture"})

    assert response.status_code == 422
    assert "no fixture" in response.json()["detail"]
