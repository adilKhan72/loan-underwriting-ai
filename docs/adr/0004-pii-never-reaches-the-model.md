# ADR-0004: PII is redacted before any text reaches a model

**Status:** Accepted

## Context
Paystubs and bank statements contain SSNs, account and routing numbers, addresses and dates of birth.
Third-party model providers are outside the platform's trust boundary.

## Decision
- Redaction runs in the API worker **before** text is sent to the AI service. Detected values are replaced with
  typed placeholders (`[SSN]`, `[ACCOUNT_NUMBER]`, …).
- Underwriting does not need those values from the model. Identity fields come from the application form, not from
  extraction.
- Applicant PII stored in the database is encrypted at rest (Laravel encrypted casts), and the audit log
  records that redaction happened, not the redacted values.

## Consequences
- Even the local Ollama path receives redacted text, so there's one code path and no "safe provider" exceptions.
- Redaction is pattern-based and unit-tested against known formats. Misses are a known risk, mitigated by the
  provider data-retention settings documented per adapter.
