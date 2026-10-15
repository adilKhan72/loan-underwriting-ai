# ADR-0002: A separate Python AI service, not LLM calls inside Laravel

**Status:** Accepted

## Context
The core product is a Laravel application. LLM tooling (SDKs, structured-output validation, evaluation, local
model runtimes) is most mature in Python.

## Decision
AI work lives in `ai-service/` (FastAPI), behind a small HTTP contract (`/v1/extract`, `/v1/explain`).
Laravel calls it from queue workers, never from a request cycle.

## Consequences
- Each side uses the best ecosystem for its job. The AI service can scale and deploy independently.
- One more service to run, which Docker Compose and CI absorb.
- The contract is the seam for testing: Laravel tests fake the HTTP client, and AI-service tests use the fixture provider.
