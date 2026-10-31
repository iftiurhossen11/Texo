"""Environment-based settings for the RAG microservice."""

from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    host: str = "127.0.0.1"
    port: int = 8001
    chroma_persist_dir: str = "./chroma_data"
    embedding_model: str = "intfloat/multilingual-e5-small"
    app_env: str = "local"
    rag_api_token: str | None = None
    similarity_threshold: float = 0.85
    chunk_size: int = 400
    chunk_overlap: int = 60

    class Config:
        env_file = ".env"
        extra = "ignore"


settings = Settings()
