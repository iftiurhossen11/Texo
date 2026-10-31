"""Texo RAG microservice entrypoint."""

from contextlib import asynccontextmanager
from typing import AsyncIterator

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from routers import documents, health, ingest, retrieve
from services import embedder


@asynccontextmanager
async def lifespan(_: FastAPI) -> AsyncIterator[None]:
    embedder.get_model()
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
