import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { api } from '../api/client'
import { ErrorState } from './StateViews'
import { useAuth } from '../context/AuthContext'

const DOC_TYPES = [
  'sop',
  'quality_manual',
  'buyer_requirement',
  'compliance_audit',
  'production_guideline',
  'hr_policy',
  'cybersecurity_policy',
  'supplier_guideline',
  'other',
] as const

const DEPARTMENTS = [
  'production',
  'qa',
  'compliance_sustainability',
  'supply_chain_merchandising',
  'hr_training',
  'it_cybersecurity',
  'spinning',
  'weaving',
  'knitting',
  'dyeing_finishing',
  'quality_control',
  'ppc',
  'industrial_engineering',
  'maintenance_engineering',
  'store_warehouse',
  'chemical_store',
  'etp',
  'accounts_finance',
  'commercial_sales',
  'it_edp',
  'general',
] as const

type TrackStatus = 'uploaded' | 'processing' | 'indexed' | 'failed'

function allowedDepartmentsForRole(role: string | undefined): readonly string[] {
  if (role === 'gm' || role === 'admin') return DEPARTMENTS
  if (role === 'qa') return ['qa', 'general']
  if (role === 'hr') return ['hr_training', 'general']
  if (role === 'compliance') return ['compliance_sustainability', 'general']
  return ['general']
}

function defaultDepartmentForRole(role: string | undefined): string {
  if (role === 'qa') return 'qa'
  if (role === 'hr') return 'hr_training'
  if (role === 'compliance') return 'compliance_sustainability'
  return 'general'
}

const PROGRESS_STEPS: TrackStatus[] = ['uploaded', 'processing', 'indexed']

interface Props {
  onUploaded: () => void
  onSuggestedQuestions?: (questions: string[]) => void
}

async function pollUntilDone(documentId: number): Promise<TrackStatus> {
  const deadline = Date.now() + 120_000
  while (Date.now() < deadline) {
    const { data } = await api.get<{ data: { status: TrackStatus; error_message?: string | null } }>(
      `/documents/${documentId}`,
    )
    const status = data.data.status
    if (status === 'indexed' || status === 'failed') {
      return status
    }
    await new Promise((r) => setTimeout(r, 1500))
  }
  return 'failed'
}

export function UploadPanel({ onUploaded, onSuggestedQuestions }: Props) {
  const { t } = useTranslation()
  const { user } = useAuth()
  const inputRef = useRef<HTMLInputElement>(null)
  const [docType, setDocType] = useState<string>('sop')
  const allowedDepartments = allowedDepartmentsForRole(user?.role)
  const [department, setDepartment] = useState<string>(() => defaultDepartmentForRole(user?.role))
  const [uploading, setUploading] = useState(false)
  const [trackStatus, setTrackStatus] = useState<TrackStatus | null>(null)
  const [error, setError] = useState<string | null>(null)

  const uploadFile = async (file: File) => {
    setUploading(true)
    setTrackStatus(null)
    setError(null)
    const form = new FormData()
    form.append('file', file)
    form.append('doc_type', docType)
    form.append('department', department)
    try {
      const { data } = await api.post<{
        document_id: number
        status: TrackStatus
        suggested_questions?: string[]
      }>('/documents', form, { headers: { 'Content-Type': 'multipart/form-data' } })
      setTrackStatus('uploaded')
      onSuggestedQuestions?.(data.suggested_questions ?? [])
      onUploaded()
      const final = await pollUntilDone(data.document_id)
      setTrackStatus(final)
      if (final === 'indexed') {
        onUploaded()
      } else {
        setError('errors.uploadFailed')
      }
    } catch {
      setError('errors.uploadFailed')
      setTrackStatus('failed')
    } finally {
      setUploading(false)
    }
  }

  const activeStep =
    trackStatus === 'failed' ? -1 : trackStatus ? PROGRESS_STEPS.indexOf(trackStatus) : -1

  return (
    <section className="rounded-2xl border border-slate-700 bg-slate-900/50 p-5">
      <h2 className="mb-4 text-lg font-semibold text-white">{t('upload.title')}</h2>
      <p className="mb-4 text-sm text-slate-400">{t('upload.scopeNote')}</p>
      <div
        className="mb-4 cursor-pointer rounded-xl border-2 border-dashed border-slate-600 bg-slate-950/50 p-8 text-center text-slate-400 hover:border-emerald-500/50"
        onClick={() => !uploading && inputRef.current?.click()}
        onDragOver={(e) => e.preventDefault()}
        onDrop={(e) => {
          e.preventDefault()
          const file = e.dataTransfer.files[0]
          if (file && !uploading) void uploadFile(file)
        }}
      >
        {uploading ? t('upload.uploading') : t('upload.dropHint')}
        <input
          ref={inputRef}
          type="file"
          accept=".pdf,.txt,.csv,.md,.docx,.html,.htm,.mhtml"
          className="hidden"
          disabled={uploading}
          onChange={(e) => {
            const file = e.target.files?.[0]
            if (file) void uploadFile(file)
          }}
        />
      </div>

      {trackStatus && (
        <div className="mb-4">
          <div className="mb-2 flex justify-between text-xs text-slate-400">
            {PROGRESS_STEPS.map((step, i) => (
              <span
                key={step}
                className={i <= activeStep ? 'font-medium text-emerald-400' : ''}
              >
                {t(`documents.status.${step}`)}
              </span>
            ))}
          </div>
          <div className="h-2 overflow-hidden rounded-full bg-slate-800">
            <div
              className={`h-full transition-all duration-500 ${
                trackStatus === 'failed' ? 'bg-red-500' : 'bg-emerald-500'
              }`}
              style={{
                width:
                  trackStatus === 'failed'
                    ? '100%'
                    : `${((activeStep + 1) / PROGRESS_STEPS.length) * 100}%`,
              }}
            />
          </div>
          {trackStatus === 'indexed' && (
            <p className="mt-2 text-sm text-emerald-400">{t('upload.indexed')}</p>
          )}
        </div>
      )}

      <div className="grid grid-cols-2 gap-3">
        <label className="text-sm text-slate-400">
          {t('upload.docType')}
          <select
            className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
            value={docType}
            onChange={(e) => setDocType(e.target.value)}
            disabled={uploading}
          >
            {DOC_TYPES.map((d) => (
              <option key={d} value={d}>
                {d}
              </option>
            ))}
          </select>
        </label>
        <label className="text-sm text-slate-400">
          {t('upload.department')}
          <select
            className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
            value={department}
            onChange={(e) => setDepartment(e.target.value)}
            disabled={uploading}
          >
            {DEPARTMENTS.filter((d) => allowedDepartments.includes(d)).map((d) => (
              <option key={d} value={d}>
                {d}
              </option>
            ))}
          </select>
        </label>
      </div>
      {error && (
        <div className="mt-3">
          <ErrorState messageKey={error} />
        </div>
      )}
    </section>
  )
}
