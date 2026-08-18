import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import type { ReactNode } from 'react'
import { subscribeToComments } from '../lib/echo'
import type { CommentEvent } from '../lib/echo'
import { useAuth } from './useAuth'
import { NotificationContext } from './notificationContext'
import type { NotificationItem } from './notificationContext'

const STORAGE_PREFIX = 'supportdesk.notifications'
const MAX_NOTIFICATIONS = 50

function storageKey(userId: number): string {
  return `${STORAGE_PREFIX}.${userId}`
}

function loadStored(userId: number): NotificationItem[] {
  try {
    const raw = localStorage.getItem(storageKey(userId))
    const parsed = raw ? (JSON.parse(raw) as NotificationItem[]) : []
    return Array.isArray(parsed) ? parsed : []
  } catch {
    return []
  }
}

export function NotificationProvider({ children }: { children: ReactNode }) {
  const { user } = useAuth()
  const userId = user?.id
  const [notifications, setNotifications] = useState<NotificationItem[]>(() =>
    userId ? loadStored(userId) : [],
  )
  const seen = useRef(new Set<string>())

  useEffect(() => {
    seen.current.clear()
    if (!userId) return

    return subscribeToComments(userId, (event: CommentEvent) => {
      if (event.author_id === userId) return
      const id = String(event.comment_id)
      if (seen.current.has(id)) return
      seen.current.add(id)

      const item: NotificationItem = {
        id,
        ticketId: event.ticket_id,
        ticketNumber: event.ticket_number ?? null,
        authorId: event.author_id ?? null,
        authorName: event.user_name ?? 'Someone',
        content: event.content,
        createdAt: event.created_at,
        read: false,
      }
      setNotifications((prev) => [item, ...prev].slice(0, MAX_NOTIFICATIONS))
    })
  }, [userId])

  useEffect(() => {
    if (!userId) return
    localStorage.setItem(storageKey(userId), JSON.stringify(notifications))
  }, [notifications, userId])

  const markAllRead = useCallback(() => {
    setNotifications((prev) => prev.map((item) => ({ ...item, read: true })))
  }, [])

  const markRead = useCallback((id: string) => {
    setNotifications((prev) => prev.map((item) => (item.id === id ? { ...item, read: true } : item)))
  }, [])

  const clear = useCallback(() => {
    setNotifications([])
  }, [])

  const value = useMemo(
    () => ({
      notifications,
      unreadCount: notifications.filter((item) => !item.read).length,
      markAllRead,
      markRead,
      clear,
    }),
    [notifications, markAllRead, markRead, clear],
  )

  return <NotificationContext.Provider value={value}>{children}</NotificationContext.Provider>
}
