import { useCallback, useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api } from '../lib/api'
import { ApiError } from '../lib/api'
import { formatRelativeTime } from '../lib/time'
import { Badge } from '../components/Badge'
import { PriorityBadge, StatusBadge } from '../components/StatusBadge'
import type { Comment, TicketDetail } from '../types'

function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
}

function CommentAvatar({ name }: { name: string }) {
  return (
    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">
      {initials(name)}
    </div>
  )
}

export function TicketDetail() {
  const { id } = useParams<{ id: string }>()
  const [ticket, setTicket] = useState<TicketDetail | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [content, setContent] = useState('')
  const [commentErrors, setCommentErrors] = useState<Record<string, string[]>>({})
  const [commentError, setCommentError] = useState<string | null>(null)
  const [posting, setPosting] = useState(false)

  const loadTicket = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const data = await api<TicketDetail>(`/tickets/${id}`)
      setTicket(data)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load the ticket.')
    } finally {
      setLoading(false)
    }
  }, [id])

  useEffect(() => {
    loadTicket()
  }, [loadTicket])

  async function handleComment(e: FormEvent) {
    e.preventDefault()
    if (!content.trim() || !ticket) return
    setPosting(true)
    setCommentErrors({})
    setCommentError(null)
    try {
      const comment = await api<Comment>(`/tickets/${ticket.id}/comments`, {
        method: 'POST',
        body: { content: content.trim() },
      })
      setTicket({ ...ticket, comments: [...ticket.comments, comment], comments_count: ticket.comments_count + 1 })
      setContent('')
    } catch (err) {
      if (err instanceof ApiError) {
        if (err.errors && Object.keys(err.errors).length > 0) {
          setCommentErrors(err.errors)
        } else {
          setCommentError(err.message)
        }
      } else {
        setCommentError('Something went wrong. Please try again.')
      }
    } finally {
      setPosting(false)
    }
  }

  if (loading) {
    return (
      <div className="p-4 sm:p-6 lg:p-8">
        <div className="mx-auto max-w-3xl animate-pulse space-y-3">
          <div className="h-5 w-32 rounded bg-slate-200" />
          <div className="h-8 w-2/3 rounded bg-slate-200" />
          <div className="h-32 rounded-xl bg-slate-200" />
        </div>
      </div>
    )
  }

  if (error || !ticket) {
    return (
      <div className="p-4 sm:p-6 lg:p-8">
        <div className="mx-auto max-w-3xl rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {error ?? 'Ticket not found.'}
        </div>
      </div>
    )
  }

  return (
    <div className="p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-3xl">
        <div className="mb-4 flex items-center justify-between gap-3">
          <Link to="/tickets" className="text-sm font-medium text-indigo-600 hover:text-indigo-700">
            ← Back to My Tickets
          </Link>
          <span className="font-mono text-xs text-slate-400">{ticket.ticket_number}</span>
        </div>

        <div className="mb-6 flex flex-wrap items-center gap-2">
          <StatusBadge status={ticket.status} />
          {ticket.priority && <PriorityBadge name={ticket.priority.name} color={ticket.priority.color} />}
          {ticket.category && <Badge name={ticket.category.name} color={ticket.category.color} />}
          <span className="text-xs text-slate-500">
            Opened {formatRelativeTime(ticket.created_at)}
          </span>
        </div>

        <h1 className="mb-6 text-2xl font-semibold tracking-tight text-slate-900">{ticket.subject}</h1>

        {ticket.is_closed && (
          <div className="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            This ticket is closed. If you need further help, add a comment and we will take another look.
          </div>
        )}

        <div className="mb-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
          <p className="whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{ticket.description}</p>
        </div>

        <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">
          Comments ({ticket.comments.length})
        </h2>

        {ticket.comments.length === 0 ? (
          <div className="mb-6 rounded-xl border border-dashed border-slate-300 bg-white px-6 py-8 text-center text-sm text-slate-500">
            No comments yet. Be the first to respond.
          </div>
        ) : (
          <ul className="mb-6 space-y-4">
            {ticket.comments.map((comment) => (
              <li key={comment.id} className="flex gap-3">
                <CommentAvatar name={comment.user.name} />
                <div className="min-w-0 flex-1 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                  <div className="mb-1 flex flex-wrap items-baseline justify-between gap-2">
                    <span className="text-sm font-medium text-slate-900">{comment.user.name}</span>
                    <span className="text-xs text-slate-400">{formatRelativeTime(comment.created_at)}</span>
                  </div>
                  <p className="whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{comment.content}</p>
                </div>
              </li>
            ))}
          </ul>
        )}

        <form
          onSubmit={handleComment}
          noValidate
          className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
        >
          <label htmlFor="comment" className="mb-2 block text-sm font-medium text-slate-700">
            Add a comment
          </label>
          <textarea
            id="comment"
            rows={4}
            value={content}
            onChange={(e) => setContent(e.target.value)}
            placeholder="Write your reply…"
            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          />
          {commentErrors.content && (
            <p className="mt-1 text-xs text-red-600">{commentErrors.content[0]}</p>
          )}
          {commentError && (
            <p className="mt-1 text-xs text-red-600">{commentError}</p>
          )}
          <div className="mt-3 flex justify-end">
            <button
              type="submit"
              disabled={posting || !content.trim()}
              className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
              {posting ? 'Posting…' : 'Post comment'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}