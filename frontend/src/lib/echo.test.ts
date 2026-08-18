import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

type ChannelHandle = {
  channel: string
  listen: ReturnType<typeof vi.fn>
  stopListening: ReturnType<typeof vi.fn>
}

type EchoConfig = {
  broadcaster?: string
  key?: string
  wsHost?: string
  wsPort?: number
  forceTLS?: boolean
  enabledTransports?: string[]
  broadcastAuthEndpoint?: string
  auth?: { headers: Record<string, string> }
}

const { echoInstances } = vi.hoisted(() => ({
  echoInstances: [] as Array<{ config: EchoConfig; channels: Record<string, ChannelHandle> }>,
}))

vi.mock('laravel-echo', () => ({
  default: class EchoMock {
    config: EchoConfig
    channels: Record<string, ChannelHandle> = {}

    constructor(config: Record<string, unknown>) {
      this.config = config as EchoConfig
      echoInstances.push(this)
    }

    private(channel: string) {
      if (!this.channels[channel]) {
        const handle: ChannelHandle = {
          channel,
          listen: vi.fn(),
          stopListening: vi.fn(),
        }
        this.channels[channel] = handle
      }
      return this.channels[channel]
    }
  },
}))

const ENV_KEYS = ['VITE_REVERB_APP_KEY', 'VITE_REVERB_HOST', 'VITE_REVERB_PORT', 'VITE_REVERB_SCHEME']

function setEnv(values: Record<string, string>): void {
  const env = import.meta.env as Record<string, string | undefined>
  for (const key of ENV_KEYS) {
    delete env[key]
  }
  Object.assign(env, values)
}

async function loadEcho() {
  return import('./echo')
}

describe('echo', () => {
  beforeEach(() => {
    vi.resetModules()
    vi.clearAllMocks()
    echoInstances.length = 0
    localStorage.clear()
    setEnv({})
  })

  afterEach(() => {
    setEnv({})
  })

  it('returns a no-op subscription when the Reverb app key is missing', async () => {
    const { subscribeToComments } = await loadEcho()
    const handler = vi.fn()

    const unsubscribe = subscribeToComments(3, handler)
    expect(echoInstances).toHaveLength(0)
    expect(() => unsubscribe()).not.toThrow()
    expect(handler).not.toHaveBeenCalled()
  })

  it('builds the Pusher-protocol Echo config pointing at Reverb', async () => {
    setEnv({
      VITE_REVERB_APP_KEY: 'key-123',
      VITE_REVERB_HOST: 'localhost',
      VITE_REVERB_PORT: '8080',
      VITE_REVERB_SCHEME: 'http',
    })
    localStorage.setItem('supportdesk.token', 'tok-xyz')
    const { subscribeToComments } = await loadEcho()

    subscribeToComments(7, vi.fn())

    expect(echoInstances).toHaveLength(1)
    expect(echoInstances[0].config).toMatchObject({
      broadcaster: 'pusher',
      key: 'key-123',
      cluster: 'mt1',
      wsHost: 'localhost',
      wsPort: 8080,
      forceTLS: false,
      enabledTransports: ['ws', 'wss'],
      broadcastAuthEndpoint: '/broadcasting/auth',
    })
    expect(echoInstances[0].config.auth).toEqual({
      headers: expect.objectContaining({ Authorization: 'Bearer tok-xyz' }),
    })

    expect(echoInstances[0].channels['user.7']).toBeDefined()
  })

  it('reads the token from localStorage at listen time', async () => {
    setEnv({
      VITE_REVERB_APP_KEY: 'key-123',
      VITE_REVERB_HOST: 'localhost',
      VITE_REVERB_PORT: '8080',
      VITE_REVERB_SCHEME: 'http',
    })
    const { subscribeToComments } = await loadEcho()

    expect(echoInstances).toHaveLength(0)
    localStorage.setItem('supportdesk.token', 'tok-late')
    subscribeToComments(7, vi.fn())

    expect(echoInstances[0].config.auth!.headers.Authorization).toBe('Bearer tok-late')
  })

  it('enables TLS when the Reverb scheme is https', async () => {
    setEnv({
      VITE_REVERB_APP_KEY: 'key-123',
      VITE_REVERB_HOST: 'localhost',
      VITE_REVERB_PORT: '443',
      VITE_REVERB_SCHEME: 'https',
    })
    const { subscribeToComments } = await loadEcho()

    subscribeToComments(7, vi.fn())

    expect(echoInstances[0].config.forceTLS).toBe(true)
    expect(echoInstances[0].config.wsPort).toBe(443)
  })

  it('subscribes multiple handlers on one channel with a single listen', async () => {
    setEnv({
      VITE_REVERB_APP_KEY: 'key-123',
      VITE_REVERB_HOST: 'localhost',
      VITE_REVERB_PORT: '8080',
      VITE_REVERB_SCHEME: 'http',
    })
    const { subscribeToComments } = await loadEcho()

    const first = vi.fn()
    const second = vi.fn()
    subscribeToComments(7, first)
    subscribeToComments(7, second)

    const channel = echoInstances[0].channels['user.7']
    expect(channel.listen).toHaveBeenCalledTimes(1)
    expect(channel.listen).toHaveBeenCalledWith('TicketCommentCreated', expect.any(Function))
  })

  it('dispatches every registered handler and unsubscribes per handler', async () => {
    setEnv({
      VITE_REVERB_APP_KEY: 'key-123',
      VITE_REVERB_HOST: 'localhost',
      VITE_REVERB_PORT: '8080',
      VITE_REVERB_SCHEME: 'http',
    })
    const { subscribeToComments } = await loadEcho()

    const first = vi.fn()
    const second = vi.fn()
    subscribeToComments(7, first)
    const unsubscribeSecond = subscribeToComments(7, second)

    const channel = echoInstances[0].channels['user.7']
    const listenCallback = channel.listen.mock.calls[0][1]
    const event = {
      comment_id: 1,
      ticket_id: 10,
      author_id: 99,
      content: 'hello',
      created_at: '2026-08-18T00:00:00Z',
    }
    listenCallback(event)
    expect(first).toHaveBeenCalledWith(event)
    expect(second).toHaveBeenCalledWith(event)

    unsubscribeSecond()
    listenCallback(event)
    expect(first).toHaveBeenCalledTimes(2)
    expect(second).toHaveBeenCalledTimes(1)
  })

  it('stops listening and removes the channel when the last handler unsubscribes', async () => {
    setEnv({
      VITE_REVERB_APP_KEY: 'key-123',
      VITE_REVERB_HOST: 'localhost',
      VITE_REVERB_PORT: '8080',
      VITE_REVERB_SCHEME: 'http',
    })
    const { subscribeToComments } = await loadEcho()

    const unsubscribe = subscribeToComments(7, vi.fn())
    unsubscribe()

    const channel = echoInstances[0].channels['user.7']
    expect(channel.stopListening).toHaveBeenCalledWith('TicketCommentCreated')

    subscribeToComments(7, vi.fn())
    expect(channel.listen).toHaveBeenCalledTimes(2)
  })
})