"""Write golden fixture responses for the sample documents in tests/samples/.

These are hand-checked answers keyed exactly like real recorded responses, so CI exercises the full
extraction path with no API key. To record real model output instead, run the service with
LLM_PROVIDER=fixture and FixtureProvider(record_with=<real provider>).

Usage (from ai-service/):  python -m scripts.make_sample_fixtures
"""

from pathlib import Path

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


def main() -> None:
    provider = FixtureProvider()
    for (filename, document_type), response in GOLDEN.items():
        text = (SAMPLES / filename).read_text()
        schema = provider_schema(EXTRACTION_SCHEMAS[document_type])
        path = provider.save("json", SYSTEM_PROMPT, _user_message(document_type, text), schema, response)
        print(f"{filename} -> {path.name}")


if __name__ == "__main__":
    main()
