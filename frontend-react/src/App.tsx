import { useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ensureCsrfCookie } from './api/client'
import { ChatWindow } from './components/ChatWindow'
import { AnalyticsDashboard } from './components/AnalyticsDashboard'
import { DocumentStatusList } from './components/DocumentStatusList'
import { RetrievedContextPanel } from './components/RetrievedContextPanel'
import { LoginScreen } from './components/LoginScreen'
import { UploadPanel } from './components/UploadPanel'
import { useAuth } from './context/AuthContext'
import { useChat } from './hooks/useChat'
import { useDocuments } from './hooks/useDocuments'

function App() {
  const { user } = useAuth()

  if (!user) return <LoginScreen />

  return <AuthenticatedApp />
}

function AuthenticatedApp() {
  const { t, i18n } = useTranslation()
  const { documents, loading, refresh } = useDocuments()
  const chat = useChat()
  const { user, logout } = useAuth()
  const [lang, setLang] = useState<'en' | 'bn'>('en')
  const [activeTab, setActiveTab] = useState<'chat' | 'dashboard'>('chat')
  const [departmentFilter, setDepartmentFilter] = useState('all')
  const [helpOpen, setHelpOpen] = useState(false)
  const sessionStarted = useRef(false)
  const [lastLogin] = useState(() => {
    const key = `texo_last_login_${user?.id ?? 'unknown'}`
    const stored = localStorage.getItem(key)
    if (stored) return new Date(stored)
    const now = new Date()
    localStorage.setItem(key, now.toISOString())
    return now
  })

  useEffect(() => {
    if (sessionStarted.current) return
    sessionStarted.current = true
    void ensureCsrfCookie()
    void chat.startSession()
  }, [chat])

  useEffect(() => {
    void i18n.changeLanguage(lang)
  }, [lang, i18n])

  if (!user) return <LoginScreen />

  const accessLabel = user.role === 'gm' || user.role === 'admin'
    ? 'All Departments'
    : user.role === 'qa'
      ? 'QA + General'
      : user.role === 'hr'
        ? 'HR + General'
        : user.role === 'compliance'
          ? 'Compliance + General'
          : 'General Only'
  const canViewManagementTabs = user.role === 'gm' || user.role === 'admin'
  const accessBadge = user.role === 'gm' || user.role === 'admin'
    ? { classes: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200', icon: 'shield' }
    : user.role === 'hr'
      ? { classes: 'border-blue-500/40 bg-blue-500/10 text-blue-200', icon: 'people' }
      : user.role === 'qa'
        ? { classes: 'border-purple-500/40 bg-purple-500/10 text-purple-200', icon: 'check' }
        : { classes: 'border-slate-600 bg-slate-800/80 text-slate-300', icon: 'user' }
  const indexedCount = documents.filter((document) => document.status === 'indexed').length
  const suggestionsRevision = documents
    .map((document) => `${document.id}:${document.status}:${document.department}:${document.doc_type}`)
    .join('|')
  const formattedLastLogin = lastLogin.toLocaleString([], {
    weekday: 'short',
    hour: 'numeric',
    minute: '2-digit',
  })

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100">
      <header className="border-b border-slate-800 px-4 py-5 sm:px-6 lg:py-6">
        <div className="mx-auto max-w-[1600px]">
          <div className="flex flex-col items-center justify-between gap-4 text-center lg:flex-row lg:text-left">
            <div>
              <h1 className="text-3xl font-bold text-white md:text-4xl">{t('app.title')}</h1>
              <p className="mt-1 text-sm text-slate-400">{t('app.subtitle')}</p>
            </div>
            <span className={`inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm ${accessBadge.classes}`}>
              {accessBadge.icon === 'shield' && (
                <svg viewBox="0 0 24 24" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                  <path d="M12 3 19 6v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6l7-3Z" />
                </svg>
              )}
              {accessBadge.icon === 'people' && (
                <svg viewBox="0 0 24 24" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                  <circle cx="9" cy="8" r="3" /><path d="M3 19c0-3 2.5-5 6-5s6 2 6 5M16 11c2.8 0 5 1.6 5 4M16 5.5a2.5 2.5 0 0 1 0 5" />
                </svg>
              )}
              {accessBadge.icon === 'check' && (
                <svg viewBox="0 0 24 24" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                  <circle cx="12" cy="12" r="9" /><path d="m8 12 2.5 2.5L16 9" />
                </svg>
              )}
              {accessBadge.icon === 'user' && (
                <svg viewBox="0 0 24 24" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                  <circle cx="12" cy="8" r="3" /><path d="M5 20c.7-3.5 3-5 7-5s6.3 1.5 7 5" />
                </svg>
              )}
              <span>
                <span className="font-medium">Welcome, {user.name}</span>
                <span className="mx-2 opacity-50">|</span>
                <span className="text-xs opacity-80">Access: {accessLabel} · Last login: {formattedLastLogin}</span>
              </span>
            </span>
          </div>
          <div className="mt-5 flex flex-wrap items-center justify-center gap-2 border-t border-slate-800/80 pt-4 lg:justify-start">
            <div className="inline-flex items-center rounded-full border border-slate-700 bg-slate-900/80 px-3 py-1.5 text-xs">
              <span className="text-slate-400">Total: <strong className="text-slate-200">{documents.length}</strong></span>
              <span className="mx-2 text-slate-600">|</span>
              <span className="text-slate-400">Indexed: <strong className="text-emerald-200">{indexedCount}</strong></span>
              <span className="mx-2 text-slate-600">|</span>
              <span className="text-slate-400">Access: <strong className="text-slate-200">{accessLabel}</strong></span>
            </div>
          {(user.role === 'gm' || user.role === 'admin') && (
            <label className="inline-flex items-center gap-2 rounded-full border border-slate-700 bg-slate-900 px-3 py-1 text-sm text-slate-300">
              <span className="text-slate-500">Department Filter</span>
              <select
                value={departmentFilter}
                onChange={(event) => setDepartmentFilter(event.target.value)}
                className="bg-transparent text-emerald-200 outline-none"
              >
                <option value="all" className="bg-slate-900">All Departments</option>
                <option value="production" className="bg-slate-900">production</option>
                <option value="qa" className="bg-slate-900">qa</option>
                <option value="compliance_sustainability" className="bg-slate-900">compliance_sustainability</option>
                <option value="supply_chain_merchandising" className="bg-slate-900">supply_chain_merchandising</option>
                <option value="hr_training" className="bg-slate-900">hr_training</option>
                <option value="it_cybersecurity" className="bg-slate-900">it_cybersecurity</option>
                <option value="spinning" className="bg-slate-900">spinning</option>
                <option value="weaving" className="bg-slate-900">weaving</option>
                <option value="knitting" className="bg-slate-900">knitting</option>
                <option value="dyeing_finishing" className="bg-slate-900">dyeing_finishing</option>
                <option value="quality_control" className="bg-slate-900">quality_control</option>
                <option value="ppc" className="bg-slate-900">ppc</option>
                <option value="industrial_engineering" className="bg-slate-900">industrial_engineering</option>
                <option value="maintenance_engineering" className="bg-slate-900">maintenance_engineering</option>
                <option value="store_warehouse" className="bg-slate-900">store_warehouse</option>
                <option value="chemical_store" className="bg-slate-900">chemical_store</option>
                <option value="etp" className="bg-slate-900">etp</option>
                <option value="accounts_finance" className="bg-slate-900">accounts_finance</option>
                <option value="commercial_sales" className="bg-slate-900">commercial_sales</option>
                <option value="it_edp" className="bg-slate-900">it_edp</option>
                <option value="general" className="bg-slate-900">general</option>
              </select>
            </label>
          )}
          <button
            type="button"
            onClick={() => setHelpOpen(true)}
            aria-label="Open help and FAQ"
            title="Help and FAQ"
            className="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-700 text-sm font-bold text-slate-400 transition hover:border-emerald-400 hover:text-emerald-200"
          >
            ?
          </button>
          <button
            type="button"
            onClick={() => void logout()}
            className="rounded-full border border-slate-700 px-4 py-1 text-sm text-slate-400 transition hover:border-red-400 hover:text-red-300"
          >
            Log out
          </button>
          {(['en', 'bn'] as const).map((code) => (
            <button
              key={code}
              type="button"
              onClick={() => setLang(code)}
              className={`rounded-full px-4 py-1 text-sm ${
                lang === code
                  ? 'bg-emerald-500 text-slate-950'
                  : 'border border-slate-700 text-slate-400'
              }`}
            >
              {code.toUpperCase()}
            </button>
          ))}
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-[1600px] px-4 py-6 lg:px-8 lg:py-8">
        <div className="mb-6 flex rounded-2xl border border-slate-800 bg-slate-900/60 p-1">
          <button
            type="button"
            onClick={() => setActiveTab('chat')}
            className={`flex-1 rounded-xl px-4 py-3 text-sm font-semibold transition ${
              activeTab === 'chat'
                ? 'bg-emerald-500 text-slate-950'
                : 'text-slate-400 hover:text-white'
            }`}
          >
            Chat &amp; Documents
          </button>
          {canViewManagementTabs && (
            <button
              type="button"
              onClick={() => setActiveTab('dashboard')}
              className={`flex-1 rounded-xl px-4 py-3 text-sm font-semibold transition ${
                activeTab === 'dashboard'
                  ? 'bg-emerald-500 text-slate-950'
                  : 'text-slate-400 hover:text-white'
              }`}
            >
              Dashboard
            </button>
          )}
        </div>

        {activeTab !== 'dashboard' || !canViewManagementTabs ? (
          <div className="flex min-h-[600px] flex-col items-stretch gap-6 lg:h-[calc(100vh-250px)] lg:flex-row">
            <div className="flex min-h-0 min-w-0 flex-1 flex-col gap-4">
              <UploadPanel onUploaded={() => void refresh()} />
              <DocumentStatusList
                documents={documents}
                loading={loading}
                departmentFilter={user.role === 'gm' || user.role === 'admin' ? departmentFilter : undefined}
              />
            </div>
            <div className="grid h-full min-h-0 min-w-0 flex-1 grid-rows-[minmax(0,1fr)_16rem] gap-4">
              <ChatWindow
                messages={chat.messages}
                citations={chat.citations}
                loading={chat.loading}
                error={chat.error}
                onSend={chat.sendMessage}
                suggestionsRevision={suggestionsRevision}
                departmentFilter={user.role === 'gm' || user.role === 'admin' ? departmentFilter : undefined}
              />
              <div className="h-64 shrink-0">
                <RetrievedContextPanel chunks={chat.retrievedChunks} />
              </div>
            </div>
          </div>
        ) : (
          <AnalyticsDashboard />
        )}
      </main>
      {helpOpen && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/85 p-4 backdrop-blur-sm"
          role="dialog"
          aria-modal="true"
          aria-labelledby="help-title"
          onClick={() => setHelpOpen(false)}
        >
          <div
            className="w-full max-w-lg rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl"
            onClick={(event) => event.stopPropagation()}
          >
            <div className="flex items-center justify-between gap-4">
              <h2 id="help-title" className="text-lg font-semibold text-white">Help &amp; FAQ</h2>
              <button
                type="button"
                onClick={() => setHelpOpen(false)}
                className="rounded-lg border border-slate-600 px-3 py-1 text-sm text-slate-300 transition hover:border-red-400 hover:text-red-200"
              >
                Close
              </button>
            </div>
            <div className="mt-5 space-y-4 text-sm">
              <div>
                <h3 className="font-semibold text-emerald-200">How to search documents?</h3>
                <p className="mt-1 text-slate-400">Use the search field in Indexed Documents to filter documents by title, filename, or department.</p>
              </div>
              <div>
                <h3 className="font-semibold text-emerald-200">How to ask a question?</h3>
                <p className="mt-1 text-slate-400">Type your question in the chat box or choose a suggested prompt, then press Send.</p>
              </div>
              <div>
                <h3 className="font-semibold text-emerald-200">What does “Access Denied” mean?</h3>
                <p className="mt-1 text-slate-400">Your role does not have permission to access the requested department or document.</p>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

export default App
