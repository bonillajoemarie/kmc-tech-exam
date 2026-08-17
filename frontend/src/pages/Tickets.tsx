import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../lib/api'
import { useMeta } from '../lib/useMeta'
import { formatRelativeTime } from '../lib/time'
import { Badge } from '../components/Badge'
import { PriorityBadge, StatusBadge } from '../components/StatusBadge'
import type { Ticket, TicketListResponse } from '../types'

interface Filters {
  status: string
  priority: string
  category: string
  search: string
  sort: string
}

const DEFAULT_FILTERS: Filters = { status: '', priority: '', category: '', search: '', sort: 'newest' }

const selectClass =
  'rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20'

function ticketSort(filters: Filters): { sort: string; order: string } {
  switch (filters.sort) {
    case 'oldest':
      return { sort: 'created_at', order: 'asc' }
    case 'updated':
      return { sort: 'updated_at', order: 'desc' }
    default:
      return { sort: 'created_at', order: 'desc' }
  }
}

export function Tickets() {
  const { meta } = useMeta()
  const [filters, setFilters] = useState<Filters>(DEFAULT_FILTERS)
  const [page, setPage] = useState(1)
  const [tickets, setTickets] = useState<Ticket[]>([])
  const [pagination, setPagination] = useState<{ current_page: number; last_page: number } | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const timer = setTimeout(() => {
      setPage(1)
    }, 350)
    return () => clearTimeout(timer)
  }, [filters.search])

  const loadTickets = useCallback(async () => {
    setLoading(true)
    setError(null)
    const { sort, order } = ticketSort(filters)
    try {
      const response = await api<TicketListResponse>(
        `/tickets?${new URLSearchParams({
          ...(filters.status ? { status: filters.status } : {}),
          ...(filters.priority ? { priority: filters.priority } : {}),
          ...(filters.category ? { category: filters.category } : {}),
          ...(filters.search.trim() ? { search: filters.search.trim() } : {}),
          sort,
          order,
          page: String(page),
        }).toString()}`,
      )
      setTickets(response.data)
      setPagination({ current_page: response.meta.current_page, last_page: response.meta.last_page })
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load tickets.')
      setTickets([])
      setPagination(null)
    } finally {
      setLoading(false)
    }
  }, [filters, page])

  useEffect(() => {
    const search = filters.search.trim()
    if (!search) {
      loadTickets()
      return
    }
    const timer = setTimeout(loadTickets, 350)
    return () => clearTimeout(timer)
  }, [loadTickets, filters.search])

  const filterCount = Object.values(filters).filter((v) => v !== '' && v !== 'newest').length

  return (
    <div className="p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-4xl">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight text-slate-900">My Tickets</h1>
            <p className="mt-0.5 text-sm text-slate-500">Track and manage your support requests</p>
          </div>
          <Link
            to="/tickets/new"
            className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-4 w-4">
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            New Ticket
          </Link>
        </div>

        <div className="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-5">
          <label className="block">
            <span className="mb-1 block text-xs font-medium text-slate-500">Status</span>
            <select
              value={filters.status}
              onChange={(e) => setFilters({ ...filters, status: e.target.value })}
              className={`${selectClass} w-full`}
            >
              <option value="">All statuses</option>
              {(meta?.statuses ?? []).map((s) => (
                <option key={s.id} value={s.slug}>
                  {s.name}
                </option>
              ))}
            </select>
          </label>
          <label className="block">
            <span className="mb-1 block text-xs font-medium text-slate-500">Priority</span>
            <select
              value={filters.priority}
              onChange={(e) => setFilters({ ...filters, priority: e.target.value })}
              className={`${selectClass} w-full`}
            >
              <option value="">All priorities</option>
              {(meta?.priorities ?? []).map((p) => (
                <option key={p.id} value={p.slug}>
                  {p.name}
                </option>
              ))}
            </select>
          </label>
          <label className="block">
            <span className="mb-1 block text-xs font-medium text-slate-500">Category</span>
            <select
              value={filters.category}
              onChange={(e) => setFilters({ ...filters, category: e.target.value })}
              className={`${selectClass} w-full`}
            >
              <option value="">All categories</option>
              {(meta?.categories ?? []).map((c) => (
                <option key={c.id} value={c.slug}>
                  {c.name}
                </option>
              ))}
            </select>
          </label>
          <label className="block">
            <span className="mb-1 block text-xs font-medium text-slate-500">Sort</span>
            <select
              value={filters.sort}
              onChange={(e) => setFilters({ ...filters, sort: e.target.value })}
              className={`${selectClass} w-full`}
            >
              <option value="newest">Newest first</option>
              <option value="oldest">Oldest first</option>
              <option value="updated">Recently updated</option>
            </select>
          </label>
          <label className="block sm:col-span-2 lg:col-span-1">
            <span className="mb-1 block text-xs font-medium text-slate-500">Search</span>
            <input
              type="search"
              value={filters.search}
              onChange={(e) => setFilters({ ...filters, search: e.target.value })}
              placeholder="Search tickets…"
              className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            />
          </label>
        </div>

        {filterCount > 0 && (
          <p className="mb-3 text-xs text-slate-500">
            {loading ? 'Loading…' : `Showing ${pagination ? pagination.current_page : 0} of ${pagination ? pagination.last_page : 0} pages — filters active`}
          </p>
        )}

        {error && (
          <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {error}
          </div>
        )}

        {loading ? (
          <div className="space-y-2">
            {[0, 1, 2].map((i) => (
              <div key={i} className="h-16 animate-pulse rounded-xl bg-slate-200" />
            ))}
          </div>
        ) : tickets.length === 0 ? (
          <div className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-6 w-6 text-slate-400">
                <path strokeLinecap="round" strokeLinejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-5l-5 5v-5z" />
              </svg>
            </div>
            <h2 className="text-base font-medium text-slate-900">No tickets yet — create your first one</h2>
            <p className="mt-1 text-sm text-slate-500">
              {filterCount > 0 ? 'Try clearing the filters to see more results.' : 'We are here to help.'}
            </p>
            <Link
              to="/tickets/new"
              className="mt-4 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
            >
              Create a ticket
            </Link>
          </div>
        ) : (
          <>
            <ul className="divide-y divide-slate-200 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
              {tickets.map((ticket) => (
                <li key={ticket.id}>
                  <Link
                    to={`/tickets/${ticket.id}`}
                    className="block px-4 py-3 transition hover:bg-indigo-50/50 sm:px-5"
                  >
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                      <span className="font-mono text-xs text-slate-400">{ticket.ticket_number}</span>
                      <span className="truncate text-sm font-medium text-slate-900">{ticket.subject}</span>
                      <span className="ml-auto flex shrink-0 items-center gap-2">
                        {ticket.priority && <PriorityBadge name={ticket.priority.name} color={ticket.priority.color} />}
                        {ticket.category && <Badge name={ticket.category.name} color={ticket.category.color} />}
                        <StatusBadge status={ticket.status} />
                      </span>
                    </div>
                    <div className="mt-1 flex items-center gap-3 text-xs text-slate-500">
                      <span>{formatRelativeTime(ticket.created_at)}</span>
                      <span aria-label={`${ticket.comments_count} comments`}>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="inline h-3.5 w-3.5">
                          <path strokeLinecap="round" strokeLinejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-5l-5 5v-5z" />
                        </svg>{' '}
                        {ticket.comments_count}
                      </span>
                    </div>
                  </Link>
                </li>
              ))}
            </ul>

            {pagination && pagination.last_page > 1 && (
              <div className="mt-4 flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <button
                  type="button"
                  disabled={page <= 1}
                  onClick={() => setPage((p) => p - 1)}
                  className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  Previous
                </button>
                <span className="text-sm text-slate-600">
                  Page {pagination.current_page} of {pagination.last_page}
                </span>
                <button
                  type="button"
                  disabled={page >= pagination.last_page}
                  onClick={() => setPage((p) => p + 1)}
                  className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  Next
                </button>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  )
}