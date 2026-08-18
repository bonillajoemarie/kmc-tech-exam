import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { act } from 'react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { NotificationProvider } from './NotificationContext'
import { useNotifications } from './useNotifications'
import type { CommentEvent } from '../lib/echo'

const { handlers, testUser } = vi.hoisted(() => ({
  handlers: new Map<number, (event: unknown) => void>(),
  testUser: { id: 1, name: 'Joe Customer', email: 'joe@example.com', roles: ['customer'] },
}))

vi.mock('../lib/echo', () => ({
  subscribeToComments: vi.fn((userId: number, handler: (event: unknown) => void) => {
    handlers.set(userId, handler)
    return () => handlers.delete(userId)
  }),
}))

vi.mock('./useAuth', () => ({
  useAuth: () => ({
    user: testUser,
    token: 'tok',
    login: vi.fn(),
    register: vi.fn(),
    logout: vi.fn(),
  }),
}))

const event = (overrides: Partial<CommentEvent> = {}): CommentEvent => ({
  comment_id: 10,
  ticket_id: 100,
  ticket_number: 'TK-000001-260818',
  author_id: 99,
  user_name: 'Staff Agent',
  content: 'We are looking into this.',
  created_at: '2026-08-18T02:00:00Z',
  ...overrides,
})

function dispatch(userId: number, payload: CommentEvent) {
  act(() => {
    handlers.get(userId)?.(payload)
  })
}

function Probe() {
  const { notifications, unreadCount, markAllRead, markRead, clear } = useNotifications()
  return (
    <div>
      <span data-testid="count">{unreadCount}</span>
      <span data-testid="ids">{notifications.map((n) => n.id).join(',')}</span>
      <span data-testid="read">{notifications.map((n) => (n.read ? 'r' : 'u')).join('')}</span>
      <button type="button" onClick={markAllRead}>
        mark-all
      </button>
      <button type="button" onClick={() => markRead('10')}>
        mark-10
      </button>
      <button type="button" onClick={clear}>
        clear
      </button>
    </div>
  )
}

function renderProbe() {
  return render(
    <NotificationProvider>
      <Probe />
    </NotificationProvider>,
  )
}

describe('NotificationProvider', () => {
  beforeEach(() => {
    handlers.clear()
    localStorage.clear()
  })

  afterEach(() => {
    handlers.clear()
  })

  it('adds a notification from a staff comment event', () => {
    renderProbe()
    expect(screen.getByTestId('count')).toHaveTextContent('0')

    dispatch(1, event())

    expect(screen.getByTestId('count')).toHaveTextContent('1')
    expect(screen.getByTestId('ids')).toHaveTextContent('10')
    expect(screen.getByTestId('read')).toHaveTextContent('u')
  })

  it('ignores the current users own comments', () => {
    renderProbe()
    dispatch(1, event({ author_id: 1, user_name: 'Joe Customer' }))
    expect(screen.getByTestId('count')).toHaveTextContent('0')
  })

  it('dedupes repeated events for the same comment', () => {
    renderProbe()
    dispatch(1, event())
    dispatch(1, event())
    expect(screen.getByTestId('count')).toHaveTextContent('1')
    expect(screen.getByTestId('ids')).toHaveTextContent('10')
  })

  it('marks one notification read', async () => {
    const user = userEvent.setup()
    renderProbe()
    dispatch(1, event())
    await user.click(screen.getByRole('button', { name: 'mark-10' }))
    expect(screen.getByTestId('count')).toHaveTextContent('0')
    expect(screen.getByTestId('read')).toHaveTextContent('r')
  })

  it('marks all notifications read', async () => {
    const user = userEvent.setup()
    renderProbe()
    dispatch(1, event({ comment_id: 10 }))
    dispatch(1, event({ comment_id: 11 }))
    expect(screen.getByTestId('count')).toHaveTextContent('2')
    await user.click(screen.getByRole('button', { name: 'mark-all' }))
    expect(screen.getByTestId('count')).toHaveTextContent('0')
  })

  it('clears the notification list', async () => {
    const user = userEvent.setup()
    renderProbe()
    dispatch(1, event())
    await user.click(screen.getByRole('button', { name: 'clear' }))
    expect(screen.getByTestId('ids')).toHaveTextContent('')
    expect(screen.getByTestId('count')).toHaveTextContent('0')
  })
})