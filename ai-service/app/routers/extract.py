from typing import Any, Literal

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel, Field

from app.extraction import extract
from app.llm.base import LLMProvider, ProviderError
from app.llm.factory import get_provider
from app.llm.fixture import FixtureMissingError
from app.schemas import DocumentType

router = APIRouter(prefix="/v1", tags=["extraction"])


class ExtractRequest(BaseModel):
    document_type: DocumentType
    # Text arrives already redacted by the API worker (ADR-0004).
    text: str = Field(min_length=1, max_length=100_000)


class ExtractResponse(BaseModel):
    status: Literal["ok", "needs_review"]
    document_type: DocumentType
    data: dict[str, Any] | None
    errors: list[str]
    attempts: int
    provider: str


@router.post("/extract", response_model=ExtractResponse)
def extract_document(request: ExtractRequest, provider: LLMProvider = Depends(get_provider)) -> ExtractResponse:
    """Sync def on purpose: provider calls block, so FastAPI runs this in its threadpool."""
    try:
        result = extract(request.document_type, request.text, provider)
    except FixtureMissingError as e:
        # Deterministic miss: retrying can never succeed, so it must not look like an outage.
        raise HTTPException(status_code=422, detail=str(e))
    except ProviderError as e:
        # Upstream failure, not a bad document: 502 tells the Laravel worker to retry later.
        raise HTTPException(status_code=502, detail=str(e))

    return ExtractResponse(
        status=result.status,
        document_type=result.document_type,
        data=result.data,
        errors=result.errors,
        attempts=result.attempts,
        provider=provider.name,
    )
