import Echo from 'laravel-echo'
import type { Channel } from 'laravel-echo'

const TOKEN_KEY = 'supportdesk.token'
const COMMENT_EVENT = 'TicketCommentCreated'

export interface CommentEvent {
  comment_id: number
  ticket_id: number
  ticket_number?: string | null
  author_id?: number | null
  user_name?: string | null
  content: string
  created_at: string
}

const key = import.meta.env.VITE_REVERB_APP_KEY as string | undefined
const scheme = (import.meta.env.VITE_REVERB_SCHEME as string | undefined) ?? 'http'

const authHeaders: Record<string, string> = {}

let echo: Echo<'pusher'> | null = null
const channels = new Map<string, Channel>()
const listeners = new Map<string, Set<(event: CommentEvent) => void>>()

function getEcho(): Echo<'pusher'> | null {
  if (!key) return null
  if (echo) return echo
  const host = (import.meta.env.VITE_REVERB_HOST as string | undefined) ?? 'localhost'
  const port = Number(import.meta.env.VITE_REVERB_PORT ?? 8080)
  echo = new Echo<'pusher'>({
    broadcaster: 'pusher',
    key,
    wsHost: host,
    wsPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    broadcastAuthEndpoint: '/broadcasting/auth',
    auth: { headers: authHeaders },
  })
  return echo
}

/**
 * Subscribe a handler to new comments on a user's private channel.
 * Multiple handlers may share one channel; returns an unsubscribe function.
 */
export function subscribeToComments(userId: number, handler: (event: CommentEvent) => void): () => void {
  const channelName = `private-user.${userId}`
  const token = localStorage.getItem(TOKEN_KEY)
  if (token) authHeaders.Authorization = `Bearer ${token}`

  let handlers = listeners.get(channelName)
  if (!handlers) {
    handlers = new Set()
    listeners.set(channelName, handlers)

    const instance = getEcho()
    if (instance) {
      const channel = instance.private(channelName)
      channel.listen(COMMENT_EVENT, (event: CommentEvent) => {
        for (const fn of listeners.get(channelName) ?? []) fn(event)
      })
      channels.set(channelName, channel)
    }
  }

  handlers.add(handler)

  return () => {
    const current = listeners.get(channelName)
    if (!current) return
    current.delete(handler)
    if (current.size === 0) {
      channels.get(channelName)?.stopListening(COMMENT_EVENT)
      channels.delete(channelName)
      listeners.delete(channelName)
    }
  }
}