import axios from 'axios'

const API_BASE = import.meta.env.DEV ? '' : (import.meta.env.VITE_API_BASE_URL ?? 'http://127.0.0.1:8000')

export const api = axios.create({
  baseURL: `${API_BASE}/api`,
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('texo_auth_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

let csrfInitialized = false

export async function ensureCsrfCookie(): Promise<void> {
  if (csrfInitialized) return
  await axios.get(`${API_BASE}/sanctum/csrf-cookie`, { withCredentials: true })
  csrfInitialized = true
}

export { API_BASE }
