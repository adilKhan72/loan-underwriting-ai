from typing import Literal

from pydantic import BaseModel, ConfigDict, Field, ValidationError, model_validator

from app.llm.base import LLMProvider, ProviderRefusal
from app.schemas import provider_schema

Outcome = Literal["approve", "refer", "decline"]


class RuleResult(BaseModel):
    code: str = Field(max_length=60)
    outcome: Literal["pass", "refer", "fail", "skip"]
    message: str = Field(max_length=500)


class ExplainRequest(BaseModel):
    """Decision facts only. No applicant identity or document text ever reaches this endpoint (ADR-0004)."""

    outcome: Outcome
    policy_version: str = Field(max_length=20)
    rule_results: list[RuleResult] = Field(min_length=1, max_length=50)


class Explanation(BaseModel):
    model_config = ConfigDict(extra="forbid")

    underwriter_summary: str = Field(min_length=20, max_length=1500)
    applicant_message: str = Field(min_length=20, max_length=1200)
    principal_reasons: list[str] = Field(max_length=4)

    @model_validator(mode="after")
    def _no_internal_codes_for_applicants(self) -> "Explanation":
        # Rule codes are SCREAMING_SNAKE_CASE internals; applicants get plain language only.
        for word in self.applicant_message.split():
            token = word.strip(".,;:()\"'")
            if len(token) > 3 and "_" in token and token.upper() == token:
                raise ValueError(f"applicant_message contains internal code {token!r}")
        return self


SYSTEM_PROMPT = """You write explanations of consumer-loan underwriting decisions that have ALREADY been made
by a deterministic rules engine. You never make, change or second-guess the decision.

Write two things from the rule results you are given:
- underwriter_summary: concise and factual, for a credit analyst. Reference rule codes and say exactly what to
  check or request next for every refer/fail result.
- applicant_message: plain, respectful language for the applicant. State the decision as given. Never mention
  internal rule codes, thresholds, scores or policy versions. For refer, say the application needs a manual
  review and what document or information may help. For decline, give the specific principal reasons in plain
  words.
- principal_reasons: for decline only, up to 4 specific plain-language reasons taken from the failing rules,
  most important first; otherwise an empty list.

Only use facts present in the rule results. Do not invent amounts, dates or reasons."""

MAX_ATTEMPTS = 2


class ExplanationFailed(Exception):
    pass


def _user_message(request: ExplainRequest) -> str:
    lines = [f"Decision: {request.outcome} (policy {request.policy_version})", "Rule results:"]
    lines += [f"- [{r.outcome}] {r.code}: {r.message}" for r in request.rule_results]
    return "\n".join(lines)


def explain(request: ExplainRequest, provider: LLMProvider) -> Explanation:
    """Same validate-then-retry-once pattern as extraction. On repeated failure the caller keeps
    the decision and shows the rule results without prose; an explanation is never required."""
    schema = provider_schema(Explanation)
    user = _user_message(request)
    errors: list[str] = []

    for _ in range(MAX_ATTEMPTS):
        try:
            raw = provider.complete_json(SYSTEM_PROMPT, user, schema)
        except ProviderRefusal as e:
            raise ExplanationFailed(str(e)) from e

        try:
            explanation = Explanation.model_validate(raw)
        except ValidationError as e:
            errors = [f"{'.'.join(str(p) for p in err['loc']) or '(root)'}: {err['msg']}" for err in e.errors()]
            user = _user_message(request) + "\n\nYour previous answer failed validation:\n- " + "\n- ".join(errors)
            continue

        if request.outcome != "decline" and explanation.principal_reasons:
            explanation.principal_reasons = []
        return explanation

    raise ExplanationFailed("; ".join(errors))
