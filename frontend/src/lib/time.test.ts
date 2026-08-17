import { describe, expect, it } from 'vitest'
import { formatRelativeTime } from './time'

const NOW = new Date('2026-03-01T12:00:00Z').getTime()

describe('formatRelativeTime', () => {
  it('formats minutes ago', () => {
    expect(formatRelativeTime(new Date(NOW - 5 * 60_000).toISOString(), NOW)).toBe('5 minutes ago')
  })

  it('formats hours ago', () => {
    expect(formatRelativeTime(new Date(NOW - 3 * 3_600_000).toISOString(), NOW)).toBe('3 hours ago')
  })

  it('formats days ago', () => {
    expect(formatRelativeTime(new Date(NOW - 2 * 86_400_000).toISOString(), NOW)).toBe('2 days ago')
  })

  it('formats near-future timestamps', () => {
    expect(formatRelativeTime(new Date(NOW + 3 * 60_000).toISOString(), NOW)).toBe('in 3 minutes')
  })

  it('falls back to an absolute date for timestamps older than 30 days', () => {
    const old = new Date(NOW - 35 * 86_400_000)
    expect(formatRelativeTime(old.toISOString(), NOW)).toBe('Jan 25, 2026')
  })

  it('returns an empty string for invalid input', () => {
    expect(formatRelativeTime('not-a-date', NOW)).toBe('')
  })
})
