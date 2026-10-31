"""Health check endpoint."""

from fastapi import APIRouter

from config import settings
from services import embedder

router = APIRouter(tags=["health"])


@router.get("/health")
async def health() -> dict[str, str | bool]:
    return {
        "status": "ok",
        "embedding_model": settings.embedding_model,
        "embedding_model_loaded": embedder.is_model_loaded(),
    }
