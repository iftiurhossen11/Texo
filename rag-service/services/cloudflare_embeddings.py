"""Cloudflare Workers AI embeddings for low-memory deployments."""

from __future__ import annotations

import logging

import requests

from config import settings

logger = logging.getLogger(__name__)
_BATCH_SIZE = 32


def _embed_batch(texts: list[str]) -> list[list[float]]:
    account_id = settings.cloudflare_account_id
    api_token = settings.cloudflare_api_token
    if not account_id or not api_token:
        raise RuntimeError("Cloudflare embedding credentials are not configured.")

    url = (
        f"https://api.cloudflare.com/client/v4/accounts/{account_id}"
        f"/ai/run/{settings.cloudflare_embedding_model}"
    )
    try:
        response = requests.post(
            url,
            headers={"Authorization": f"Bearer {api_token}"},
            json={"text": texts},
            timeout=60,
        )
        response.raise_for_status()
    except requests.RequestException as exc:
        logger.error("Cloudflare embedding request failed: %s", exc)
        raise RuntimeError("Cloudflare embedding request failed.") from exc

    payload = response.json()
    vectors = payload.get("result", {}).get("data")
    if not isinstance(vectors, list) or len(vectors) != len(texts):
        raise RuntimeError("Cloudflare returned an invalid embedding response.")

    dimensions = settings.cloudflare_embedding_dimensions
    if any(not isinstance(vector, list) or len(vector) != dimensions for vector in vectors):
        raise RuntimeError("Cloudflare returned embeddings with unexpected dimensions.")

    return vectors


def embed_texts(texts: list[str]) -> list[list[float]]:
    vectors: list[list[float]] = []
    for start in range(0, len(texts), _BATCH_SIZE):
        vectors.extend(_embed_batch(texts[start : start + _BATCH_SIZE]))
    return vectors


def embed_query(text: str) -> list[float]:
    vectors = _embed_batch([text])
    return vectors[0]
