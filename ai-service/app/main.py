from fastapi import FastAPI

from app.config import settings
from app.routers import explain, extract

app = FastAPI(
    title="Underwriting AI service",
    version="0.1.0",
    description="Document extraction and decision explanations for loan-underwriting-ai. "
    "Never makes decisions; see docs/adr/0001-rules-decide-llm-explains.md.",
)

app.include_router(extract.router)
app.include_router(explain.router)


@app.get("/health")
def health() -> dict:
    return {"status": "ok", "provider": settings.llm_provider}
