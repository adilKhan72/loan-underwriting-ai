# ADR-0001: Rules decide, the LLM only extracts and explains

**Status:** Accepted

## Context
Lending decisions must be explainable to regulators and applicants, reproducible on replay, and free of
unexplainable variation. LLM outputs are non-deterministic and hard to audit. But LLMs are far better than
templates or regexes at reading inconsistent paystubs and bank statements, and at writing clear explanations.

## Decision
- The LLM is used for **two bounded jobs only**: turning documents into structured fields, and turning a
  finished decision into prose.
- The approve / refer / decline decision is made by a **versioned, deterministic rules engine** in the API.
- Extraction output is schema-validated and carries a confidence score. Low confidence routes to human review,
  never to an automatic decline.

## Consequences
- Decisions are replayable from stored inputs plus the policy version.
- An LLM regression can at worst produce a bad extraction (caught by validation/confidence) or a poorly worded
  explanation. It can never flip a decision directly.
- Policy changes are code changes, so they get reviewed and versioned.
