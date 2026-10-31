"""Persistent pgvector store for hosted deployments."""

from __future__ import annotations

from typing import Any

from config import settings

TABLE_NAME = "texo_rag_chunks"
VECTOR_DIMENSIONS = 768


def _connect():
    if not settings.database_url:
        raise RuntimeError("DATABASE_URL is required for the PostgreSQL vector store.")

    import psycopg

    return psycopg.connect(settings.database_url, connect_timeout=20)


def ensure_schema() -> None:
    if settings.cloudflare_embedding_dimensions != VECTOR_DIMENSIONS:
        raise RuntimeError(
            f"The PostgreSQL vector store requires {VECTOR_DIMENSIONS}-dimension embeddings."
        )

    with _connect() as connection, connection.cursor() as cursor:
        cursor.execute("CREATE EXTENSION IF NOT EXISTS vector")
        cursor.execute(
            f"""
            CREATE TABLE IF NOT EXISTS {TABLE_NAME} (
                vector_id TEXT PRIMARY KEY,
                document_id BIGINT NOT NULL,
                chunk_index INTEGER NOT NULL,
                section_heading TEXT,
                page_number INTEGER,
                content TEXT NOT NULL,
                doc_type TEXT,
                department TEXT NOT NULL,
                embedding vector({VECTOR_DIMENSIONS}) NOT NULL
            )
            """
        )
        cursor.execute(
            f"""
            CREATE INDEX IF NOT EXISTS texo_rag_chunks_embedding_idx
            ON {TABLE_NAME} USING hnsw (embedding vector_cosine_ops)
            """
        )
        cursor.execute(
            f"CREATE INDEX IF NOT EXISTS texo_rag_chunks_document_idx ON {TABLE_NAME} (document_id)"
        )


def _as_vector(values: list[float]) -> str:
    if len(values) != VECTOR_DIMENSIONS:
        raise ValueError(f"Expected {VECTOR_DIMENSIONS}-dimension embedding.")
    return "[" + ",".join(str(float(value)) for value in values) + "]"


def delete_by_document_id(document_id: int) -> int:
    with _connect() as connection, connection.cursor() as cursor:
        cursor.execute(
            f"DELETE FROM {TABLE_NAME} WHERE document_id = %s",
            (document_id,),
        )
        return cursor.rowcount


def upsert_chunks(
    document_id: int,
    doc_type: str,
    department: str,
    chunks: list[dict[str, Any]],
    embeddings: list[list[float]],
) -> list[str]:
    if len(chunks) != len(embeddings):
        raise ValueError("Chunk and embedding counts do not match.")

    vector_ids: list[str] = []
    with _connect() as connection, connection.cursor() as cursor:
        for chunk, embedding in zip(chunks, embeddings):
            vector_id = f"doc_{document_id}_chunk_{chunk['chunk_index']}"
            vector_ids.append(vector_id)
            cursor.execute(
                f"""
                INSERT INTO {TABLE_NAME} (
                    vector_id, document_id, chunk_index, section_heading,
                    page_number, content, doc_type, department, embedding
                )
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s::vector)
                ON CONFLICT (vector_id) DO UPDATE SET
                    document_id = EXCLUDED.document_id,
                    chunk_index = EXCLUDED.chunk_index,
                    section_heading = EXCLUDED.section_heading,
                    page_number = EXCLUDED.page_number,
                    content = EXCLUDED.content,
                    doc_type = EXCLUDED.doc_type,
                    department = EXCLUDED.department,
                    embedding = EXCLUDED.embedding
                """,
                (
                    vector_id,
                    document_id,
                    chunk["chunk_index"],
                    chunk.get("section_heading") or None,
                    chunk.get("page_number"),
                    chunk["content"],
                    doc_type,
                    department,
                    _as_vector(embedding),
                ),
            )

    return vector_ids


def query_similar(
    query_embedding: list[float],
    top_k: int,
    doc_type_filter: str | None = None,
    document_id_filter: int | None = None,
    allowed_departments: list[str] | None = None,
) -> list[dict[str, Any]]:
    if allowed_departments is not None and not allowed_departments:
        return []

    conditions = ["embedding IS NOT NULL"]
    vector = _as_vector(query_embedding)
    parameters: list[Any] = [vector]
    if doc_type_filter:
        conditions.append("doc_type = %s")
        parameters.append(doc_type_filter)
    if document_id_filter is not None:
        conditions.append("document_id = %s")
        parameters.append(document_id_filter)
    if allowed_departments is not None:
        conditions.append("department = ANY(%s)")
        parameters.append(allowed_departments)

    parameters.extend([vector, top_k])
    query = f"""
        SELECT vector_id, content, document_id, chunk_index, section_heading,
               page_number, doc_type, 1 - (embedding <=> %s::vector) AS similarity_score
        FROM {TABLE_NAME}
        WHERE {' AND '.join(conditions)}
        ORDER BY embedding <=> %s::vector
        LIMIT %s
    """

    with _connect() as connection, connection.cursor() as cursor:
        cursor.execute(query, parameters)
        rows = cursor.fetchall()

    return [
        {
            "vector_id": row[0],
            "content": row[1],
            "document_id": int(row[2]),
            "chunk_index": int(row[3]),
            "section_heading": row[4],
            "page_number": row[5],
            "doc_type": row[6],
            "similarity_score": max(0.0, float(row[7])),
        }
        for row in rows
    ]


def count_vectors_for_document(document_id: int) -> int:
    with _connect() as connection, connection.cursor() as cursor:
        cursor.execute(
            f"SELECT COUNT(*) FROM {TABLE_NAME} WHERE document_id = %s",
            (document_id,),
        )
        result = cursor.fetchone()
        return int(result[0]) if result else 0
