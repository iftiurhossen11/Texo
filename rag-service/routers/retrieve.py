"""POST /retrieve — embed query and return top-k similar chunks."""

from __future__ import annotations

import logging

from fastapi import APIRouter, Depends

from config import settings
from models.schemas import RetrieveRequest, RetrieveResponse, RetrieveResult
from security import require_rag_api_token
from services import embedder, vector_store

logger = logging.getLogger(__name__)
router = APIRouter(tags=["retrieve"], dependencies=[Depends(require_rag_api_token)])


@router.post("/retrieve", response_model=RetrieveResponse)
async def retrieve(body: RetrieveRequest) -> RetrieveResponse:
    logger.info("Retrieve start query=%r top_k=%s", body.query[:80], body.top_k)
    query_embedding = embedder.embed_query(body.query.strip())
    raw_results = vector_store.query_similar(
        query_embedding=query_embedding,
        top_k=body.top_k,
        doc_type_filter=body.doc_type_filter,
        document_id_filter=body.document_id_filter,
        allowed_departments=body.allowed_departments,
    )

    threshold = settings.similarity_threshold
    filtered = [
        RetrieveResult(
            document_id=item["document_id"],
            chunk_index=item["chunk_index"],
            section_heading=item.get("section_heading"),
            page_number=item.get("page_number"),
            content=item["content"],
            similarity_score=round(item["similarity_score"], 4),
            vector_id=item["vector_id"],
            doc_type=item.get("doc_type"),
        )
        for item in raw_results
        if item["similarity_score"] >= threshold
    ]

    logger.debug(
        "Retrieve query=%r top_k=%s raw=%s above_threshold=%s",
        body.query[:80],
        body.top_k,
        len(raw_results),
        len(filtered),
    )

    logger.info(
        "Retrieve complete query=%r raw=%s above_threshold=%s",
        body.query[:80],
        len(raw_results),
        len(filtered),
    )

    return RetrieveResponse(
        query=body.query,
        results=filtered,
        total_results=len(filtered),
    )
