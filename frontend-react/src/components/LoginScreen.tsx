import { useState } from 'react'
import { useAuth } from '../context/AuthContext'

const demoUsers = [
  { label: 'Login as HR', email: 'hr@texo.com', role: 'HR Manager' },
  { label: 'Login as GM', email: 'gm@texo.com', role: 'General Manager' },
  { label: 'Login as Employee', email: 'employee@texo.com', role: 'Floor Employee' },
]

export function LoginScreen() {
  const { login } = useAuth()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  const submit = async (event: React.FormEvent) => {
    event.preventDefault()
    setError(null)
    setLoading(true)
    try {
      await login(email, password)
    } catch {
      setError('Invalid email or password.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <main className="flex min-h-screen items-center justify-center bg-slate-950 px-4 text-slate-100">
      <section className="w-full max-w-md rounded-3xl border border-slate-700 bg-slate-900 p-8 shadow-2xl">
        <p className="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-400">TEXO</p>
        <h1 className="mt-3 text-3xl font-bold text-white">Secure workspace login</h1>
        <p className="mt-2 text-sm text-slate-400">Sign in to access your role-based factory knowledge workspace.</p>
        <form className="mt-6 space-y-4" onSubmit={submit}>
          <label className="block text-sm text-slate-300">
            Email
            <input
              type="email"
              required
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              className="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-emerald-400"
            />
          </label>
          <label className="block text-sm text-slate-300">
            Password
            <input
              type="password"
              required
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              className="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-emerald-400"
            />
          </label>
          {error && <p className="text-sm text-red-300">{error}</p>}
          <button
            type="submit"
            disabled={loading}
            className="w-full rounded-xl bg-emerald-500 px-4 py-3 font-semibold text-slate-950 transition hover:bg-emerald-400 disabled:opacity-50"
          >
            {loading ? 'Signing in…' : 'Sign in'}
          </button>
        </form>
        <div className="mt-6 border-t border-slate-800 pt-5">
          <div className="grid gap-2">
            {demoUsers.map((demo) => (
              <button
                key={demo.email}
                type="button"
                onClick={() => {
                  setEmail(demo.email)
                  setPassword('password123')
                }}
                className="flex items-center justify-between rounded-xl border border-slate-700 px-3 py-2 text-left text-sm text-slate-300 transition hover:border-emerald-400 hover:text-white"
              >
                <span>{demo.label}</span>
                <span className="text-xs text-slate-500">{demo.role}</span>
              </button>
            ))}
          </div>
        </div>
      </section>
    </main>
  )
}
