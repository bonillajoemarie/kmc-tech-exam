import { createContext } from 'react'

export interface NotificationItem {
  id: string
  ticketId: number
  ticketNumber: string | null
  authorId: number | null
  authorName: string
  content: string
  createdAt: string
  read: boolean
}

export interface NotificationContextValue {
  notifications: NotificationItem[]
  unreadCount: number
  markAllRead: () => void
  markRead: (id: string) => void
  clear: () => void
}

export const NotificationContext = createContext<NotificationContextValue | null>(null)
