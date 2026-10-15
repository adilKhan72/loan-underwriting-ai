"""The demo pack must stay in sync: every demo document's redacted text resolves to a fixture."""

from pathlib import Path

import pytest

from app.extraction import extract
from app.llm.fixture import FixtureProvider
from app.schemas import DocumentType

REDACTED = Path(__file__).resolve().parents[2] / "demo" / "redacted"
pytestmark = pytest.mark.skipif(not REDACTED.exists(), reason="demo pack not mounted")


@pytest.mark.parametrize(
    "name,document_type",
    [
        ("approve_paystub", DocumentType.PAYSTUB),
        ("approve_bank_statement", DocumentType.BANK_STATEMENT),
        ("decline_paystub", DocumentType.PAYSTUB),
    ],
)
def test_demo_document_extracts_via_fixture(name, document_type):
    result = extract(document_type, (REDACTED / f"{name}.txt").read_text(), FixtureProvider())

    assert result.status == "ok", result.errors
