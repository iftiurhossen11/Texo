"""Authentication for RAG endpoints used by the Laravel backend."""

from __future__ import annotations

import hmac

from fastapi import Header, HTTPException

from config import settings


def require_rag_api_token(x_rag_api_token: str | None = Header(default=None)) -> None:
    expected_token = settings.rag_api_token
    if expected_token is None:
        if settings.app_env.lower() in {"production", "prod"}:
            raise HTTPException(status_code=503, detail="RAG API authentication is not configured.")
        return

    if x_rag_api_token is None or not hmac.compare_digest(x_rag_api_token, expected_token):
        raise HTTPException(status_code=401, detail="Invalid or missing RAG API token.")
