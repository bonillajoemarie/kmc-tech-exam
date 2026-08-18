// Dependency-free static server for the built Vite bundle (frontend/dist).
// Serves the SPA on :5173 and reverse-proxies API/broadcasting traffic to the
// Laravel backend container. Runs under pm2 via ecosystem.config.cjs.
//
// Note on /broadcasting: the only traffic that path ever receives in this app
// is the `POST /broadcasting/auth` channel-authorization call (src/lib/echo.ts
// sets broadcastAuthEndpoint to it). That route is served by the Laravel HTTP
// stack (Illuminate\Broadcasting\BroadcastController), NOT by Reverb -- Reverb's
// pusher router only exposes /app/{appKey}, /apps/{appId}/events, /up, etc.
// So BROADCAST_TARGET points at the HTTP port (8000). The browser reaches the
// Reverb WebSocket itself at ws://VITE_REVERB_HOST:VITE_REVERB_PORT (baked in
// at build time), which docker-compose publishes from the backend's :8080.

import http from 'node:http'
import { createReadStream, existsSync, statSync } from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const DIST = path.resolve(__dirname, 'dist')
const PORT = Number(process.env.PORT ?? 5173)
const API_TARGET = process.env.API_TARGET ?? 'http://backend:8000'
const BROADCAST_TARGET = process.env.BROADCAST_TARGET ?? 'http://backend:8000'

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.mjs': 'text/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.map': 'application/json',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.gif': 'image/gif',
  '.webp': 'image/webp',
  '.ico': 'image/x-icon',
  '.woff': 'font/woff',
  '.woff2': 'font/woff2',
  '.ttf': 'font/ttf',
  '.txt': 'text/plain; charset=utf-8',
}

function proxy(req, res, target) {
  const { hostname, port } = new URL(target)

  const upstream = http.request(
    {
      hostname,
      port,
      path: req.url,
      method: req.method,
      headers: {
        ...req.headers,
        host: hostname + (port ? `:${port}` : ''),
      },
    },
    (up) => {
      res.writeHead(up.statusCode ?? 502, up.headers)
      up.pipe(res)
    },
  )

  upstream.on('error', () => {
    if (!res.headersSent) res.writeHead(502)
    res.end('Bad Gateway')
  })

  req.pipe(upstream)
}

function serveStatic(req, res, pathname) {
  if (req.method !== 'GET' && req.method !== 'HEAD') {
    res.writeHead(405, { Allow: 'GET, HEAD' })
    res.end('Method Not Allowed')
    return
  }

  let filePath = path.normalize(path.join(DIST, pathname))

  // Guard against path traversal outside the dist directory.
  if (filePath !== DIST && !filePath.startsWith(DIST + path.sep)) {
    res.writeHead(403)
    res.end('Forbidden')
    return
  }

  if (existsSync(filePath) && statSync(filePath).isDirectory()) {
    filePath = path.join(filePath, 'index.html')
  }

  if (!existsSync(filePath) || !statSync(filePath).isFile()) {
    // SPA fallback: client-side routes (/, /login, /tickets/:id, ...) all get
    // index.html; anything that looks like a file (has a dot in its last
    // segment, e.g. assets or dotfiles) 404s instead.
    if (path.basename(pathname).includes('.')) {
      res.writeHead(404)
      res.end('Not found')
      return
    }
    filePath = path.join(DIST, 'index.html')
    if (!existsSync(filePath)) {
      res.writeHead(404)
      res.end('Not found')
      return
    }
  }

  const type = MIME[path.extname(filePath).toLowerCase()] ?? 'application/octet-stream'
  res.writeHead(200, { 'Content-Type': type })
  if (req.method === 'HEAD') {
    res.end()
    return
  }
  createReadStream(filePath).pipe(res)
}

const server = http.createServer((req, res) => {
  const pathname = new URL(req.url ?? '/', 'http://localhost').pathname

  if (pathname === '/api' || pathname.startsWith('/api/')) {
    proxy(req, res, API_TARGET)
    return
  }
  if (pathname === '/broadcasting' || pathname.startsWith('/broadcasting/')) {
    proxy(req, res, BROADCAST_TARGET)
    return
  }
  serveStatic(req, res, pathname)
})

server.listen(PORT, '0.0.0.0', () => {
  console.log(`frontend listening on :${PORT}, serving ${DIST}`)
})