import pytest
from pydantic import ValidationError

from app.schemas import BankStatementExtraction, PaystubExtraction, provider_schema


def test_valid_paystub_parses(valid_paystub):
    parsed = PaystubExtraction.model_validate(valid_paystub)
    assert parsed.gross_pay_cents == 210700


def test_net_pay_above_gross_is_rejected(valid_paystub):
    valid_paystub["net_pay_cents"] = valid_paystub["gross_pay_cents"] + 1
    with pytest.raises(ValidationError, match="net_pay_cents cannot exceed"):
        PaystubExtraction.model_validate(valid_paystub)


def test_reversed_pay_period_is_rejected(valid_paystub):
    valid_paystub["pay_period_end"] = "2026-08-01"
    with pytest.raises(ValidationError, match="before pay_period_start"):
        PaystubExtraction.model_validate(valid_paystub)


def test_unknown_fields_are_rejected(valid_paystub):
    valid_paystub["ssn"] = "900-00-0000"
    with pytest.raises(ValidationError):
        PaystubExtraction.model_validate(valid_paystub)


def test_missing_fields_are_rejected_null_is_required_to_be_explicit(valid_paystub):
    del valid_paystub["ytd_gross_cents"]
    with pytest.raises(ValidationError):
        PaystubExtraction.model_validate(valid_paystub)


def test_confidence_must_be_between_zero_and_one(valid_paystub):
    valid_paystub["confidence"] = 1.4
    with pytest.raises(ValidationError):
        PaystubExtraction.model_validate(valid_paystub)


def test_provider_schema_strips_bounds_but_keeps_every_field_required():
    schema = provider_schema(PaystubExtraction)

    assert "minimum" not in str(schema) and "maximum" not in str(schema)
    assert set(schema["required"]) == set(PaystubExtraction.model_fields)
    assert schema["additionalProperties"] is False


def test_provider_schema_handles_nested_models():
    schema = provider_schema(BankStatementExtraction)
    deposit = schema["$defs"]["Deposit"]

    assert deposit["additionalProperties"] is False
    assert "exclusiveMinimum" not in deposit["properties"]["amount_cents"]
