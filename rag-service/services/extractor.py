"""Text extraction from PDF, DOCX, CSV, TXT, and web URLs."""

from __future__ import annotations

import io
from email import policy
from email.parser import BytesParser
from dataclasses import dataclass
from pathlib import Path
from typing import BinaryIO
from urllib.parse import urlparse

import pandas as pd
import pdfplumber
import requests
from bs4 import BeautifulSoup
from docx import Document

SUPPORTED_EXTENSIONS = {".pdf", ".docx", ".csv", ".txt", ".md", ".html", ".htm", ".mhtml"}
SUPPORTED_MIME_TYPES = {
    "application/pdf",
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    "text/csv",
    "text/plain",
    "text/markdown",
    "text/html",
}


class ExtractionError(Exception):
    """Raised when text extraction fails."""

    def __init__(self, message: str, detail: str = "") -> None:
        super().__init__(message)
        self.message = message
        self.detail = detail


@dataclass
class ExtractedPage:
    text: str
    page_number: int | None = None


@dataclass
class ExtractedDocument:
    pages: list[ExtractedPage]
    source_type: str

    @property
    def full_text(self) -> str:
        return "\n\n".join(page.text for page in self.pages if page.text.strip())


def _normalize_extension(filename: str | None) -> str:
    if not filename:
        return ""
    return Path(filename).suffix.lower()


def validate_file_extension(filename: str | None) -> str:
    ext = _normalize_extension(filename)
    if ext not in SUPPORTED_EXTENSIONS:
        supported = ", ".join(sorted(SUPPORTED_EXTENSIONS))
        raise ExtractionError(
            "unsupported_file_type",
            f"File type '{ext or 'unknown'}' is not supported. Supported: {supported}",
        )
    return ext


def extract_from_bytes(data: bytes, filename: str) -> ExtractedDocument:
    if not data or not data.strip():
        raise ExtractionError("empty_file", "The uploaded file is empty.")

    ext = validate_file_extension(filename)
    stream: BinaryIO = io.BytesIO(data)

    if ext == ".pdf":
        return _extract_pdf(stream)
    if ext == ".docx":
        return _extract_docx(stream)
    if ext == ".csv":
        return _extract_csv(stream)
    if ext in {".txt", ".md"}:
        return _extract_plain_text(data)
    if ext in {".html", ".htm"}:
        text = _extract_html_text(data.decode("utf-8", errors="replace"))
        if not text:
            raise ExtractionError("empty_file", "HTML contains no extractable text.")
        return ExtractedDocument(
            pages=[ExtractedPage(text=text, page_number=None)],
            source_type="html",
        )
    if ext == ".mhtml":
        text = _extract_mhtml_text(data)
        if not text:
            raise ExtractionError("empty_file", "MHTML contains no extractable HTML text.")
        return ExtractedDocument(
            pages=[ExtractedPage(text=text, page_number=None)],
            source_type="html",
        )

    raise ExtractionError("unsupported_file_type", f"Unsupported extension: {ext}")


def extract_from_url(url: str) -> ExtractedDocument:
    parsed = urlparse(url)
    if parsed.scheme not in {"http", "https"}:
        raise ExtractionError("invalid_url", "URL must use http or https scheme.")

    try:
        response = requests.get(url, timeout=30, headers={"User-Agent": "Texo-RAG/1.0"})
        response.raise_for_status()
    except requests.RequestException as exc:
        raise ExtractionError("url_fetch_failed", str(exc)) from exc

    if not response.content or not response.content.strip():
        raise ExtractionError("empty_file", "Fetched URL returned empty content.")

    content_type = response.headers.get("Content-Type", "").split(";")[0].strip().lower()
    ext = _normalize_extension(parsed.path)

    if ext in SUPPORTED_EXTENSIONS:
        return extract_from_bytes(response.content, parsed.path or f"file{ext}")

    if "html" in content_type or response.text.lstrip().startswith("<"):
        downloaded = _extract_html_text(response.text)
        if not downloaded or not downloaded.strip():
            raise ExtractionError("extraction_failed", "Could not extract text from URL.")
        return ExtractedDocument(
            pages=[ExtractedPage(text=downloaded.strip(), page_number=None)],
            source_type="url",
        )

    raise ExtractionError(
        "unsupported_file_type",
        f"URL content type '{content_type or ext or 'unknown'}' is not supported.",
    )


