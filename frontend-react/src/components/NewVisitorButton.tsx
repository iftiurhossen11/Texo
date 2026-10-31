import { useTranslation } from 'react-i18next'

interface Props {
  onClick: () => void
}

export function NewVisitorButton({ onClick }: Props) {
  const { t } = useTranslation()

  return (
    <button
      type="button"
      onClick={onClick}
      className="fixed right-6 top-6 z-50 rounded-full border border-amber-400/50 bg-amber-500/20 px-5 py-2 text-sm font-bold text-amber-200 shadow-lg hover:bg-amber-500/30"
    >
      {t('newVisitor')}
    </button>
  )
}
