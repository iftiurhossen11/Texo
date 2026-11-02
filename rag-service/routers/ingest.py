"""POST /ingest — extract, chunk, embed, and store document vectors."""

from __future__ import annotations

import logging

from fastapi import APIRouter, Depends, File, Form, HTTPException, UploadFile

from models.schemas import IngestErrorResponse, IngestResponse
from security import require_rag_api_token
from services import chunker, embedder, extractor, vector_store

logger = logging.getLogger(__name__)
router = APIRouter(tags=["ingest"], dependencies=[Depends(require_rag_api_token)])


async def _process_ingest(
    document_id: int,
    workspace_id: int,
    doc_type: str,
    department: str,
    extracted,
) -> IngestResponse:
    text_chunks = chunker.chunk_document(extracted.pages)
    if not text_chunks:
        raise HTTPException(
            status_code=422,
            detail=IngestErrorResponse(
                error="empty_content",
                detail="No chunks could be created from the extracted text.",
            ).model_dump(),
        )

    chunk_payloads = [
        {
            "content": chunk.content,
            "chunk_index": chunk.chunk_index,
            "section_heading": chunk.section_heading,
            "page_number": chunk.page_number,
            "token_count": chunk.token_count,
        }
        for chunk in text_chunks
    ]

    deleted = vector_store.delete_by_document_id(document_id, workspace_id)
    if deleted:
        logger.info("Deleted %d existing vectors for document_id=%s", deleted, document_id)

    embeddings = embedder.embed_passages([c["content"] for c in chunk_payloads])
    vector_ids = vector_store.upsert_chunks(
        document_id,
        workspace_id,
        doc_type,
        department,
        chunk_payloads,
        embeddings,
    )

    results = [
        {
            "chunk_index": chunk.chunk_index,
            "section_heading": chunk.section_heading,
            "page_number": chunk.page_number,
            "content": chunk.content,
            "token_count": chunk.token_count,
            "vector_id": vector_ids[i],
        }
        for i, chunk in enumerate(text_chunks)
    ]

    return IngestResponse(document_id=document_id, chunks=results, total_chunks=len(results))


@router.post("/ingest", response_model=IngestResponse)
async def ingest(
    document_id: int = Form(...),
    workspace_id: int = Form(...),
    doc_type: str = Form(...),
    department: str = Form(default="general"),
    file: UploadFile | None = File(default=None),
    source_url: str | None = Form(default=None),
) -> IngestResponse:
    if file is None and not source_url:
        raise HTTPException(
            status_code=422,
            detail=IngestErrorResponse(
                error="missing_input",
                detail="Provide either a file upload or source_url.",
            ).model_dump(),
        )
    if file is not None and source_url:
        raise HTTPException(
            status_code=422,
            detail=IngestErrorResponse(
                error="ambiguous_input",
                detail="Provide either a file upload or source_url, not both.",
            ).model_dump(),
        )

    logger.info(
        "Ingest start document_id=%s doc_type=%s filename=%s",
        document_id,
        doc_type,
        file.filename if file else source_url,
    )

    try:
        if file is not None:
            data = await file.read()
            extracted = extractor.extract_from_bytes(data, file.filename or "upload.txt")
        else:
            extracted = extractor.extract_from_url(source_url or "")
    except extractor.ExtractionError as exc:
        raise HTTPException(
            status_code=422,
            detail=IngestErrorResponse(error=exc.message, detail=exc.detail).model_dump(),
        ) from exc
    except Exception as exc:
        logger.exception("Unexpected ingest failure for document_id=%s", document_id)
        raise HTTPException(
            status_code=500,
            detail=IngestErrorResponse(error="ingest_failed", detail=str(exc)).model_dump(),
        ) from exc

    response = await _process_ingest(document_id, workspace_id, doc_type, department, extracted)
    logger.info(
        "Ingest complete document_id=%s total_chunks=%s",
        document_id,
        response.total_chunks,
    )
    return response
