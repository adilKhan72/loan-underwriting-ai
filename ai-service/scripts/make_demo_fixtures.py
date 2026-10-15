"""Golden fixture answers for the demo documents in /demo, so `docker compose up` gives real decisions with no API key.

The keys are the exact redacted text the API sends (demo/redacted/*.txt, produced by `php artisan pii:preview`),
so this only covers those demo PDFs; any other document needs a real provider (Claude / OpenAI / Ollama).

Usage (from the repo root):  python -m scripts.make_demo_fixtures  (run inside ai-service/, with ../demo mounted)
"""

from pathlib import Path

from app.extraction import SYSTEM_PROMPT, _user_message
from app.llm.fixture import FixtureProvider
from app.schemas import EXTRACTION_SCHEMAS, DocumentType, provider_schema

REDACTED = Path(__file__).resolve().parents[2] / "demo" / "redacted"

GOLDEN = {
    ("approve_paystub", DocumentType.PAYSTUB): {
        "employer_name": "NORTHWIND SUPPLY CO.",
        "pay_period_start": "2026-09-27",
        "pay_period_end": "2026-10-10",
        "pay_date": "2026-10-12",
        "pay_frequency": "biweekly",
        "gross_pay_cents": 264000,
        "net_pay_cents": 209474,
        "ytd_gross_cents": 5016000,
        "confidence": 0.95,
        "notes": [],
    },
    ("approve_bank_statement", DocumentType.BANK_STATEMENT): {
        "statement_start": "2026-09-15",
        "statement_end": "2026-10-14",
        "opening_balance_cents": 120455,
        "closing_balance_cents": 410185,
        "deposits": [
            {"posted_on": "2026-09-28", "amount_cents": 209474, "description": "DIRECT DEP NORTHWIND SUPPLY PAYROLL", "likely_payroll": True},
            {"posted_on": "2026-10-12", "amount_cents": 209474, "description": "DIRECT DEP NORTHWIND SUPPLY PAYROLL", "likely_payroll": True},
        ],
        "overdraft_or_nsf_count": 0,
        "confidence": 0.94,
        "notes": [],
    },
    ("decline_paystub", DocumentType.PAYSTUB): {
        "employer_name": "CORNER CAFE LLC",
        "pay_period_start": "2026-10-04",
        "pay_period_end": "2026-10-10",
        "pay_date": "2026-10-13",
        "pay_frequency": "weekly",
        "gross_pay_cents": 31200,
        "net_pay_cents": 28140,
        "ytd_gross_cents": None,
        "confidence": 0.93,
        "notes": [],
    },
}


def main() -> None:
    provider = FixtureProvider()
    for (name, document_type), response in GOLDEN.items():
        text = (REDACTED / f"{name}.txt").read_text()
        schema = provider_schema(EXTRACTION_SCHEMAS[document_type])
        path = provider.save("json", SYSTEM_PROMPT, _user_message(document_type, text), schema, response)
        print(f"{name} -> {path.name}")


if __name__ == "__main__":
    main()
