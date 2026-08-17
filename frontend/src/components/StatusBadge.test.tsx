import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { StatusBadge } from './StatusBadge'
import type { Status } from '../types'

const status: Status = {
  id: 1,
  name: 'Open',
  slug: 'open',
  color: '#e11d48',
  is_closed: false,
}

describe('StatusBadge', () => {
  it('renders the status name', () => {
    render(<StatusBadge status={status} />)
    expect(screen.getByText('Open')).toBeInTheDocument()
  })

  it('colors the badge using the status record color', () => {
    render(<StatusBadge status={{ ...status, color: '#2563eb' }} />)
    const badge = screen.getByText('Open')
    expect(badge).toHaveStyle({ color: '#2563eb' })
    expect(badge).toHaveStyle({ backgroundColor: 'rgba(37, 99, 235, 0.12)' })
  })

  it('renders a rounded pill shape', () => {
    render(<StatusBadge status={status} />)
    expect(screen.getByText('Open')).toHaveClass('rounded-full')
  })
})
