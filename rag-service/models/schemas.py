"""Pydantic request/response models."""

from __future__ import annotations

from pydantic import BaseModel, Field


class IngestErrorResponse(BaseModel):
    error: str
    detail: str = ""


class IngestResponse(BaseModel):
    document_id: int
    chunks: list[dict]
    total_chunks: int


class RetrieveRequest(BaseModel):
    query: str = Field(..., min_length=1)
    workspace_id: int = Field(..., ge=1)
    top_k: int = Field(default=3, ge=3, le=20)
    doc_type_filter: str | None = None
    document_id_filter: int | None = None
    user_role: str | None = None
    allowed_departments: list[str] | None = None


class RetrieveResult(BaseModel):
    document_id: int
    chunk_index: int
    section_heading: str | None = None
    page_number: int | None = None
    content: str
    similarity_score: float
    vector_id: str
    doc_type: str | None = None


class RetrieveResponse(BaseModel):
    query: str
    results: list[RetrieveResult]
    total_results: int
