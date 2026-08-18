import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import { act } from 'react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { NotificationProvider } from '../context/NotificationContext'
import { NotificationBell } from './NotificationBell'
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

vi.mock('../context/useAuth', () => ({
  useAuth: () => ({
    user: testUser,
    token: 'tok',
    login: vi.fn(),
    register: vi.fn(),
    logout: vi.fn(),
  }),
}))

function dispatch(payload: CommentEvent) {
  act(() => {
    handlers.get(1)?.(payload)
  })
}

function LocationProbe() {
  const location = useLocation()
  return <span data-testid="path">{location.pathname}</span>
}

function renderBell() {
  return render(
    <MemoryRouter initialEntries={['/tickets']}>
      <NotificationProvider>
        <Routes>
          <Route
            path="/tickets"
            element={
              <>
                <NotificationBell />
                <LocationProbe />
              </>
            }
          />
          <Route
            path="/tickets/:id"
            element={
              <>
                <NotificationBell />
                <LocationProbe />
              </>
            }
          />
        </Routes>
      </NotificationProvider>
    </MemoryRouter>,
  )
}

describe('NotificationBell', () => {
  beforeEach(() => {
    handlers.clear()
    localStorage.clear()
  })

  afterEach(() => {
    handlers.clear()
  })

  it('shows the empty state when there are no notifications', async () => {
    const user = userEvent.setup()
    renderBell()

    expect(screen.getByRole('button', { name: 'Notifications (0 unread)' })).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Notifications (0 unread)' }))
    expect(screen.getByText('No notifications yet.')).toBeInTheDocument()
  })

  it('shows an unread badge and lists a new comment notification', async () => {
    const user = userEvent.setup()
    renderBell()

    dispatch({
      comment_id: 10,
      ticket_id: 100,
      ticket_number: 'TK-000001-260818',
      author_id: 99,
      user_name: 'Staff Agent',
      content: 'We are looking into this.',
      created_at: '2026-08-18T02:00:00Z',
    })

    expect(screen.getByRole('button', { name: 'Notifications (1 unread)' })).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Notifications (1 unread)' }))
    expect(screen.getByText('Staff Agent on TK-000001-260818')).toBeInTheDocument()
    expect(screen.getByText('We are looking into this.')).toBeInTheDocument()
  })

  it('redirects to the ticket when a notification is clicked and marks it read', async () => {
    const user = userEvent.setup()
    renderBell()

    dispatch({
      comment_id: 10,
      ticket_id: 100,
      ticket_number: 'TK-000001-260818',
      author_id: 99,
      user_name: 'Staff Agent',
      content: 'We are looking into this.',
      created_at: '2026-08-18T02:00:00Z',
    })

    await user.click(screen.getByRole('button', { name: 'Notifications (1 unread)' }))
    await user.click(screen.getByRole('button', { name: /Staff Agent on TK-000001-260818/ }))

    expect(screen.getByTestId('path')).toHaveTextContent('/tickets/100')
    expect(screen.getByRole('button', { name: 'Notifications (0 unread)' })).toBeInTheDocument()
  })

  it('clears notifications and the badge via Clear all', async () => {
    const user = userEvent.setup()
    renderBell()

    dispatch({
      comment_id: 10,
      ticket_id: 100,
      ticket_number: 'TK-000001-260818',
      author_id: 99,
      user_name: 'Staff Agent',
      content: 'We are looking into this.',
      created_at: '2026-08-18T02:00:00Z',
    })

    await user.click(screen.getByRole('button', { name: 'Notifications (1 unread)' }))
    await user.click(screen.getByRole('button', { name: 'Clear all' }))

    expect(screen.getByRole('button', { name: 'Notifications (0 unread)' })).toBeInTheDocument()
    expect(screen.getByText('No notifications yet.')).toBeInTheDocument()
  })
})