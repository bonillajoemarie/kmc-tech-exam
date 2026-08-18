import { useCallback, useEffect, useRef, useState } from 'react'
import type { FormEvent } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api } from '../lib/api'
import { ApiError } from '../lib/api'
import { addComment } from '../lib/api'
import { subscribeToComments } from '../lib/echo'
import { useAuth } from '../context/useAuth'
import { formatRelativeTime } from '../lib/time'
import { Badge } from '../components/Badge'
import { PriorityBadge, StatusBadge } from '../components/StatusBadge'
import type { Comment, TicketDetail } from '../types'

const ACCEPTED_FILES = 'image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip'

function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
}

function formatBytes(bytes: number): string {
  if (!bytes || bytes <= 0) return '0 B'
  const units = ['B', 'KB', 'MB', 'GB']
  const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1)
  const value = bytes / 1024 ** i
  return `${value >= 10 || i === 0 ? Math.round(value) : value.toFixed(1)} ${units[i]}`
}

function CommentAvatar({ name }: { name: string }) {
  return (
    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">
      {initials(name)}
    </div>
  )
}

function CommentAttachments({ comment }: { comment: Comment }) {
  const attachments = comment.attachments ?? []
  if (attachments.length === 0) return null
  return (
    <ul className="mt-2 flex flex-wrap gap-2">
      {attachments.map((a) => (
        <li key={a.url}>
          <a
            href={a.url}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex max-w-full items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-indigo-600 transition hover:border-indigo-300 hover:bg-indigo-50"
            title={a.name}
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-3.5 w-3.5 shrink-0">
              <path strokeLinecap="round" strokeLinejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
            </svg>
            <span className="truncate">{a.name}</span>
            <span className="shrink-0 text-slate-400">{formatBytes(a.size)}</span>
          </a>
        </li>
      ))}
    </ul>
  )
}

export function TicketDetail() {
  const { id } = useParams<{ id: string }>()
  const { user } = useAuth()
  const [ticket, setTicket] = useState<TicketDetail | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [content, setContent] = useState('')
  const [attachments, setAttachments] = useState<File[]>([])
  const fileInputRef = useRef<HTMLInputElement>(null)
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
    const timer = setTimeout(loadTicket, 0)
    return () => clearTimeout(timer)
  }, [loadTicket])

  useEffect(() => {
    if (!user) return
    return subscribeToComments(user.id, loadTicket)
  }, [user, loadTicket])

  async function handleComment(e: FormEvent) {
    e.preventDefault()
    if (!ticket) return
    const trimmed = content.trim()
    if (!trimmed && attachments.length === 0) return
    setPosting(true)
    setCommentErrors({})
    setCommentError(null)
    try {
      const comment = await addComment(ticket.id, trimmed, attachments)
      setTicket({ ...ticket, comments: [...ticket.comments, comment], comments_count: ticket.comments_count + 1 })
      setContent('')
      setAttachments([])
      if (fileInputRef.current) fileInputRef.current.value = ''
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

  function removeAttachment(index: number) {
    setAttachments((prev) => prev.filter((_, i) => i !== index))
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
                  <CommentAttachments comment={comment} />
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

          <div className="mt-3">
            <input
              ref={fileInputRef}
              id="attachments"
              type="file"
              multiple
              accept={ACCEPTED_FILES}
              onChange={(e) => setAttachments(Array.from(e.target.files ?? []))}
              className="sr-only"
              disabled={posting}
            />
            {attachments.length > 0 ? (
              <ul className="mb-2 flex flex-wrap gap-2">
                {attachments.map((file, index) => (
                  <li key={`${file.name}-${index}`}>
                    <span className="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700">
                      <span className="max-w-48 truncate">{file.name}</span>
                      <span className="text-indigo-400">{formatBytes(file.size)}</span>
                      <button
                        type="button"
                        onClick={() => removeAttachment(index)}
                        disabled={posting}
                        aria-label={`Remove ${file.name}`}
                        className="ml-0.5 rounded text-indigo-400 transition hover:text-red-500"
                      >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-3.5 w-3.5">
                          <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                      </button>
                    </span>
                  </li>
                ))}
              </ul>
            ) : null}
            <label
              htmlFor="attachments"
              className={`inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 ${
                posting ? 'cursor-not-allowed opacity-50' : ''
              }`}
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-4 w-4">
                <path strokeLinecap="round" strokeLinejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" />
              </svg>
              Attach files
            </label>
            <p className="mt-1.5 text-xs text-slate-400">Images, PDFs, Office documents, text, CSV or ZIP — up to several files.</p>
            {commentErrors.attachments && (
              <p className="mt-1 text-xs text-red-600">{commentErrors.attachments[0]}</p>
            )}
          </div>

          <div className="mt-3 flex justify-end">
            <button
              type="submit"
              disabled={posting || (!content.trim() && attachments.length === 0)}
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
