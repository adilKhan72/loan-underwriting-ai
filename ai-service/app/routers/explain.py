from fastapi import APIRouter, Depends, HTTPException

from app.explanation import Explanation, ExplanationFailed, ExplainRequest, explain
from app.llm.base import LLMProvider, ProviderError
from app.llm.factory import get_provider

router = APIRouter(prefix="/v1", tags=["explanation"])


class ExplainResponse(Explanation):
    provider: str


@router.post("/explain", response_model=ExplainResponse)
def explain_decision(request: ExplainRequest, provider: LLMProvider = Depends(get_provider)) -> ExplainResponse:
    try:
        explanation = explain(request, provider)
    except ExplanationFailed as e:
        # The input is fine; this model couldn't produce valid prose. 422 → caller keeps the decision without prose.
        raise HTTPException(status_code=422, detail=f"explanation unavailable: {e}")
    except ProviderError as e:
        raise HTTPException(status_code=502, detail=str(e))

    return ExplainResponse(**explanation.model_dump(), provider=provider.name)
