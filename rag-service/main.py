"""Texo RAG microservice entrypoint."""

from contextlib import asynccontextmanager
from typing import AsyncIterator

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from config import settings
from routers import documents, health, ingest, retrieve
from services import embedder


@asynccontextmanager
async def lifespan(_: FastAPI) -> AsyncIterator[None]:
    if settings.embedding_backend == "sentence_transformers":
        embedder.get_model()
    elif settings.embedding_backend == "cloudflare":
        if not settings.cloudflare_account_id or not settings.cloudflare_api_token:
            raise RuntimeError("Cloudflare embedding credentials are required.")
    else:
        raise RuntimeError(f"Unsupported embedding backend: {settings.embedding_backend}")

    if settings.vector_store_backend == "postgres":
        from services.pg_vector_store import ensure_schema

        ensure_schema()
    elif settings.vector_store_backend != "chroma":
        raise RuntimeError(f"Unsupported vector store backend: {settings.vector_store_backend}")

    yield


app = FastAPI(
    title="Texo RAG Service",
    description="Document ingestion and retrieval microservice",
    version="0.1.0",
    lifespan=lifespan,
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

app.include_router(health.router)
app.include_router(ingest.router)
app.include_router(retrieve.router)
app.include_router(documents.router)


@app.get("/")
async def root() -> dict[str, str]:
    return {"service": "texo-rag", "status": "ok", "message": "Texo RAG service running"}
