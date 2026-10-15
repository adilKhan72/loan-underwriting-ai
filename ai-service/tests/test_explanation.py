import pytest
from fastapi.testclient import TestClient

from app.explanation import SYSTEM_PROMPT, ExplainRequest, ExplanationFailed, explain
from app.llm.base import ProviderError, ProviderRefusal
from app.llm.factory import get_provider
from app.main import app
from tests.conftest import ScriptedProvider

DECLINE = {
    "outcome": "decline",
    "policy_version": "v1",
    "rule_results": [
        {"code": "MIN_VERIFIED_INCOME", "outcome": "fail", "message": "Verified monthly income $1,210.00 is below the $1,500.00 minimum."},
        {"code": "PAY_DATE_RECENT", "outcome": "pass", "message": "Latest pay date is 12 days before submission."},
    ],
}

GOOD = {
    "underwriter_summary": "Declined under MIN_VERIFIED_INCOME: verified income $1,210/month vs $1,500 minimum.",
    "applicant_message": "We can't approve your application because your verified income is below what this loan requires.",
    "principal_reasons": ["Verified income is too low for the requested loan."],
}

client = TestClient(app)


def test_decision_facts_are_passed_to_the_model_and_prose_returned():
    provider = ScriptedProvider(GOOD)

    result = explain(ExplainRequest.model_validate(DECLINE), provider)

    assert result.principal_reasons == ["Verified income is too low for the requested loan."]
    call = provider.calls[0]
    assert call["system"] == SYSTEM_PROMPT
    assert "Decision: decline (policy v1)" in call["user"]
    assert "[fail] MIN_VERIFIED_INCOME" in call["user"]


def test_internal_rule_codes_in_the_applicant_message_trigger_a_retry():
    leaky = dict(GOOD, applicant_message="Declined due to MIN_VERIFIED_INCOME being below the policy threshold for you.")
    provider = ScriptedProvider(leaky, GOOD)

    result = explain(ExplainRequest.model_validate(DECLINE), provider)

    assert result.applicant_message == GOOD["applicant_message"]
    assert "internal code" in provider.calls[1]["user"]


def test_principal_reasons_are_dropped_unless_declined():
    request = ExplainRequest.model_validate(dict(DECLINE, outcome="refer"))

    result = explain(request, ScriptedProvider(GOOD))

    assert result.principal_reasons == []


def test_repeated_invalid_output_fails_cleanly():
    bad = dict(GOOD, underwriter_summary="short")

    with pytest.raises(ExplanationFailed):
        explain(ExplainRequest.model_validate(DECLINE), ScriptedProvider(bad, bad))


def test_refusal_fails_without_retrying():
    provider = ScriptedProvider(ProviderRefusal("model declined"))

    with pytest.raises(ExplanationFailed):
        explain(ExplainRequest.model_validate(DECLINE), provider)
    assert len(provider.calls) == 1


def test_explain_endpoint_end_to_end_via_fixture():
    response = client.post("/v1/explain", json=DECLINE)

    assert response.status_code == 200
    body = response.json()
    assert body["provider"] == "fixture"
    assert body["principal_reasons"]
    assert "MIN_VERIFIED_INCOME" not in body["applicant_message"]


def test_explain_endpoint_maps_failures_to_status_codes():
    app.dependency_overrides[get_provider] = lambda: ScriptedProvider(ProviderError("down"))
    try:
        assert client.post("/v1/explain", json=DECLINE).status_code == 502
        app.dependency_overrides[get_provider] = lambda: ScriptedProvider(ProviderRefusal("no"))
        assert client.post("/v1/explain", json=DECLINE).status_code == 422
    finally:
        app.dependency_overrides.clear()


def test_explain_rejects_requests_without_rule_results():
    assert client.post("/v1/explain", json=dict(DECLINE, rule_results=[])).status_code == 422


def test_explain_fixture_miss_is_permanent_not_an_outage():
    request = dict(DECLINE, rule_results=[{"code": "NEW_RULE", "outcome": "pass", "message": "never recorded"}])

    assert client.post("/v1/explain", json=request).status_code == 422
