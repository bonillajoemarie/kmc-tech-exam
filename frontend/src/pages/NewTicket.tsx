import { useState } from 'react'
import type { FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api } from '../lib/api'
import { ApiError } from '../lib/api'
import { useMeta } from '../lib/useMeta'
import type { Ticket } from '../types'

const inputClass =
  'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20'

export function NewTicket() {
  const { meta } = useMeta()
  const navigate = useNavigate()
  const [subject, setSubject] = useState('')
  const [description, setDescription] = useState('')
  const [categoryId, setCategoryId] = useState('')
  const [priorityId, setPriorityId] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    const errors: Record<string, string[]> = {}
    if (!subject.trim()) errors.subject = ['Subject is required.']
    if (!description.trim()) errors.description = ['Description is required.']
    setFieldErrors(errors)
    setFormError(null)
    if (Object.keys(errors).length > 0) return

    setSubmitting(true)
    try {
      const ticket = await api<Ticket>('/tickets', {
        method: 'POST',
        body: {
          subject: subject.trim(),
          description: description.trim(),
          category_id: categoryId ? Number(categoryId) : undefined,
          priority_id: priorityId ? Number(priorityId) : undefined,
        },
      })
      navigate(`/tickets/${ticket.id}`, { replace: true })
    } catch (err) {
      if (err instanceof ApiError) {
        if (err.errors && Object.keys(err.errors).length > 0) {
          setFieldErrors(err.errors)
        } else {
          setFormError(err.message)
        }
      } else {
        setFormError('Something went wrong. Please try again.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-2xl">
        <div className="mb-6">
          <Link to="/tickets" className="text-sm font-medium text-indigo-600 hover:text-indigo-700">
            ← Back to My Tickets
          </Link>
          <h1 className="mt-2 text-2xl font-semibold tracking-tight text-slate-900">New Ticket</h1>
          <p className="mt-0.5 text-sm text-slate-500">Tell us what went wrong and we will get back to you</p>
        </div>

        <form
          onSubmit={handleSubmit}
          noValidate
          className="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
        >
          <div>
            <label htmlFor="subject" className="mb-1 block text-sm font-medium text-slate-700">
              Subject
            </label>
            <input
              id="subject"
              type="text"
              value={subject}
              onChange={(e) => setSubject(e.target.value)}
              placeholder="Briefly describe the issue"
              className={inputClass}
            />
            {fieldErrors.subject && (
              <p className="mt-1 text-xs text-red-600">{fieldErrors.subject[0]}</p>
            )}
          </div>

          <div>
            <label htmlFor="description" className="mb-1 block text-sm font-medium text-slate-700">
              Description
            </label>
            <textarea
              id="description"
              rows={6}
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Give us the details — what happened, when, and any error messages you saw."
              className={inputClass}
            />
            {fieldErrors.description && (
              <p className="mt-1 text-xs text-red-600">{fieldErrors.description[0]}</p>
            )}
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <label htmlFor="category" className="mb-1 block text-sm font-medium text-slate-700">
                Category
              </label>
              <select
                id="category"
                value={categoryId}
                onChange={(e) => setCategoryId(e.target.value)}
                className={inputClass}
              >
                <option value="">Select a category</option>
                {(meta?.categories ?? []).map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
              {fieldErrors.category_id && (
                <p className="mt-1 text-xs text-red-600">{fieldErrors.category_id[0]}</p>
              )}
            </div>
            <div>
              <label htmlFor="priority" className="mb-1 block text-sm font-medium text-slate-700">
                Priority
              </label>
              <select
                id="priority"
                value={priorityId}
                onChange={(e) => setPriorityId(e.target.value)}
                className={inputClass}
              >
                <option value="">Select a priority</option>
                {(meta?.priorities ?? []).map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name}
                  </option>
                ))}
              </select>
              {fieldErrors.priority_id && (
                <p className="mt-1 text-xs text-red-600">{fieldErrors.priority_id[0]}</p>
              )}
            </div>
          </div>

          {formError && (
            <div className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
              {formError}
            </div>
          )}

          <div className="flex items-center justify-end gap-3 pt-2">
            <Link
              to="/tickets"
              className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
            >
              Cancel
            </Link>
            <button
              type="submit"
              disabled={submitting}
              className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
              {submitting ? 'Submitting…' : 'Submit ticket'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}