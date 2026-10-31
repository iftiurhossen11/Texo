import { useCallback, useEffect, useRef, useState } from 'react'
import { api } from '../api/client'

export interface DocumentRow {
  id: number
  title: string
  original_filename: string
  file_type: string
  source_type: 'file' | 'html' | 'url'
  source_url: string | null
  doc_type: string
  department: string | null
  status: 'uploaded' | 'processing' | 'indexed' | 'failed'
  error_message: string | null
  created_at: string
  indexed_at: string | null
}

export function useDocuments(pollMs = 3000) {
  const [documents, setDocuments] = useState<DocumentRow[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const intervalRef = useRef<number | null>(null)

  const refresh = useCallback(async () => {
    try {
      const { data } = await api.get<{ data: DocumentRow[] }>('/documents')
      setDocuments(data.data)
      setError(null)
    } catch {
      setError('fetch_failed')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void refresh()
    intervalRef.current = window.setInterval(() => void refresh(), pollMs)
    return () => {
      if (intervalRef.current) window.clearInterval(intervalRef.current)
    }
  }, [pollMs, refresh])

  return { documents, loading, error, refresh }
}
