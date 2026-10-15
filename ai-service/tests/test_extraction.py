from app.extraction import MAX_ATTEMPTS, SYSTEM_PROMPT, extract
from app.llm.base import ProviderRefusal
from app.schemas import DocumentType
from tests.conftest import ScriptedProvider


def test_valid_first_answer_is_returned(valid_paystub):
    provider = ScriptedProvider(valid_paystub)

    result = extract(DocumentType.PAYSTUB, "stub text", provider)

    assert result.status == "ok"
    assert result.attempts == 1
    assert result.data["gross_pay_cents"] == 210700
    assert provider.calls[0]["system"] == SYSTEM_PROMPT
    assert '<document type="paystub">\nstub text\n</document>' in provider.calls[0]["user"]


def test_invalid_answer_gets_one_corrective_retry_with_the_errors(valid_paystub):
    bad = dict(valid_paystub, net_pay_cents=999_999)
    provider = ScriptedProvider(bad, valid_paystub)

    result = extract(DocumentType.PAYSTUB, "stub text", provider)

    assert result.status == "ok"
    assert result.attempts == 2
    retry_prompt = provider.calls[1]["user"]
    assert "failed validation" in retry_prompt
    assert "net_pay_cents cannot exceed gross_pay_cents" in retry_prompt


def test_still_invalid_after_retry_needs_review(valid_paystub):
    bad = dict(valid_paystub, confidence=7)
    provider = ScriptedProvider(bad, bad)

    result = extract(DocumentType.PAYSTUB, "stub text", provider)

    assert result.status == "needs_review"
    assert result.data is None
    assert result.attempts == MAX_ATTEMPTS
    assert any("confidence" in error for error in result.errors)


def test_refusal_needs_review_without_retrying():
    provider = ScriptedProvider(ProviderRefusal("model declined (category=None)"))

    result = extract(DocumentType.BANK_STATEMENT, "statement", provider)

    assert result.status == "needs_review"
    assert result.attempts == 1
    assert result.errors == ["model declined (category=None)"]
    assert len(provider.calls) == 1
