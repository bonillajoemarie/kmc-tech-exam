import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError, addComment, api, setAuthToken, setUnauthorizedHandler } from './api'

type RecordedFormData = {
  get: (name: string) => unknown
  getAll: (name: string) => unknown[]
}

function recordFormData(): { instances: RecordedFormData[]; FormDataClass: new () => RecordedFormData } {
  const instances: RecordedFormData[] = []
  class MockFormData {
    private entries = new Map<string, unknown[]>()

    constructor() {
      instances.push(this)
    }

    append(name: string, value: unknown, filename?: string) {
      const list = this.entries.get(name) ?? []
      list.push(filename !== undefined ? { value, filename } : value)
      this.entries.set(name, list)
    }

    get(name: string) {
      return this.entries.get(name)?.[0]
    }

    getAll(name: string) {
      return this.entries.get(name) ?? []
    }
  }
  return { instances, FormDataClass: MockFormData }
}

function jsonResponse(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

describe('api', () => {
  const fetchMock = vi.fn()

  beforeEach(() => {
    vi.stubGlobal('fetch', fetchMock)
    fetchMock.mockReset()
    setAuthToken(null)
    setUnauthorizedHandler(null)
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('attaches the auth token as a Bearer header when set', async () => {
    fetchMock.mockResolvedValue(jsonResponse(200, { id: 1, name: 'Ada' }))
    setAuthToken('tok-123')

    await api('/user')

    expect(fetchMock).toHaveBeenCalledWith(
      '/api/v1/user',
      expect.objectContaining({
        headers: expect.objectContaining({ Authorization: 'Bearer tok-123' }),
      }),
    )
  })

  it('sends an Accept header and no Authorization header without a token', async () => {
    fetchMock.mockResolvedValue(jsonResponse(200, { data: [] }))

    await api('/tickets')

    const [, options] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(options.headers).toEqual({ Accept: 'application/json' })
  })

  it('parses the JSON response body', async () => {
    fetchMock.mockResolvedValue(jsonResponse(200, { id: 7, name: 'Ada' }))

    const result = await api<{ id: number; name: string }>('/user')

    expect(result).toEqual({ id: 7, name: 'Ada' })
  })

  it('sends POST bodies as JSON with the correct content type', async () => {
    fetchMock.mockResolvedValue(jsonResponse(200, { ok: true }))

    await api('/auth/login', { method: 'POST', body: { email: 'a@b.c', password: 'secret' } })

    expect(fetchMock).toHaveBeenCalledWith(
      '/api/v1/auth/login',
      expect.objectContaining({
        method: 'POST',
        headers: expect.objectContaining({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ email: 'a@b.c', password: 'secret' }),
      }),
    )
  })

  it('throws an ApiError with status and message on 401', async () => {
    fetchMock.mockResolvedValue(jsonResponse(401, { message: 'Unauthenticated.' }))

    const err = await api('/tickets').catch((e: unknown) => e)

    expect(err).toBeInstanceOf(ApiError)
    expect(err).toMatchObject({ status: 401, message: 'Unauthenticated.' })
  })

  it('invokes the unauthorized handler when the API returns 401', async () => {
    const handler = vi.fn()
    setUnauthorizedHandler(handler)
    fetchMock.mockResolvedValue(jsonResponse(401, { message: 'Unauthenticated.' }))

    await api('/tickets').catch(() => undefined)

    expect(handler).toHaveBeenCalledTimes(1)
  })

  it('does not invoke the unauthorized handler on non-401 errors', async () => {
    const handler = vi.fn()
    setUnauthorizedHandler(handler)
    fetchMock.mockResolvedValue(jsonResponse(404, { message: 'Not found.' }))

    await api('/tickets/999').catch(() => undefined)

    expect(handler).not.toHaveBeenCalled()
  })

  it('parses 422 validation errors into ApiError.errors keyed by field', async () => {
    fetchMock.mockResolvedValue(
      jsonResponse(422, {
        message: 'The given data was invalid.',
        errors: { email: ['The email field is required.'] },
      }),
    )

    const err = await api('/auth/login', { method: 'POST', body: {} }).catch((e: unknown) => e)

    expect(err).toBeInstanceOf(ApiError)
    expect((err as ApiError).status).toBe(422)
    expect((err as ApiError).errors?.email).toEqual(['The email field is required.'])
  })

  it('addComment posts JSON when no attachments are provided', async () => {
    fetchMock.mockResolvedValue(jsonResponse(200, { id: 1, content: 'hi' }))

    await addComment(3, 'hello')

    expect(fetchMock).toHaveBeenCalledWith(
      '/api/v1/tickets/3/comments',
      expect.objectContaining({
        method: 'POST',
        headers: expect.objectContaining({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ content: 'hello' }),
      }),
    )
  })

  it('addComment builds multipart FormData with content and attachments[] when files are provided', async () => {
    const { instances, FormDataClass } = recordFormData()
    vi.stubGlobal('FormData', FormDataClass)
    fetchMock.mockResolvedValue(jsonResponse(200, { id: 1, content: 'hello' }))
    const file = new File(['a'], 'note.txt', { type: 'text/plain' })

    await addComment(3, 'hello', [file])

    expect(instances).toHaveLength(1)
    expect(instances[0].get('content')).toBe('hello')
    expect(instances[0].getAll('attachments[]')).toEqual([{ value: file, filename: 'note.txt' }])

    const [, options] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(options.method).toBe('POST')
    expect(options.body).toBe(instances[0])
    expect(options.headers).toEqual({ Accept: 'application/json' })
  })

  it('addComment appends multiple files to attachments[]', async () => {
    const { instances, FormDataClass } = recordFormData()
    vi.stubGlobal('FormData', FormDataClass)
    fetchMock.mockResolvedValue(jsonResponse(200, { id: 1, content: 'hello' }))
    const a = new File(['a'], 'a.pdf', { type: 'application/pdf' })
    const b = new File(['b'], 'b.png', { type: 'image/png' })

    await addComment(3, 'hello', [a, b])

    expect(instances[0].getAll('attachments[]')).toEqual([
      { value: a, filename: 'a.pdf' },
      { value: b, filename: 'b.png' },
    ])
  })

  it('addComment surfaces 422 validation errors as ApiError.errors', async () => {
    fetchMock.mockResolvedValue(
      jsonResponse(422, {
        message: 'The given data was invalid.',
        errors: { content: ['The content field is required.'] },
      }),
    )

    const err = await addComment(3, '').catch((e: unknown) => e)

    expect(err).toBeInstanceOf(ApiError)
    expect((err as ApiError).status).toBe(422)
    expect((err as ApiError).errors?.content).toEqual(['The content field is required.'])
  })
})
