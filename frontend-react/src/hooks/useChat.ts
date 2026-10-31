import { useCallback, useState } from 'react'
import { api } from '../api/client'

export interface ChatMessage {
  id: number
  role: 'user' | 'assistant'
  content: string
}

export interface RetrievedChunk {
  content: string
  section_heading?: string | null
  page_number?: number | null
  similarity_score?: number | null
  document?: { title?: string; original_filename?: string }
}

export interface Citation {
  document_title?: string
  document_filename?: string
  section_heading?: string | null
  page_number?: number | null
  similarity_score?: number | null
}

export function useChat() {
  const [sessionId, setSessionId] = useState<number | null>(null)
  const [messages, setMessages] = useState<ChatMessage[]>([])
  const [retrievedChunks, setRetrievedChunks] = useState<RetrievedChunk[]>([])
  const [citations, setCitations] = useState<Citation[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const startSession = useCallback(async () => {
    const { data } = await api.post<{ session_id: number }>('/chat/sessions', {
      context_tag: 'exhibition_booth',
    })
    setSessionId(data.session_id)
    setMessages([])
    setRetrievedChunks([])
    setCitations([])
    setError(null)
    return data.session_id
  }, [])

  const sendMessage = useCallback(
    async (content: string, departmentFilter?: string) => {
      setError(null)
      setLoading(true)
      let optimisticId: number | null = null

      try {
        let sid = sessionId
        if (!sid) sid = await startSession()

        const userMessageId = Date.now()
        optimisticId = userMessageId
        setMessages((prev) => [...prev, { id: userMessageId, role: 'user', content }])
        const { data } = await api.post<{
          user_message: ChatMessage
          assistant_message: ChatMessage
          retrieved_chunks?: RetrievedChunk[]
          citations?: Citation[]
          no_answer?: boolean
          error?: boolean
        }>(`/chat/sessions/${sid}/messages`, {
          content,
          ...(departmentFilter ? { department_filter: departmentFilter } : {}),
        })

        setMessages((prev) => [
          ...prev.filter((m) => m.id !== optimisticId),
          data.user_message,
          data.assistant_message,
        ])
        setRetrievedChunks(data.retrieved_chunks ?? [])
        setCitations(data.citations ?? [])
        if (data.error) setError('unavailable')
      } catch {
        if (optimisticId !== null) {
          setMessages((prev) => prev.filter((m) => m.id !== optimisticId))
        }
        setError('generic')
      } finally {
        setLoading(false)
      }
    },
    [sessionId, startSession],
  )

  const resetForNewVisitor = useCallback(async () => {
    await startSession()
  }, [startSession])

  return {
    sessionId,
    messages,
    retrievedChunks,
    citations,
    loading,
    error,
    sendMessage,
    resetForNewVisitor,
    startSession,
  }
}
