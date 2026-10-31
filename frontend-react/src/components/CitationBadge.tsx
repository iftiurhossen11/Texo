import type { Citation } from '../hooks/useChat'
import { useTranslation } from 'react-i18next'

interface Props {
  citations: Citation[]
}

export function CitationBadge({ citations }: Props) {
  const { t } = useTranslation()
  if (!citations.length) return null

  return (
    <div className="mt-3 flex flex-wrap gap-2">
      {citations.map((c, i) => (
        <span
          key={i}
          className="inline-flex items-center rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs text-emerald-200"
        >
          {t('citation.source')}: {c.document_title ?? c.document_filename}
          {c.section_heading ? ` · ${c.section_heading}` : ''}
          {c.page_number ? ` · p.${c.page_number}` : ''}
        </span>
      ))}
    </div>
  )
}
