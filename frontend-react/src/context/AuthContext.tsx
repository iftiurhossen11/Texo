import { createContext, useContext, useMemo, useState, type ReactNode } from 'react'
import { api, ensureCsrfCookie } from '../api/client'

export interface AuthUser {
  id: number
  name: string
  email: string
  role: string
}

interface AuthContextValue {
  user: AuthUser | null
  token: string | null
  login: (email: string, password: string) => Promise<void>
  register: (name: string, email: string, password: string, passwordConfirmation: string) => Promise<void>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [token, setToken] = useState(() => localStorage.getItem('texo_auth_token'))
  const [user, setUser] = useState<AuthUser | null>(() => {
    const stored = localStorage.getItem('texo_auth_user')
    return stored ? (JSON.parse(stored) as AuthUser) : null
  })

  const value = useMemo<AuthContextValue>(() => ({
    user,
    token,
    async login(email, password) {
      await ensureCsrfCookie()
      const { data } = await api.post<{ token: string; user: AuthUser }>('/login', { email, password })
      localStorage.setItem('texo_auth_token', data.token)
      localStorage.setItem('texo_auth_user', JSON.stringify(data.user))
      setToken(data.token)
      setUser(data.user)
    },
    async register(name, email, password, passwordConfirmation) {
      await ensureCsrfCookie()
      const { data } = await api.post<{ token: string; user: AuthUser }>('/register', {
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
      })
      localStorage.setItem('texo_auth_token', data.token)
      localStorage.setItem('texo_auth_user', JSON.stringify(data.user))
      setToken(data.token)
      setUser(data.user)
    },
    async logout() {
      try {
        if (token) await api.post('/logout')
      } finally {
        localStorage.removeItem('texo_auth_token')
        localStorage.removeItem('texo_auth_user')
        setToken(null)
        setUser(null)
      }
    },
  }), [token, user])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) throw new Error('useAuth must be used within AuthProvider')
  return context
}
