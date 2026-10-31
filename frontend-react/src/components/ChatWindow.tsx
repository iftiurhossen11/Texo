import { useEffect, useState } from 'react'
import jsPDF from 'jspdf'
import ReactMarkdown from 'react-markdown'
import remarkGfm from 'remark-gfm'
import { useTranslation } from 'react-i18next'
import { api } from '../api/client'
import type { ChatMessage, Citation } from '../hooks/useChat'
import { CitationBadge } from './CitationBadge'
import { ErrorState, LoadingState } from './StateViews'

interface Props {
  messages: ChatMessage[]
  citations: Citation[]
  loading: boolean
  error: string | null
  onSend: (text: string, departmentFilter?: string) => Promise<void>
  suggestionsRevision: string
  departmentFilter?: string
}

export function ChatWindow({
  messages,
  citations,
  loading,
  error,
  onSend,
  suggestionsRevision,
  departmentFilter,
}: Props) {
  const { t } = useTranslation()
  const [input, setInput] = useState('')
  const [inputError, setInputError] = useState<string | null>(null)
  const [copiedId, setCopiedId] = useState<number | null>(null)
  const [quickPrompts, setQuickPrompts] = useState<string[]>([])
  const [suggestionsError, setSuggestionsError] = useState(false)

  useEffect(() => {
    let active = true

    const fetchSuggestions = async () => {
      try {
        const { data } = await api.get<{ suggestions: string[] }>('/suggested-questions')
        if (active) {
          setQuickPrompts(data.suggestions)
          setSuggestionsError(false)
        }
      } catch {
        if (active) {
          setQuickPrompts([])
          setSuggestionsError(true)
        }
      }
    }

    void fetchSuggestions()

    return () => {
      active = false
    }
  }, [suggestionsRevision])

  const handleSend = async (valueOverride?: string) => {
    if (loading) return
    const nextValue = (valueOverride ?? input).trim()
    if (!nextValue) {
      setInputError('chat.emptyInput')
      return
    }
    if (nextValue.length > 500) return
    setInputError(null)
    setInput('')
    await onSend(nextValue, departmentFilter)
  }

  const handleCopy = async (id: number, content: string) => {
    try {
      await navigator.clipboard.writeText(content)
      setCopiedId(id)
      window.setTimeout(() => setCopiedId((current) => (current === id ? null : current)), 1400)
    } catch {
      // ignore clipboard failures for now
    }
  }

  const cleanText = (value: string) => {
    const text = String(value ?? '')
      .replace(/[^\x00-\x7F]/g, '')
      .replace(/[\\()$%#*|]/g, '')
      .replace(/[_`>]/g, '')
      .replace(/^\s*(?:User|Texo)\s*:\s*/i, '')
      .trim()

    return text || '[Bengali text omitted for PDF compatibility]'
  }

  const handleExportPdf = () => {
    const doc = new jsPDF()
    let y = 20
    const margin = 20
    const lineHeight = 5
    const messageSpacing = 10

    doc.setDocumentProperties({ title: 'Texo Chat Export' })
    doc.setFont('helvetica', 'bold')
    doc.setFontSize(16)
    doc.text('Texo Chat Export', margin, y)
    y += 18

    for (let index = 0; index < messages.length; index += 1) {
      const message = messages[index]
      const label = message.role === 'user' ? 'User: ' : 'Texo: '
      const text = cleanText(message.content)
      const wrappedText = doc.splitTextToSize(text, 180)

      if (y > 270) {
        doc.addPage()
        y = 20
      }

      doc.setFont('helvetica', 'bold')
      doc.setFontSize(11)
      doc.text(label.trimEnd(), margin, y)
      y += 6

      doc.setFont('helvetica', 'normal')
      doc.setFontSize(10)
      doc.text(wrappedText, margin, y)
      y += wrappedText.length * lineHeight + messageSpacing
    }

    doc.save('Texo_Chat_Export.pdf')
  }

  const lastAssistant = [...messages].reverse().find((m) => m.role === 'assistant')

  return (
    <section className="flex h-full min-h-0 min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-700 bg-slate-900/50 p-4">
      <div className="mb-3 flex shrink-0 items-center justify-between gap-4">
        <h2 className="text-lg font-semibold text-white">{t('chat.title')}</h2>
        <button
          type="button"
          onClick={handleExportPdf}
          className="rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-3 py-2 text-xs font-medium text-emerald-200 transition hover:bg-emerald-500/20"
        >
          Export Chat
        </button>
      </div>

      <div className="min-h-0 min-w-0 flex-1 space-y-3 overflow-y-auto rounded-xl border border-slate-800 bg-slate-950/60 p-4">
        {!messages.length && !loading && (
          <p className="text-sm text-slate-500">{t('chat.empty')}</p>
        )}
        {messages.map((m) => (
          <div
            key={m.id}
            className={`max-w-[90%] break-words overflow-visible rounded-2xl px-4 py-3 text-sm ${
              m.role === 'assistant' && m.content === 'Access Denied. You do not have permission to view this information.'
                ? 'border border-red-500/40 bg-red-500/10 text-red-200'
                : m.role === 'user'
                ? 'ml-auto bg-emerald-600/30 text-emerald-50'
                : 'bg-slate-800 text-slate-100'
            }`}
          >
            {m.role === 'user' ? (
              <div className="whitespace-pre-wrap">{m.content}</div>
            ) : (
              <div className="prose prose-invert max-w-none break-words overflow-visible whitespace-pre-wrap text-sm prose-p:my-1 prose-headings:mt-2 prose-headings:mb-1 prose-ul:my-1 prose-ol:my-1 prose-li:ml-4 prose-table:my-2 prose-th:p-2 prose-td:p-2 prose-code:rounded prose-code:bg-slate-700 prose-code:px-1 prose-code:py-0.5 prose-pre:overflow-x-auto prose-pre:bg-slate-950/70 prose-pre:p-3 prose-a:text-emerald-300 prose-a:underline">
                <ReactMarkdown remarkPlugins={[remarkGfm]}>{m.content}</ReactMarkdown>
              </div>
            )}

            {m.role === 'assistant' && (
              <div className="mt-3 flex justify-end">
                <button
                  type="button"
                  onClick={() => void handleCopy(m.id, m.content)}
                  className="rounded-md border border-slate-600 bg-slate-950/60 px-2 py-1 text-[10px] font-medium text-slate-300 transition hover:border-emerald-400 hover:text-emerald-200"
                >
                  {copiedId === m.id ? 'Copied!' : 'Copy'}
                </button>
              </div>
            )}
          </div>
        ))}
        {loading && <LoadingState label={t('chat.thinking')} />}
        {error && <ErrorState messageKey={`errors.${error}`} />}
        {lastAssistant && citations.length > 0 && (
          <div className="overflow-x-auto pt-1">
            <CitationBadge citations={citations} />
          </div>
        )}
      </div>
      <div className="shrink-0 border-t border-slate-800 pt-2">
        <p className="mb-2 text-xs font-medium text-slate-400">Suggested Questions</p>
        <div className="mb-3 flex flex-wrap gap-2">
          {quickPrompts.map((prompt) => (
            <button
              key={prompt}
              type="button"
              disabled={loading}
              onClick={() => void handleSend(prompt)}
              className="rounded-full border border-slate-600 bg-slate-800 px-3 py-1.5 text-xs text-slate-200 transition hover:border-emerald-400 hover:bg-slate-700 hover:text-emerald-200 disabled:cursor-not-allowed disabled:opacity-50"
            >
              {prompt}
            </button>
          ))}
          {suggestionsError && (
            <p className="text-xs text-amber-300">Suggested questions could not be loaded.</p>
          )}
        </div>
      </div>
      <div className="flex h-16 shrink-0 gap-2">
        <input
          className="flex-1 rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white placeholder:text-slate-500"
          placeholder={t('chat.placeholder')}
          value={input}
          maxLength={500}
          disabled={loading}
          onChange={(e) => setInput(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              e.preventDefault()
              void handleSend()
            }
          }}
        />
        <button
          type="button"
          className="rounded-xl bg-emerald-500 px-5 py-3 font-medium text-slate-950 hover:bg-emerald-400 disabled:opacity-50"
          disabled={loading}
          onClick={() => void handleSend()}
        >
          {t('chat.send')}
        </button>
      </div>
      {inputError && <p className="mt-2 text-sm text-amber-300">{t(inputError)}</p>}
    </section>
  )
}
