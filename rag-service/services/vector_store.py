"""Chroma persistent vector store with cosine similarity."""

from __future__ import annotations

import logging
from typing import TYPE_CHECKING, Any

from config import settings

if TYPE_CHECKING:
    import chromadb
    from chromadb.api.models.Collection import Collection

logger = logging.getLogger(__name__)

COLLECTION_NAME = "texo_documents"
_client: Any = None
_collection: Any = None


def get_client() -> chromadb.PersistentClient:
    global _client
    if _client is None:
        import chromadb

        _client = chromadb.PersistentClient(path=settings.chroma_persist_dir)
    return _client


def get_collection() -> Collection:
    global _collection
    if _collection is None:
        client = get_client()
        _collection = client.get_or_create_collection(
            name=COLLECTION_NAME,
            metadata={"hnsw:space": "cosine"},
        )
        logger.info(
            "Chroma collection '%s' ready (space=cosine, path=%s)",
            COLLECTION_NAME,
            settings.chroma_persist_dir,
        )
    return _collection


def delete_by_document_id(document_id: int) -> int:
    if settings.vector_store_backend == "postgres":
        from services.pg_vector_store import delete_by_document_id as delete_from_postgres

        return delete_from_postgres(document_id)
    if settings.vector_store_backend != "chroma":
        raise RuntimeError(f"Unsupported vector store backend: {settings.vector_store_backend}")

    collection = get_collection()
    existing = collection.get(where={"document_id": document_id}, include=[])
    ids = existing.get("ids") or []
    if ids:
        collection.delete(ids=ids)
    return len(ids)


def upsert_chunks(
    document_id: int,
    doc_type: str,
    department: str,
    chunks: list[dict[str, Any]],
    embeddings: list[list[float]],
) -> list[str]:
    if settings.vector_store_backend == "postgres":
        from services.pg_vector_store import upsert_chunks as upsert_to_postgres

        return upsert_to_postgres(document_id, doc_type, department, chunks, embeddings)
    if settings.vector_store_backend != "chroma":
        raise RuntimeError(f"Unsupported vector store backend: {settings.vector_store_backend}")

    collection = get_collection()
    ids: list[str] = []
    documents: list[str] = []
    metadatas: list[dict[str, Any]] = []

    for chunk, embedding in zip(chunks, embeddings):
        vector_id = f"doc_{document_id}_chunk_{chunk['chunk_index']}"
        ids.append(vector_id)
        documents.append(chunk["content"])
        metadatas.append(
            {
                "document_id": document_id,
                "chunk_index": chunk["chunk_index"],
                "section_heading": chunk.get("section_heading") or "",
                "page_number": chunk.get("page_number") if chunk.get("page_number") is not None else -1,
                "doc_type": doc_type,
                "department": department,
            }
        )

    collection.upsert(
        ids=ids,
        embeddings=embeddings,
        documents=documents,
        metadatas=metadatas,
    )
    return ids


def query_similar(
    query_embedding: list[float],
    top_k: int,
    doc_type_filter: str | None = None,
    document_id_filter: int | None = None,
    allowed_departments: list[str] | None = None,
) -> list[dict[str, Any]]:
    if settings.vector_store_backend == "postgres":
        from services.pg_vector_store import query_similar as query_postgres

        return query_postgres(
            query_embedding,
            top_k,
            doc_type_filter,
            document_id_filter,
            allowed_departments,
        )
    if settings.vector_store_backend != "chroma":
        raise RuntimeError(f"Unsupported vector store backend: {settings.vector_store_backend}")

    collection = get_collection()
    where: dict[str, Any] | None = None

    filters: list[dict[str, Any]] = []
    if doc_type_filter:
        filters.append({"doc_type": doc_type_filter})
    if document_id_filter is not None:
        filters.append({"document_id": document_id_filter})
    if allowed_departments:
        filters.append({"department": {"$in": allowed_departments}})

    if len(filters) == 1:
        where = filters[0]
    elif len(filters) > 1:
        where = {"$and": filters}

    results = collection.query(
        query_embeddings=[query_embedding],
        n_results=top_k,
        where=where,
        include=["documents", "metadatas", "distances"],
    )

    parsed: list[dict[str, Any]] = []
    if not results["ids"] or not results["ids"][0]:
        return parsed

    for idx, vector_id in enumerate(results["ids"][0]):
        distance = results["distances"][0][idx] if results.get("distances") else 1.0
        # Chroma cosine distance: similarity = 1 - distance (normalized vectors)
        similarity = max(0.0, 1.0 - float(distance))
        metadata = results["metadatas"][0][idx] if results.get("metadatas") else {}
        page_number = metadata.get("page_number")
        parsed.append(
            {
                "vector_id": vector_id,
                "content": results["documents"][0][idx] if results.get("documents") else "",
                "document_id": int(metadata.get("document_id", 0)),
                "chunk_index": int(metadata.get("chunk_index", 0)),
                "section_heading": metadata.get("section_heading") or None,
                "page_number": None if page_number == -1 else page_number,
                "doc_type": metadata.get("doc_type"),
                "similarity_score": similarity,
            }
        )

    return parsed


def count_vectors_for_document(document_id: int) -> int:
    if settings.vector_store_backend == "postgres":
        from services.pg_vector_store import count_vectors_for_document as count_postgres

        return count_postgres(document_id)
    if settings.vector_store_backend != "chroma":
        raise RuntimeError(f"Unsupported vector store backend: {settings.vector_store_backend}")

    collection = get_collection()
    existing = collection.get(where={"document_id": document_id}, include=[])
    return len(existing.get("ids") or [])
