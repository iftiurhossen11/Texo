"""Multilingual embedding wrapper using sentence-transformers.

Default model: intfloat/multilingual-e5-small (lighter CPU-friendly choice).

Benchmark note (Phase 2):
- intfloat/multilingual-e5-large (~1.2GB) was the spec primary but can add
  1-2+ seconds per query on CPU-only booth hardware.
- e5-small trades a small retrieval-quality cost for much lower latency.
- Run `python -m services.embedder` on the demo laptop to measure
  single-query latency before locking the model.
- Query/passage prefixes are required for E5 models.
"""

from __future__ import annotations

import logging
import time
from typing import TYPE_CHECKING

from config import settings

if TYPE_CHECKING:
    from sentence_transformers import SentenceTransformer

logger = logging.getLogger(__name__)

_model: SentenceTransformer | None = None


def get_model() -> SentenceTransformer:
    global _model
    if _model is None:
        from sentence_transformers import SentenceTransformer

        logger.info("Loading embedding model: %s", settings.embedding_model)
        _model = SentenceTransformer(settings.embedding_model)
        logger.info("Embedding model loaded.")
    return _model


def is_model_loaded() -> bool:
    return _model is not None


def embed_passages(texts: list[str]) -> list[list[float]]:
    if not texts:
        return []
    prefixed = [f"passage: {text}" for text in texts]
    model = get_model()
    vectors = model.encode(prefixed, normalize_embeddings=True, show_progress_bar=False)
    return vectors.tolist()


def embed_query(query: str) -> list[float]:
    model = get_model()
    vector = model.encode(
        f"query: {query}",
        normalize_embeddings=True,
        show_progress_bar=False,
    )
    return vector.tolist()


def benchmark_query_latency(query: str = "How should a broken stitch be handled?") -> float:
    """Warm-load model and return single-query embedding latency in seconds."""
    start = time.perf_counter()
    get_model()
    embed_query(query)
    return time.perf_counter() - start


if __name__ == "__main__":
    logging.basicConfig(level=logging.INFO)
    latency = benchmark_query_latency()
    logging.info("Model: %s", settings.embedding_model)
    logging.info("Single query embedding latency: %.3fs", latency)
