"""DELETE /documents/{document_id} — remove all vectors for a document."""

from __future__ import annotations

import logging

from fastapi import APIRouter, Depends

from security import require_rag_api_token
from services import vector_store

logger = logging.getLogger(__name__)
router = APIRouter(tags=["documents"], dependencies=[Depends(require_rag_api_token)])


@router.delete("/documents/{document_id}")
async def delete_document_vectors(document_id: int) -> dict[str, int | str]:
    deleted = vector_store.delete_by_document_id(document_id)
    logger.info("Deleted %d vectors for document_id=%s", deleted, document_id)

    return {
        "status": "ok",
        "document_id": document_id,
        "deleted_vectors": deleted,
    }
