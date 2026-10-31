"""Section-aware document chunking with recursive fallback."""

from __future__ import annotations

import re
from dataclasses import dataclass

import tiktoken

from config import settings

HEADING_PATTERNS = [
    re.compile(r"^#{1,6}\s+.+$", re.MULTILINE),
    # Section titles like "1. Purpose" — exclude numbered action steps ("1. **Stop...")
    re.compile(r"^\d+\.\s+(?!\*\*)(?![\d]+\.)[A-Za-z0-9].+$", re.MULTILINE),
    re.compile(r"^\d+\.\d+(?:\.\d+)*\s+.+$", re.MULTILINE),
]


@dataclass
class TextChunk:
    content: str
    chunk_index: int
    section_heading: str | None
    page_number: int | None
    token_count: int


def _get_tokenizer():
    try:
        return tiktoken.get_encoding("cl100k_base")
    except Exception:
        return None


def count_tokens(text: str) -> int:
    tokenizer = _get_tokenizer()
    if tokenizer is not None:
        return len(tokenizer.encode(text))
    # Rough fallback: ~4 chars per token for mixed Bangla/English
    return max(1, len(text) // 4)


def _split_by_headings(text: str) -> list[tuple[str | None, str, int | None]]:
    """Return (heading, body, page_number) sections."""
    lines = text.splitlines()
    sections: list[tuple[str | None, list[str], int | None]] = []
    current_heading: str | None = None
    current_lines: list[str] = []
    current_page: int | None = None

    def flush() -> None:
        nonlocal current_heading, current_lines, current_page
        body = "\n".join(current_lines).strip()
        if body:
            sections.append((current_heading, body, current_page))
        current_lines = []

    for line in lines:
        stripped = line.strip()
        is_heading = any(pattern.match(stripped) for pattern in HEADING_PATTERNS)
        if is_heading and (current_lines or current_heading is not None):
            flush()
            current_heading = stripped.lstrip("#").strip()
            current_page = None
            continue
        if stripped:
            current_lines.append(line)

    flush()

    if len(sections) <= 1:
        return []

    return [(heading, body, page) for heading, body, page in sections]


def _recursive_split(text: str, chunk_size: int, overlap: int) -> list[str]:
    if count_tokens(text) <= chunk_size:
        return [text.strip()] if text.strip() else []

    separators = ["\n\n", "\n", ". ", " "]
    chunks: list[str] = []
    start = 0
    text_len = len(text)

    while start < text_len:
        end = min(text_len, start + chunk_size * 4)
        if end < text_len:
            window = text[start:end]
            split_at = -1
            for sep in separators:
                idx = window.rfind(sep)
                if idx > len(window) // 3:
                    split_at = idx + len(sep)
                    break
            if split_at > 0:
                end = start + split_at

        piece = text[start:end].strip()
        if piece:
            while count_tokens(piece) > chunk_size and len(piece) > 100:
                piece = piece[: int(len(piece) * 0.85)].rsplit(" ", 1)[0]
            if piece:
                chunks.append(piece)

        if end >= text_len:
            break
        start = max(end - overlap * 4, start + 1)

    return chunks


def _chunk_section(
    body: str,
    heading: str | None,
    page_number: int | None,
    chunk_size: int,
    overlap: int,
    start_index: int,
) -> tuple[list[TextChunk], int]:
    chunks: list[TextChunk] = []
    pieces = _recursive_split(body, chunk_size, overlap)
    index = start_index
    for piece in pieces:
        prefix = f"{heading}\n\n" if heading and not piece.startswith(heading or "") else ""
        content = f"{prefix}{piece}".strip()
        chunks.append(
            TextChunk(
                content=content,
                chunk_index=index,
                section_heading=heading,
                page_number=page_number,
                token_count=count_tokens(content),
            )
        )
        index += 1
    return chunks, index


def chunk_document(
    pages: list,
    chunk_size: int | None = None,
    chunk_overlap: int | None = None,
) -> list[TextChunk]:
    """Chunk extracted pages using section-aware splitting with fallback."""
    size = chunk_size or settings.chunk_size
    overlap = chunk_overlap or settings.chunk_overlap

    full_text = "\n\n".join(page.text for page in pages if page.text.strip())
    if not full_text.strip():
        return []

    page_map: dict[int, int | None] = {}
    offset = 0
    for page in pages:
        page_map[offset] = page.page_number
        offset += len(page.text) + 2

    sections = _split_by_headings(full_text)
    all_chunks: list[TextChunk] = []
    next_index = 0

    if sections:
        for heading, body, page_number in sections:
            section_chunks, next_index = _chunk_section(
                body, heading, page_number, size, overlap, next_index
            )
            all_chunks.extend(section_chunks)
    else:
        default_page = pages[0].page_number if pages else None
        pieces = _recursive_split(full_text, size, overlap)
        for piece in pieces:
            all_chunks.append(
                TextChunk(
                    content=piece,
                    chunk_index=next_index,
                    section_heading=None,
                    page_number=default_page,
                    token_count=count_tokens(piece),
                )
            )
            next_index += 1

    return all_chunks
