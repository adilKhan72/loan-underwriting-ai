from fastapi import FastAPI

from app.config import settings

app = FastAPI(
    title="Underwriting AI service",
    version="0.1.0",
    description="Document extraction and decision explanations for loan-underwriting-ai. "
    "Never makes decisions; see docs/adr/0001-rules-decide-llm-explains.md.",
)


@app.get("/health")
def health() -> dict:
    return {"status": "ok", "provider": settings.llm_provider}
