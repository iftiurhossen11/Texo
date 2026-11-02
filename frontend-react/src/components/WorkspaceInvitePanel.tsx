import { useCallback, useEffect, useState } from 'react'
import { api } from '../api/client'

interface WorkspaceInfo {
  id: number
  name: string
}

interface Invite {
  id: number
  role: string
  uses_remaining: number
  expires_at: string
  created_at: string
}

interface CreatedInvite {
  id: number
  code: string
  role: string
  uses_remaining: number
  expires_at: string
}

const inviteRoles = [
  ['employee', 'Standard user'],
  ['gm', 'General manager'],
  ['qa', 'QA'],
  ['hr', 'HR'],
  ['compliance', 'Compliance'],
  ['company_owner', 'Company Owner'],
  ['factory_owner', 'Factory Owner'],
] as const

export function WorkspaceInvitePanel() {
  const [workspace, setWorkspace] = useState<WorkspaceInfo | null>(null)
  const [invites, setInvites] = useState<Invite[]>([])
  const [role, setRole] = useState('employee')
  const [uses, setUses] = useState(1)
  const [expiresInDays, setExpiresInDays] = useState(7)
  const [createdInvite, setCreatedInvite] = useState<CreatedInvite | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [creating, setCreating] = useState(false)

  const load = useCallback(async () => {
    try {
      const [workspaceResponse, invitesResponse] = await Promise.all([
        api.get<{ data: WorkspaceInfo }>('/workspace'),
        api.get<{ data: Invite[] }>('/workspace/invites'),
      ])
      setWorkspace(workspaceResponse.data.data)
      setInvites(invitesResponse.data.data)
      setError(null)
    } catch {
      setError('Workspace invitations could not be loaded. Please try again.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const createInvite = async (event: React.FormEvent) => {
    event.preventDefault()
    setCreating(true)
    setError(null)
    setCreatedInvite(null)
    try {
      const { data } = await api.post<{ data: CreatedInvite }>('/workspace/invites', {
        role,
        uses,
        expires_in_days: expiresInDays,
      })
      setCreatedInvite(data.data)
      await load()
    } catch {
      setError('The invitation could not be created. Please try again.')
    } finally {
      setCreating(false)
    }
  }

  const copyCode = async () => {
    if (!createdInvite) return
    try {
      await navigator.clipboard.writeText(createdInvite.code)
    } catch {
      setError('Clipboard access failed. Select and copy the invitation code manually.')
    }
  }

  const revokeInvite = async (inviteId: number) => {
    setError(null)
    try {
      await api.delete(`/workspace/invites/${inviteId}`)
      await load()
    } catch {
      setError('The invitation could not be revoked. Please try again.')
    }
  }

  return (
    <section className="rounded-2xl border border-slate-800 bg-slate-900/70 p-5">
      <h2 className="text-xl font-semibold text-white">Workspace invitations</h2>
      <p className="mt-1 text-sm text-slate-400">
        {workspace ? `Create role-scoped invitations for ${workspace.name}.` : 'Create role-scoped invitations for your workspace.'}
        {' '}Owner roles can upload documents; other roles receive access based on their assigned role.
      </p>

      <form className="mt-5 grid gap-3 md:grid-cols-4" onSubmit={createInvite}>
        <label className="text-sm text-slate-300">
          Role
          <select
            value={role}
            onChange={(event) => setRole(event.target.value)}
            className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
          >
            {inviteRoles.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
          </select>
        </label>
        <label className="text-sm text-slate-300">
          Number of uses
          <input
            type="number"
            min={1}
            max={50}
            required
            value={uses}
            onChange={(event) => setUses(Number(event.target.value))}
            className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
          />
        </label>
        <label className="text-sm text-slate-300">
          Expires in (days)
          <input
            type="number"
            min={1}
            max={30}
            required
            value={expiresInDays}
            onChange={(event) => setExpiresInDays(Number(event.target.value))}
            className="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white"
          />
        </label>
        <button
          type="submit"
          disabled={creating || uses < 1 || uses > 50 || expiresInDays < 1 || expiresInDays > 30}
          className="self-end rounded-lg bg-emerald-500 px-4 py-2 font-semibold text-slate-950 disabled:opacity-50"
        >
          {creating ? 'Creating…' : 'Create invitation'}
        </button>
      </form>

      {error && <p role="alert" className="mt-4 text-sm text-red-300">{error}</p>}
      {createdInvite && (
        <div className="mt-4 rounded-xl border border-emerald-700/60 bg-emerald-950/30 p-4">
          <p className="text-sm font-semibold text-emerald-200">
            Share this {createdInvite.role} invitation. The code is shown only once.
          </p>
          <div className="mt-2 flex flex-wrap items-center gap-2">
            <code className="max-w-full break-all rounded bg-slate-950 px-3 py-2 text-sm text-white">{createdInvite.code}</code>
            <button type="button" onClick={() => void copyCode()} className="rounded-lg border border-emerald-700 px-3 py-2 text-sm text-emerald-200">
              Copy code
            </button>
          </div>
        </div>
      )}

      <div className="mt-5">
        <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-400">Issued invitations</h3>
        {loading ? (
          <p className="mt-2 text-sm text-slate-500">Loading…</p>
        ) : invites.length === 0 ? (
          <p className="mt-2 text-sm text-slate-500">No invitations yet.</p>
        ) : (
          <ul className="mt-2 divide-y divide-slate-800">
            {invites.map((invite) => (
              <li key={invite.id} className="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                <span className="text-slate-200">{invite.role}</span>
                <span className="flex items-center gap-3 text-slate-400">
                  {invite.uses_remaining} uses · expires {new Date(invite.expires_at).toLocaleDateString()}
                  <button type="button" onClick={() => void revokeInvite(invite.id)} className="text-red-300 hover:text-red-200">
                    Revoke
                  </button>
                </span>
              </li>
            ))}
          </ul>
        )}
      </div>
    </section>
  )
}
