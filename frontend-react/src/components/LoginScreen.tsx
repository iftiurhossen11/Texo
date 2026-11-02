import { isAxiosError } from 'axios'
import { useState } from 'react'
import { api } from '../api/client'
import { isOwnerRole, type AccountType, useAuth } from '../context/AuthContext'

export function LoginScreen() {
  const { login, register } = useAuth()
  const searchParams = new URLSearchParams(window.location.search)
  const isOwnerActivation = searchParams.get('owner-activation') === '1'
  const resetToken = searchParams.get('token') ?? ''
  const [isRegistering, setIsRegistering] = useState(false)
  const [isForgotPassword, setIsForgotPassword] = useState(false)
  const [isResettingPassword, setIsResettingPassword] = useState(searchParams.get('password-reset') === '1')
  const [resetCompleted, setResetCompleted] = useState(false)
  const [recoveryMessage, setRecoveryMessage] = useState<string | null>(null)
  const [accountType, setAccountType] = useState<AccountType>(() => isOwnerActivation ? 'company_owner' : 'employee')
  const [showPasswords, setShowPasswords] = useState(false)
  const [name, setName] = useState('')
  const [email, setEmail] = useState(searchParams.get('email') ?? '')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [ownerSetupCode, setOwnerSetupCode] = useState('')
  const [invitationCode, setInvitationCode] = useState('')
  const [workspaceName, setWorkspaceName] = useState('')
  const isOwnerAccount = isOwnerRole(accountType)
  const showOwnerSetupCode = (isRegistering && isOwnerAccount) || (!isRegistering && isOwnerActivation)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)
  const passwordRequirements = [
    { label: 'At least 12 characters', met: password.length >= 12 },
    { label: 'At least one uppercase letter', met: /[A-Z]/.test(password) },
    { label: 'At least one lowercase letter', met: /[a-z]/.test(password) },
    { label: 'At least one number', met: /\d/.test(password) },
    { label: 'At least one symbol', met: /[^A-Za-z0-9]/.test(password) },
  ]
  const passwordIsStrong = passwordRequirements.every((requirement) => requirement.met)
  const passwordsMatch = password.length > 0 && password === passwordConfirmation
  const isRecoveryFlow = isForgotPassword || isResettingPassword || resetCompleted

  const submit = async (event: React.FormEvent) => {
    event.preventDefault()
    setError(null)
    setLoading(true)
    try {
      if (isForgotPassword) {
        const { data } = await api.post<{ message: string }>('/forgot-password', { email })
        setRecoveryMessage(data.message)
      } else if (isResettingPassword) {
        const { data } = await api.post<{ message: string }>('/reset-password', {
          token: resetToken,
          email,
          password,
          password_confirmation: passwordConfirmation,
        })
        setRecoveryMessage(data.message)
        setIsResettingPassword(false)
        setResetCompleted(true)
        setPassword('')
        setPasswordConfirmation('')
      } else if (isRegistering) {
        await register(
          name,
          email,
          password,
          passwordConfirmation,
          isOwnerAccount ? ownerSetupCode : '',
          accountType,
          invitationCode,
          workspaceName,
        )
      } else {
        await login(
          email,
          password,
          isOwnerActivation ? ownerSetupCode : '',
          isOwnerActivation ? accountType : undefined,
        )
      }
    } catch (requestError) {
      const validationMessage = isAxiosError<{ errors?: Record<string, string[]> }>(requestError)
        ? Object.values(requestError.response?.data?.errors ?? {}).flat()[0]
        : undefined
      const responseMessage = isAxiosError<{ message?: string }>(requestError)
        ? requestError.response?.data?.message
        : undefined
      setError(isRecoveryFlow
        ? validationMessage ?? responseMessage ?? 'Unable to process the password recovery request. Please try again.'
        : isRegistering
        ? validationMessage ?? (isOwnerAccount
          ? 'Owner account creation requires an authorized email and a valid setup code.'
          : 'Unable to create your account. Check your details, use a unique email, and try again.')
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
          {isForgotPassword
            ? resetCompleted
              ? 'Password updated'
              : 'Reset your password'
            : isResettingPassword
              ? 'Choose a new password'
              : isRegistering
            ? isOwnerAccount ? `Create your ${accountType === 'company_owner' ? 'company' : 'factory'} owner account` : 'Create your Texo account'
            : 'Secure workspace login'}
        </h1>
        <p className="mt-2 text-sm text-slate-400">
          {isForgotPassword
            ? resetCompleted
              ? 'Your password has been updated. Sign in with your new password.'
              : 'Enter your account email and we will send a password reset link if an account exists.'
            : isResettingPassword
              ? 'Choose a strong new password for your Texo account.'
              : isRegistering
            ? isOwnerAccount
              ? `Create or join a ${accountType === 'company_owner' ? 'company' : 'factory'} workspace using an owner setup code or invitation.`
              : 'Use an invitation code from your company or factory owner to join the workspace.'
            : 'Sign in to access your role-based factory knowledge workspace.'}
        </p>
        {recoveryMessage && (
          <div role="status" className="mt-4 rounded-xl border border-emerald-700/50 bg-emerald-950/40 p-3 text-sm text-emerald-200">
            {recoveryMessage}
            {!isResettingPassword && (
              <button
                type="button"
                onClick={() => {
                  setIsForgotPassword(false)
                  setResetCompleted(false)
                  setRecoveryMessage(null)
                  setError(null)
                  if (searchParams.has('password-reset')) {
                    window.history.replaceState({}, '', window.location.pathname)
                  }
                }}
                className="ml-2 font-semibold underline"
              >
                Back to sign in
              </button>
            )}
          </div>
        )}
        {!recoveryMessage && <form className="mt-6 space-y-4" onSubmit={submit}>
          {!isRecoveryFlow && (isRegistering || isOwnerActivation) && (
            <label className="block text-sm text-slate-300">
              Account type
              <select
                value={accountType}
                onChange={(event) => {
                  const selectedType = event.target.value
                  setAccountType(
                    selectedType === 'company_owner' || selectedType === 'factory_owner'
                      ? selectedType
                      : 'employee',
                  )
                  setOwnerSetupCode('')
                  setError(null)
                }}
                className="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-emerald-400"
              >
                {isRegistering && <option value="employee">Standard user</option>}
                <option value="company_owner">Company Owner</option>
                <option value="factory_owner">Factory Owner</option>
              </select>
            </label>
          )}
          {!isRecoveryFlow && isRegistering && (
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
          {!isRecoveryFlow && isRegistering && isOwnerAccount && (
            <label className="block text-sm text-slate-300">
              Company or factory name
              <input
                type="text"
                required={invitationCode.trim().length === 0}
                maxLength={255}
                value={workspaceName}
                onChange={(event) => setWorkspaceName(event.target.value)}
                className="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-emerald-400"
              />
              <span className="mt-1 block text-xs text-slate-500">
                Required when creating a workspace with the owner setup code.
              </span>
            </label>
          )}
          {!isRecoveryFlow && isRegistering && (
            <label className="block text-sm text-slate-300">
              Workspace invitation code
              <input
                type="text"
                required={!isOwnerAccount}
                autoComplete="off"
                maxLength={255}
                value={invitationCode}
                onChange={(event) => setInvitationCode(event.target.value)}
                className="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-emerald-400"
              />
              <span className="mt-1 block text-xs text-slate-500">
                Get this code from a workspace owner. Owner invitations can also be used to join as an owner.
              </span>
            </label>
          )}
          <label className="block text-sm text-slate-300">
            Email
            <input
              type="email"
              required
              autoComplete="email"
              maxLength={255}
              readOnly={isResettingPassword}
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              className="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-emerald-400"
            />
          </label>
          {!isForgotPassword && <label className="block text-sm text-slate-300">
            Password
            <input
              type={showPasswords ? 'text' : 'password'}
              required
              autoComplete={isRegistering || isResettingPassword ? 'new-password' : 'current-password'}
              minLength={isRegistering || isResettingPassword ? 12 : undefined}
              maxLength={128}
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              className="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-emerald-400"
            />
          </label>}
          {(isRegistering || isResettingPassword) && (
            <ul aria-live="polite" className="space-y-1 text-xs">
              {passwordRequirements.map(({ label, met }) => (
                <li key={label} className={met ? 'text-emerald-300' : 'text-slate-400'}>
                  <span aria-hidden="true">{met ? '✓' : '○'} </span>
                  {label}
                </li>
              ))}
            </ul>
          )}
          {!isRecoveryFlow && showOwnerSetupCode && (
            <label className="block text-sm text-slate-300">
              Company owner setup code
              <input
                type="password"
                required={isOwnerActivation}
                autoComplete="off"
                maxLength={255}
                value={ownerSetupCode}
                onChange={(event) => setOwnerSetupCode(event.target.value)}
                className="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-emerald-400"
              />
              <span className="mt-1 block text-xs text-slate-500">
                Only the configured company owner email can use this code.
              </span>
            </label>
          )}
          {(isRegistering || isResettingPassword) && (
            <label className="block text-sm text-slate-300">
              Confirm password
              <input
                type={showPasswords ? 'text' : 'password'}
                required
                autoComplete="new-password"
                minLength={12}
                maxLength={128}
                value={passwordConfirmation}
                onChange={(event) => setPasswordConfirmation(event.target.value)}
                className="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-emerald-400"
              />
              {passwordConfirmation.length > 0 && (
                <span className={`mt-1 block text-xs ${passwordsMatch ? 'text-emerald-300' : 'text-red-300'}`}>
                  {passwordsMatch ? 'Passwords match.' : 'Passwords do not match.'}
                </span>
              )}
            </label>
          )}
          {!isForgotPassword && <label className="flex items-center gap-2 text-sm text-slate-400">
            <input
              type="checkbox"
              checked={showPasswords}
              onChange={(event) => setShowPasswords(event.target.checked)}
              className="h-4 w-4 rounded border-slate-600 bg-slate-950 text-emerald-400 focus:ring-emerald-400"
            />
            Show password{isRegistering || isResettingPassword ? 's' : ''}
          </label>}
          {!isRecoveryFlow && isRegistering && isOwnerAccount && (
            <p className="text-xs text-slate-400">
              To create a workspace, use an authorized owner email and setup code. To join an existing workspace, enter an owner invitation code.
            </p>
          )}
          {error && <p role="alert" className="text-sm text-red-300">{error}</p>}
          {!recoveryMessage && <button
            type="submit"
            disabled={loading || ((isRegistering || isResettingPassword) && (!passwordIsStrong || !passwordsMatch))}
            className="w-full rounded-xl bg-emerald-500 px-4 py-3 font-semibold text-slate-950 transition hover:bg-emerald-400 disabled:opacity-50"
          >
            {loading
              ? 'Please wait…'
              : isForgotPassword
                ? 'Send reset link'
                : isResettingPassword
                  ? 'Save new password'
                  : isRegistering
                    ? 'Create account'
                    : 'Sign in'}
          </button>}
        </form>}
        {!isRecoveryFlow && !isOwnerActivation && !isRegistering && (
          <p className="mt-4 text-center text-sm">
            <button
              type="button"
              onClick={() => {
                setIsForgotPassword(true)
                setRecoveryMessage(null)
                setError(null)
              }}
              className="font-semibold text-emerald-300 hover:text-emerald-200"
            >
              Forgot password?
            </button>
          </p>
        )}
        {!isRecoveryFlow && <p className="mt-6 text-center text-sm text-slate-400">
          {isRegistering ? 'Already have an account?' : 'New to Texo?'}{' '}
          <button
            type="button"
            onClick={() => {
              setIsRegistering((current) => {
                if (current) setAccountType('employee')
                return !current
              })
              setRecoveryMessage(null)
              setOwnerSetupCode('')
              setInvitationCode('')
              setWorkspaceName('')
              setError(null)
            }}
            className="font-semibold text-emerald-300 hover:text-emerald-200"
          >
            {isRegistering ? 'Sign in' : 'Create an account'}
          </button>
        </p>}
        {isForgotPassword && (
          <p className="mt-6 text-center text-sm text-slate-400">
            Remembered it?{' '}
            <button
              type="button"
              onClick={() => {
                setIsForgotPassword(false)
                setResetCompleted(false)
                setRecoveryMessage(null)
                setError(null)
              }}
              className="font-semibold text-emerald-300 hover:text-emerald-200"
            >
              Back to sign in
            </button>
          </p>
        )}
      </section>
    </main>
  )
}
