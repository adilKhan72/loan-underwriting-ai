import copy
from datetime import date
from enum import Enum
from typing import Any, Literal

from pydantic import BaseModel, ConfigDict, Field, model_validator


class DocumentType(str, Enum):
    PAYSTUB = "paystub"
    BANK_STATEMENT = "bank_statement"


class _Strict(BaseModel):
    # Unknown keys are rejected so a model can't smuggle extra fields past validation.
    model_config = ConfigDict(extra="forbid")


class PaystubExtraction(_Strict):
    """Fields are required but nullable: the model must say "not on the document" explicitly
    (null) rather than silently omitting a field."""

    employer_name: str | None
    pay_period_start: date | None
    pay_period_end: date | None
    pay_date: date | None
    pay_frequency: Literal["weekly", "biweekly", "semimonthly", "monthly"] | None
    gross_pay_cents: int | None = Field(ge=0)
    net_pay_cents: int | None = Field(ge=0)
    ytd_gross_cents: int | None = Field(ge=0)
    confidence: float = Field(ge=0, le=1)
    notes: list[str]

    @model_validator(mode="after")
    def _consistent(self) -> "PaystubExtraction":
        if self.gross_pay_cents is not None and self.net_pay_cents is not None:
            if self.net_pay_cents > self.gross_pay_cents:
                raise ValueError("net_pay_cents cannot exceed gross_pay_cents")
        if self.pay_period_start and self.pay_period_end and self.pay_period_end < self.pay_period_start:
            raise ValueError("pay_period_end is before pay_period_start")
        return self


class Deposit(_Strict):
    posted_on: date
    amount_cents: int = Field(gt=0)
    description: str
    likely_payroll: bool


class BankStatementExtraction(_Strict):
    statement_start: date | None
    statement_end: date | None
    opening_balance_cents: int | None
    closing_balance_cents: int | None
    deposits: list[Deposit]
    overdraft_or_nsf_count: int | None = Field(ge=0)
    confidence: float = Field(ge=0, le=1)
    notes: list[str]

    @model_validator(mode="after")
    def _consistent(self) -> "BankStatementExtraction":
        if self.statement_start and self.statement_end and self.statement_end < self.statement_start:
            raise ValueError("statement_end is before statement_start")
        return self


EXTRACTION_SCHEMAS: dict[DocumentType, type[_Strict]] = {
    DocumentType.PAYSTUB: PaystubExtraction,
    DocumentType.BANK_STATEMENT: BankStatementExtraction,
}

# Numeric bounds are enforced by Pydantic after the call; they're stripped from the schema sent to
# providers because vendors' structured-output modes support different subsets of JSON Schema.
_PROVIDER_UNSUPPORTED = {"minimum", "maximum", "exclusiveMinimum", "exclusiveMaximum"}


def provider_schema(model: type[BaseModel]) -> dict[str, Any]:
    schema = copy.deepcopy(model.model_json_schema())

    def strip(node: Any) -> None:
        if isinstance(node, dict):
            for key in _PROVIDER_UNSUPPORTED & node.keys():
                del node[key]
            node.pop("title", None)
            for value in node.values():
                strip(value)
        elif isinstance(node, list):
            for value in node:
                strip(value)

    strip(schema)
    return schema
