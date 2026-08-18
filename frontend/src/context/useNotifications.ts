import { useContext } from 'react'
import { NotificationContext } from './notificationContext'
import type { NotificationContextValue } from './notificationContext'

export function useNotifications(): NotificationContextValue {
  const ctx = useContext(NotificationContext)
  if (!ctx) throw new Error('useNotifications must be used within a NotificationProvider')
  return ctx
}
