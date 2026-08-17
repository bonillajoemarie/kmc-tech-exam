import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError, api, setAuthToken, setUnauthorizedHandler } from './api'

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
      '/api/user',
      expect.objectContaining({
        headers: expect.objectContaining({ Authorization: 'Bearer tok-123' }),
      }),
    )
  })

  it('does not send an Authorization header without a token', async () => {
    fetchMock.mockResolvedValue(jsonResponse(200, { data: [] }))

    await api('/tickets')

    const [, options] = fetchMock.mock.calls[0] as [string, RequestInit]
    expect(options.headers).toBeUndefined()
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
      '/api/auth/login',
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
})
