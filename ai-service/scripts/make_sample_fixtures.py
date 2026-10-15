"""Write golden fixture responses for the sample documents in tests/samples/.

These are hand-checked answers keyed exactly like real recorded responses, so CI exercises the full
extraction path with no API key. To record real model output instead, run the service with
LLM_PROVIDER=fixture and FixtureProvider(record_with=<real provider>).

Usage (from ai-service/):  python -m scripts.make_sample_fixtures
"""

from pathlib import Path

from app.explanation import SYSTEM_PROMPT as EXPLAIN_PROMPT
from app.explanation import Explanation, ExplainRequest
from app.explanation import _user_message as explain_message
from app.extraction import SYSTEM_PROMPT, _user_message
from app.llm.fixture import FixtureProvider
from app.schemas import EXTRACTION_SCHEMAS, DocumentType, provider_schema

SAMPLES = Path(__file__).resolve().parents[1] / "tests" / "samples"

GOLDEN = {
    ("paystub_acme.txt", DocumentType.PAYSTUB): {
        "employer_name": "ACME LOGISTICS INC.",
        "pay_period_start": "2026-09-01",
        "pay_period_end": "2026-09-14",
        "pay_date": "2026-09-19",
        "pay_frequency": "biweekly",
        "gross_pay_cents": 210700,
        "net_pay_cents": 157795,
        "ytd_gross_cents": 3675000,
        "confidence": 0.96,
        "notes": ["Includes 4 hours of overtime this period; regular earnings are 196000 cents."],
    },
    ("bank_statement_cu.txt", DocumentType.BANK_STATEMENT): {
        "statement_start": "2026-09-01",
        "statement_end": "2026-09-30",
        "opening_balance_cents": 41218,
        "closing_balance_cents": 204508,
        "deposits": [
            {"posted_on": "2026-09-05", "amount_cents": 157140, "description": "DIRECT DEP ACME LOGISTICS PAYROLL", "likely_payroll": True},
            {"posted_on": "2026-09-12", "amount_cents": 6000, "description": "ZELLE FROM [NAME]", "likely_payroll": False},
            {"posted_on": "2026-09-19", "amount_cents": 157795, "description": "DIRECT DEP ACME LOGISTICS PAYROLL", "likely_payroll": True},
        ],
        "overdraft_or_nsf_count": 1,
        "confidence": 0.93,
        "notes": ["One overdraft fee on 2026-09-28 although the balance stayed positive; may reflect a same-day pending debit."],
    },
}


# Matches tests/test_explanation.py::DECLINE.
SAMPLE_DECLINE = {
    "outcome": "decline",
    "policy_version": "v1",
    "rule_results": [
        {"code": "MIN_VERIFIED_INCOME", "outcome": "fail", "message": "Verified monthly income $1,210.00 is below the $1,500.00 minimum."},
        {"code": "PAY_DATE_RECENT", "outcome": "pass", "message": "Latest pay date is 12 days before submission."},
    ],
}

GOLDEN_EXPLANATION = {
    "underwriter_summary": "Declined under policy v1. MIN_VERIFIED_INCOME failed: verified monthly income of $1,210.00 is below the $1,500.00 minimum. PAY_DATE_RECENT passed. No further documents would change this outcome unless they evidence additional verifiable income.",
    "applicant_message": "Thank you for applying. We are unable to approve your loan at this time because the income we could verify from your documents is below what this loan requires. If you have additional regular income, you are welcome to apply again with documents that show it.",
    "principal_reasons": ["The income we could verify is below the amount required for this loan."],
}


def main() -> None:
    provider = FixtureProvider()
    for (filename, document_type), response in GOLDEN.items():
        text = (SAMPLES / filename).read_text()
        schema = provider_schema(EXTRACTION_SCHEMAS[document_type])
        path = provider.save("json", SYSTEM_PROMPT, _user_message(document_type, text), schema, response)
        print(f"{filename} -> {path.name}")

    request = ExplainRequest.model_validate(SAMPLE_DECLINE)
    path = provider.save("json", EXPLAIN_PROMPT, explain_message(request), provider_schema(Explanation), GOLDEN_EXPLANATION)
    print(f"sample decline explanation -> {path.name}")


if __name__ == "__main__":
    main()
