import { useEffect, useMemo, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { api } from '../api/client'
import type { DocumentRow } from '../hooks/useDocuments'
import { LoadingState } from './StateViews'
import { useAuth } from '../context/AuthContext'

const statusColor: Record<string, string> = {
  uploaded: 'bg-yellow-500/20 text-yellow-300',
  processing: 'bg-blue-500/20 text-blue-300',
  indexed: 'bg-emerald-500/20 text-emerald-300',
  failed: 'bg-red-500/20 text-red-300',
}

function decodeQuotedPrintable(content: string): string {
  const input = new TextEncoder().encode(content)
  const output = new Uint8Array(input.length)
  let outputLength = 0

  const hexValue = (byte: number): number => {
    if (byte >= 48 && byte <= 57) return byte - 48
    if (byte >= 65 && byte <= 70) return byte - 55
    if (byte >= 97 && byte <= 102) return byte - 87
    return -1
  }

  for (let index = 0; index < input.length;) {
    if (input[index] === 61 && input[index + 1] === 13 && input[index + 2] === 10) {
      index += 3
      continue
    }
    if (input[index] === 61 && input[index + 1] === 10) {
      index += 2
      continue
    }

    const high = input[index] === 61 ? hexValue(input[index + 1]) : -1
    const low = input[index] === 61 ? hexValue(input[index + 2]) : -1
    if (high >= 0 && low >= 0) {
      output[outputLength++] = high * 16 + low
      index += 3
      continue
    }

    output[outputLength++] = input[index++]
  }

  return new TextDecoder('utf-8').decode(output.subarray(0, outputLength))
}

function extractMhtmlHtmlPart(content: string): string {
  const boundary = content.match(/boundary="?([^";\r\n]+)"?/i)?.[1]
  const parts = boundary ? content.split(`--${boundary}`) : [content]
  const htmlPart = parts.find((part) => /^Content-Type:\s*text\/html\b/im.test(part))
  if (!htmlPart) return content

  const separator = htmlPart.search(/\r?\n\r?\n/)
  if (separator < 0) return htmlPart

  const headers = htmlPart.slice(0, separator)
  const body = htmlPart.slice(separator).replace(/^\r?\n\r?\n/, '')
  const transferEncoding = headers.match(/Content-Transfer-Encoding:\s*([^\r\n]+)/i)?.[1]
    ?.trim()
    .toLowerCase()

  if (transferEncoding === 'base64') {
    const bytes = Uint8Array.from(atob(body.replace(/\s+/g, '')), (character) => character.charCodeAt(0))
    return new TextDecoder('utf-8').decode(bytes)
  }
  if (transferEncoding === 'quoted-printable') {
    return decodeQuotedPrintable(body)
  }

  return body
}

