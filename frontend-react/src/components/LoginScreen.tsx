import { useState } from 'react'
import { useAuth } from '../context/AuthContext'

export function LoginScreen() {
  const { login, register } = useAuth()
  const [isRegistering, setIsRegistering] = useState(false)
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  const submit = async (event: React.FormEvent) => {
    event.preventDefault()
    setError(null)
    setLoading(true)
    try {
      if (isRegistering) {
        await register(name, email, password, passwordConfirmation)
      } else {
        await login(email, password)
      }
    } catch {
      setError(isRegistering
        ? 'Unable to create your account. Check your details, use a unique email, and try again.'
        : 'Invalid email or password.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <main className="flex min-h-screen items-center justify-center bg-slate-950 px-4 text-slate-100">
      <section className="w-full max-w-md rounded-3xl border border-slate-700 bg-slate-900 p-8 shadow-2xl">
        <p className="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-400">TEXO</p>
        <h1 className="mt-3 text-3xl font-bold text-white">
          {isRegistering ? 'Create your Texo account' : 'Secure workspace login'}
        </h1>
        <p className="mt-2 text-sm text-slate-400">
          {isRegistering
            ? 'Create an employee account to access general factory documents and AI chat.'
            : 'Sign in to access your role-based factory knowledge workspace.'}
        </p>
        <form className="mt-6 space-y-4" onSubmit={submit}>
          {isRegistering && (
            <label className="block text-sm text-slate-300">
              Full name
              <input
                type="text"
                required
                autoComplete="name"
                maxLength={255}
                value={name}
                onChange={(event) => setName(event.target.value)}
                className="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-emerald-400"
              />
            </label>
          )}
          <label className="block text-sm text-slate-300">
            Email
            <input
              type="email"
              required
              autoComplete="email"
              maxLength={255}
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
              autoComplete={isRegistering ? 'new-password' : 'current-password'}
              minLength={isRegistering ? 12 : undefined}
              maxLength={128}
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              className="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-emerald-400"
            />
          </label>
          {isRegistering && (
            <label className="block text-sm text-slate-300">
              Confirm password
              <input
                type="password"
                required
                autoComplete="new-password"
                minLength={12}
                maxLength={128}
                value={passwordConfirmation}
                onChange={(event) => setPasswordConfirmation(event.target.value)}
                className="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-emerald-400"
              />
            </label>
          )}
          {isRegistering && (
            <p className="text-xs text-slate-400">
              New accounts receive employee access and can only view general documents.
            </p>
          )}
          {error && <p className="text-sm text-red-300">{error}</p>}
          <button
            type="submit"
            disabled={loading}
            className="w-full rounded-xl bg-emerald-500 px-4 py-3 font-semibold text-slate-950 transition hover:bg-emerald-400 disabled:opacity-50"
          >
            {loading ? 'Please wait…' : isRegistering ? 'Create account' : 'Sign in'}
          </button>
        </form>
        <p className="mt-6 text-center text-sm text-slate-400">
          {isRegistering ? 'Already have an account?' : 'New to Texo?'}{' '}
          <button
            type="button"
            onClick={() => {
              setIsRegistering((current) => !current)
              setError(null)
            }}
            className="font-semibold text-emerald-300 hover:text-emerald-200"
          >
            {isRegistering ? 'Sign in' : 'Create an account'}
          </button>
        </p>
      </section>
    </main>
  )
}