def _extract_html_text(html: str) -> str:
    """Extract readable text from HTML without lxml (Python 3.14+ friendly)."""
    soup = BeautifulSoup(html, "html.parser")
    for tag in soup(["script", "style", "nav", "footer", "header", "noscript"]):
        tag.decompose()
    lines = (line.strip() for line in soup.get_text("\n").splitlines())
    return "\n".join(line for line in lines if line)


def _extract_mhtml_text(data: bytes) -> str:
    """Decode saved HTML parts while ignoring embedded images and resources."""
    message = BytesParser(policy=policy.default).parsebytes(data)
    html_parts = [
        part.get_payload(decode=True) or b""
        for part in message.walk()
        if part.get_content_type() == "text/html"
    ]
    return "\n".join(
        text
        for text in (
            _extract_html_text(part.decode("utf-8", errors="replace"))
            for part in html_parts
        )
        if text
    )


def _extract_pdf(stream: BinaryIO) -> ExtractedDocument:
    pages: list[ExtractedPage] = []
    try:
        with pdfplumber.open(stream) as pdf:
            if not pdf.pages:
                raise ExtractionError("empty_file", "PDF contains no pages.")
            for index, page in enumerate(pdf.pages, start=1):
                text = page.extract_text() or ""
                if text.strip():
                    pages.append(ExtractedPage(text=text.strip(), page_number=index))
    except ExtractionError:
        raise
    except Exception as exc:
        raise ExtractionError("extraction_failed", f"PDF extraction failed: {exc}") from exc

    if not pages:
        return ExtractedDocument(
            pages=[
                ExtractedPage(
                    text="This PDF contains scanned or image content without machine-readable text.",
                    page_number=None,
                )
            ],
            source_type="pdf",
        )
    return ExtractedDocument(pages=pages, source_type="pdf")


def _extract_docx(stream: BinaryIO) -> ExtractedDocument:
    try:
        document = Document(stream)
        paragraphs = [p.text.strip() for p in document.paragraphs if p.text.strip()]
    except Exception as exc:
        raise ExtractionError("extraction_failed", f"DOCX extraction failed: {exc}") from exc

    if not paragraphs:
        raise ExtractionError("empty_file", "DOCX contains no extractable text.")
    return ExtractedDocument(
        pages=[ExtractedPage(text="\n".join(paragraphs), page_number=None)],
        source_type="docx",
    )


def _extract_csv(stream: BinaryIO) -> ExtractedDocument:
    try:
        df = pd.read_csv(stream)
    except Exception as exc:
        raise ExtractionError("extraction_failed", f"CSV parsing failed: {exc}") from exc

    if df.empty:
        raise ExtractionError("empty_file", "CSV contains no rows.")

    lines: list[str] = []
    lines.append(" | ".join(str(col) for col in df.columns))
    for _, row in df.iterrows():
        lines.append(" | ".join(str(value) for value in row.values))

    return ExtractedDocument(
        pages=[ExtractedPage(text="\n".join(lines), page_number=None)],
        source_type="csv",
    )


def _extract_plain_text(data: bytes) -> ExtractedDocument:
    for encoding in ("utf-8", "utf-8-sig", "latin-1"):
        try:
            text = data.decode(encoding).strip()
            break
        except UnicodeDecodeError:
            continue
    else:
        raise ExtractionError("extraction_failed", "Could not decode text file.")

    if not text:
        raise ExtractionError("empty_file", "Text file is empty.")
    return ExtractedDocument(
        pages=[ExtractedPage(text=text, page_number=None)],
        source_type="txt",
    )
