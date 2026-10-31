import { useTranslation } from 'react-i18next'

interface Props {
  label: string
}

export function LoadingState({ label }: Props) {
  return (
    <div className="flex items-center gap-3 rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-6 text-slate-300">
      <span className="h-4 w-4 animate-spin rounded-full border-2 border-emerald-400 border-t-transparent" />
      <span>{label}</span>
    </div>
  )
}

export function EmptyState({ label }: Props) {
  return (
    <div className="rounded-xl border border-slate-800 bg-slate-950 px-4 py-8 text-center text-slate-400">
      {label}
    </div>
  )
}

export function ErrorState({ messageKey }: { messageKey: string }) {
  const { t } = useTranslation()
  return (
    <div className="rounded-xl border border-red-500/40 bg-red-950/40 px-4 py-4 text-red-200">
      {t(messageKey, { defaultValue: t('errors.generic') })}
    </div>
  )
}
