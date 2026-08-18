import type { Comment } from '../types'

type ErrorBody = { message?: string; errors?: Record<string, string[]> }

export class ApiError extends Error {
  status: number
  errors?: Record<string, string[]>

  constructor(status: number, message: string, errors?: Record<string, string[]>) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }
}

let authToken: string | null =
  typeof localStorage !== 'undefined' ? localStorage.getItem('supportdesk.token') : null
let unauthorizedHandler: (() => void) | null = null

export function setAuthToken(token: string | null): void {
  authToken = token
}

export function setUnauthorizedHandler(handler: (() => void) | null): void {
  unauthorizedHandler = handler
}

interface ApiOptions {
  method?: string
  body?: unknown
  formData?: FormData
}

export async function api<T>(path: string, options: ApiOptions = {}): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json' }
  if (authToken) headers.Authorization = `Bearer ${authToken}`

  let body: BodyInit | undefined
  if (options.formData !== undefined) {
    body = options.formData
  } else if (options.body !== undefined) {
    headers['Content-Type'] = 'application/json'
    body = JSON.stringify(options.body)
  }

  let response: Response
  try {
    response = await fetch(`/api/v1${path}`, {
      method: options.method ?? 'GET',
      headers: Object.keys(headers).length > 0 ? headers : undefined,
      body,
    })
  } catch {
    throw new ApiError(0, 'Could not reach the server. Please check your connection and try again.')
  }

  const payload = (await response.json().catch(() => ({}))) as ErrorBody

  if (!response.ok) {
    if (response.status === 401) unauthorizedHandler?.()
    throw new ApiError(
      response.status,
      payload.message ?? `Request failed with status ${response.status}`,
      payload.errors,
    )
  }

  return payload as T
}

export function addComment(ticketId: number | string, content: string, attachments: File[] = []): Promise<Comment> {
  if (attachments.length === 0) {
    return api<Comment>(`/tickets/${ticketId}/comments`, { method: 'POST', body: { content } })
  }

  const formData = new FormData()
  formData.append('content', content)
  for (const file of attachments) {
    formData.append('attachments[]', file, file.name)
  }
  return api<Comment>(`/tickets/${ticketId}/comments`, { method: 'POST', formData })
}
