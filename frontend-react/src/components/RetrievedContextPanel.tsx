import ReactMarkdown from 'react-markdown'
import remarkGfm from 'remark-gfm'
import { useTranslation } from 'react-i18next'
import type { RetrievedChunk } from '../hooks/useChat'

interface Props {
  chunks: RetrievedChunk[]
}

export function RetrievedContextPanel({ chunks }: Props) {
  const { t } = useTranslation()

  const highestSimilarity = chunks.reduce<number | null>((best, chunk) => {
    const score = typeof chunk.similarity_score === 'number' ? chunk.similarity_score : null
    if (score === null || Number.isNaN(score)) return best
    const normalized = score > 1 ? score : score * 100
    if (best === null) return normalized
    return Math.max(best, normalized)
  }, null)

  return (
    <section
      className="flex h-48 min-w-0 shrink-0 flex-col overflow-hidden rounded-2xl border p-4"
      style={{ backgroundColor: '#0f172a', borderColor: '#334155' }}
    >
      <div className="mb-2 flex shrink-0 items-center justify-between gap-3">
        <h2 className="text-lg font-semibold text-white">{t('context.title')}</h2>
        {highestSimilarity !== null && (
          <span className="inline-flex items-center rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-[11px] font-medium text-emerald-200">
            Match Confidence: {Math.round(highestSimilarity)}%
          </span>
        )}
      </div>
      <p className="mb-3 shrink-0 text-xs text-emerald-400/80">{t('context.trustNote')}</p>
      {!chunks.length ? (
        <div
          className="flex min-h-0 flex-1 items-center justify-center rounded-xl border border-solid border-slate-800 bg-slate-950 px-4 py-6 text-center text-slate-300"
          style={{ backgroundColor: '#020617', color: '#94a3b8', borderColor: '#1e293b' }}
        >
          {t('context.empty')}
        </div>
      ) : (
        <ul className="min-h-0 flex-1 space-y-3 overflow-y-auto">
          {chunks.map((chunk, i) => (
            <li
              key={i}
              className="rounded-xl border p-3 text-sm"
              style={{ backgroundColor: '#0f172a', color: '#e2e8f0', borderColor: '#1e293b' }}
            >
              {chunk.section_heading && (
                <p className="mb-1 text-xs font-medium text-emerald-300">{chunk.section_heading}</p>
              )}
              <div className="prose prose-invert max-w-none prose-p:my-1 prose-headings:mt-2 prose-headings:mb-1 prose-ul:my-2 prose-ol:my-2 prose-li:ml-4 prose-table:my-2 prose-th:p-2 prose-td:p-2 prose-code:rounded prose-code:bg-slate-700 prose-code:px-1 prose-code:py-0.5 prose-pre:overflow-x-auto prose-pre:bg-slate-950/70 prose-pre:p-3 prose-a:text-emerald-300 prose-a:underline">
                <ReactMarkdown remarkPlugins={[remarkGfm]}>{chunk.content}</ReactMarkdown>
              </div>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