function extractHtmlText(content: string): string {
  const isMhtml = /Content-Type:\s*text\/html\b/i.test(content)
  const html = isMhtml ? extractMhtmlHtmlPart(content) : content
  const decoded = !isMhtml && (html.includes('=3C') || html.includes('=3c') || html.includes('=20'))
    ? decodeQuotedPrintable(html)
    : html
  const parsed = new DOMParser().parseFromString(decoded, 'text/html')
  parsed.querySelectorAll('script, style, nav, header, footer, aside, noscript').forEach((element) => {
    element.remove()
  })

  const unwantedClass = /sidebar|menu|comment|cookie|related-posts|share|advertisement/i
  parsed.querySelectorAll('*').forEach((element) => {
    if (element === parsed.documentElement || element === parsed.head || element === parsed.body) return

    const classNames = typeof element.className === 'string'
      ? element.className
      : element.getAttribute('class') ?? ''
    const identifier = element.id ?? ''
    if (unwantedClass.test(classNames) || unwantedClass.test(identifier)) {
      element.remove()
    }
  })

  const articleContainer = [
    '.entry-content',
    '.post-content',
    '.article-content',
    'article',
    'main',
  ]
    .map((selector) => parsed.querySelector(selector))
    .find((element) => element !== null)
  const text = (articleContainer ?? parsed.body).textContent ?? ''

  return text
    .replace(/[\uFFFD\u0080-\u009F]/g, ' ')
    .replace(/â€[™œž]|â€“|â€”|Ã[\u0080-\u00FF]|Â[\u00A0-\u00FF]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
}

interface Props {
  documents: DocumentRow[]
  loading: boolean
  departmentFilter?: string
}

export function DocumentStatusList({ documents, loading, departmentFilter }: Props) {
  const { t } = useTranslation()
  const { user } = useAuth()
  const [search, setSearch] = useState('')
  const searchInputRef = useRef<HTMLInputElement>(null)
  const [selectedDocument, setSelectedDocument] = useState<DocumentRow | null>(null)
  const [documentUrl, setDocumentUrl] = useState<string | null>(null)
  const [textContent, setTextContent] = useState<string | null>(null)
  const [viewLoading, setViewLoading] = useState(false)
  const [viewError, setViewError] = useState<string | null>(null)
  const visibleDocuments = documents.filter((document) => {
    if (departmentFilter && departmentFilter !== 'all') {
      return (document.department ?? 'general') === departmentFilter
    }
    if (!user || user.role === 'gm' || user.role === 'admin') return true
    const allowed = user.role === 'qa'
      ? ['qa', 'general']
      : user.role === 'hr'
        ? ['hr_training', 'general']
        : user.role === 'compliance'
          ? ['compliance_sustainability', 'general']
          : ['general']
    return allowed.includes(document.department ?? 'general')
  })
  const filteredDocuments = useMemo(() => {
    const query = search.trim().toLowerCase()
    if (!query) return visibleDocuments
    return visibleDocuments.filter((document) =>
      `${document.title} ${document.original_filename} ${document.doc_type} ${document.department ?? ''}`
        .toLowerCase()
        .includes(query),
    )
  }, [search, visibleDocuments])

  useEffect(() => {
    const handleShortcut = (event: KeyboardEvent) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault()
        searchInputRef.current?.focus()
      }
    }
    window.addEventListener('keydown', handleShortcut)
    return () => window.removeEventListener('keydown', handleShortcut)
  }, [])

  const closeViewer = () => {
    if (documentUrl) URL.revokeObjectURL(documentUrl)
    setSelectedDocument(null)
    setDocumentUrl(null)
    setTextContent(null)
    setViewError(null)
  }

  const viewDocument = async (document: DocumentRow) => {
    if (documentUrl) URL.revokeObjectURL(documentUrl)
    setSelectedDocument(document)
    setDocumentUrl(null)
    setTextContent(null)
    setViewError(null)
    setViewLoading(true)

    try {
      if (document.source_type === 'url' && document.source_url) {
        window.open(document.source_url, '_blank', 'noopener,noreferrer')
        closeViewer()
        return
      }
      const response = await api.get<Blob>(`/documents/${document.id}/view`, {
        responseType: 'blob',
      })
      const blob = response.data
      const signature = new TextDecoder().decode(await blob.slice(0, 5).arrayBuffer())
      const isPdf = document.file_type.toLowerCase() === 'pdf'
        || document.original_filename.toLowerCase().endsWith('.pdf')
        || blob.type.toLowerCase() === 'application/pdf'
        || signature.startsWith('%PDF-')

      if (isPdf) {
        setDocumentUrl(URL.createObjectURL(blob))
      } else {
        const content = await blob.text()
        if (document.source_type === 'html'
          || /\.(?:html?|mhtml)$/i.test(document.original_filename)) {
          setTextContent(extractHtmlText(content))
        } else {
          setTextContent(content)
        }
      }
    } catch (error) {
      console.error('Unable to preview document', error)
      setViewError('Unable to load this document.')
    } finally {
      setViewLoading(false)
    }
  }

  return (
    <section className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl border border-slate-700 bg-slate-900/50 p-5">
      <h2 className="mb-4 text-lg font-semibold text-white">{t('documents.title')}</h2>
      <label className="mb-4 block">
        <span className="sr-only">Search Indexed Documents</span>
        <div className="relative">
          <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-500" aria-hidden="true">
            <svg viewBox="0 0 24 24" className="h-4 w-4 fill-none stroke-current stroke-2">
              <circle cx="11" cy="11" r="6" />
              <path d="m16 16 4 4" />
            </svg>
          </span>
          <input
            ref={searchInputRef}
            type="search"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Search documents, HTML, and web sources (Ctrl + K)"
            className="w-full rounded-xl border border-slate-700 bg-slate-950 py-3 pl-11 pr-4 text-sm text-white outline-none placeholder:text-slate-500 focus:border-emerald-400"
          />
        </div>
      </label>
      {loading && visibleDocuments.length === 0 ? (
        <div className="flex flex-1 items-center justify-center">
          <LoadingState label={t('documents.title')} />
        </div>
      ) : filteredDocuments.length === 0 ? (
        <div className="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed border-slate-700 bg-slate-950/60 px-6 py-10 text-center">
          <div className="mb-4 rounded-2xl border border-slate-700 bg-slate-900 p-4 text-slate-500">
            <svg viewBox="0 0 48 48" className="h-12 w-12" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
              <path d="M7 14a3 3 0 0 1 3-3h10l4 4h14a3 3 0 0 1 3 3v17a3 3 0 0 1-3 3H10a3 3 0 0 1-3-3V14Z" />
              <rect x="18" y="23" width="12" height="10" rx="2" />
              <path d="M21 23v-2a3 3 0 0 1 6 0v2" />
            </svg>
          </div>
          <h3 className="text-base font-semibold text-white">No Documents Found</h3>
          <p className="mt-2 max-w-sm text-sm text-slate-400">
            {search.trim()
              ? 'No documents match your search.'
              : 'Your role does not have access to any documents yet.'}
          </p>
        </div>
      ) : (
      <ul className="min-h-0 flex-1 space-y-2 overflow-y-auto pr-1">
        {filteredDocuments.map((doc) => (
          <li
            key={doc.id}
            className="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2.5 shadow-sm transition hover:border-emerald-500/60"
            onClick={() => void viewDocument(doc)}
            onKeyDown={(event) => {
              if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault()
                void viewDocument(doc)
              }
            }}
            role="button"
            tabIndex={0}
          >
            <div className="min-w-0">
              <p className="flex items-center gap-2 truncate font-medium text-white">
                {doc.source_type === 'url' || doc.source_type === 'html' ? (
                  <svg viewBox="0 0 24 24" className="h-4 w-4 shrink-0 text-cyan-300" fill="none" stroke="currentColor" strokeWidth="2" aria-label="Web document">
                    <circle cx="12" cy="12" r="9" /><path d="M3 12h18M12 3c2.5 2.5 3.5 5.5 3.5 9S14.5 19.5 12 21c-2.5-1.5-3.5-5.5-3.5-9S9.5 5.5 12 3Z" />
                  </svg>
                ) : null}
                <span className="truncate">{doc.title}</span>
              </p>
              <p className="text-xs text-slate-500">{doc.original_filename}</p>
              {doc.status === 'failed' && doc.error_message && (
                <p className="mt-1 text-xs text-red-300">{doc.error_message}</p>
              )}
            </div>
            <div className="flex shrink-0 items-center gap-2">
              {doc.department && (
                <span className="rounded-full border border-slate-700 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-slate-400">
                  {doc.department}
                </span>
              )}
              <span className={`rounded-full px-2.5 py-1 text-[10px] font-semibold ${statusColor[doc.status] ?? ''}`}>
                {t(`documents.status.${doc.status}`)}
              </span>
              <button
                type="button"
                aria-label={`View ${doc.title}`}
                title="View document"
                onClick={(event) => {
                  event.stopPropagation()
                  void viewDocument(doc)
                }}
                className="inline-flex items-center gap-1 rounded-full border border-emerald-500/40 bg-emerald-500/10 px-2.5 py-1 text-[10px] font-semibold text-emerald-200 transition hover:bg-emerald-500/20"
              >
                <svg viewBox="0 0 24 24" className="h-3.5 w-3.5 fill-none stroke-current stroke-2" aria-hidden="true">
                  <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" />
                  <circle cx="12" cy="12" r="2.5" />
                </svg>
                View
              </button>
            </div>
          </li>
        ))}
      </ul>
      )}
      {selectedDocument && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/85 p-4 backdrop-blur-sm"
          role="dialog"
          aria-modal="true"
          aria-labelledby="document-viewer-title"
          onClick={closeViewer}
        >
          <div
            className="flex h-[min(90vh,900px)] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-slate-700 bg-slate-900 shadow-2xl"
            onClick={(event) => event.stopPropagation()}
          >
            <div className="flex shrink-0 items-center justify-between gap-4 border-b border-slate-700 px-5 py-4">
              <div className="min-w-0">
                <h3 id="document-viewer-title" className="truncate font-semibold text-white">
                  {selectedDocument.title}
                </h3>
                <p className="truncate text-xs text-slate-400">{selectedDocument.original_filename}</p>
              </div>
              <button
                type="button"
                onClick={closeViewer}
                className="rounded-lg border border-slate-600 px-3 py-1.5 text-sm text-slate-300 transition hover:border-red-400 hover:text-red-200"
              >
                Close
              </button>
            </div>
            <div className="min-h-0 flex-1 bg-slate-950 p-4">
              {viewLoading && <div className="flex h-full items-center justify-center"><LoadingState label="Loading document..." /></div>}
              {viewError && <p className="rounded-xl border border-red-500/40 bg-red-950/40 p-4 text-sm text-red-200">{viewError}</p>}
              {documentUrl && !viewLoading && (
                <iframe
                  src={documentUrl}
                  title={`Preview of ${selectedDocument.title}`}
                  className="h-full min-h-[500px] w-full rounded-lg border border-slate-800"
                />
              )}
              {textContent !== null && !viewLoading && (
                <pre className="h-full overflow-auto whitespace-pre-wrap rounded-lg border border-slate-800 bg-slate-900 p-5 text-sm leading-6 text-slate-200">
                  {textContent}
                </pre>
              )}
            </div>
          </div>
        </div>
      )}
    </section>
  )
}
