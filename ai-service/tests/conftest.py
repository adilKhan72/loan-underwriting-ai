from typing import Any

import pytest


class ScriptedProvider:
    """Returns queued responses in order and records every call, for testing extraction logic."""

    name = "scripted"

    def __init__(self, *responses: Any):
        self.responses = list(responses)
        self.calls: list[dict[str, Any]] = []

    def complete_json(self, system: str, user: str, schema: dict[str, Any]) -> dict[str, Any]:
        self.calls.append({"system": system, "user": user, "schema": schema})
        response = self.responses.pop(0)
        if isinstance(response, Exception):
            raise response
        return response

    def complete_text(self, system: str, user: str) -> str:
        raise NotImplementedError


VALID_PAYSTUB = {
    "employer_name": "Acme",
    "pay_period_start": "2026-09-01",
    "pay_period_end": "2026-09-14",
    "pay_date": "2026-09-19",
    "pay_frequency": "biweekly",
    "gross_pay_cents": 210700,
    "net_pay_cents": 157795,
    "ytd_gross_cents": None,
    "confidence": 0.9,
    "notes": [],
}


@pytest.fixture
def valid_paystub() -> dict[str, Any]:
    return dict(VALID_PAYSTUB)
