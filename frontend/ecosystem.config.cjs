module.exports = {
  apps: [
    {
      name: 'frontend',
      script: 'server.mjs',
      cwd: __dirname,
      instances: 1,
      exec_mode: 'fork',
      autorestart: true,
      max_restarts: 10,
      watch: false,
      env: {
        NODE_ENV: 'production',
        PORT: '5173',
        // Inside the compose network the backend service is reachable as
        // `backend`. Override these when running the server standalone.
        API_TARGET: process.env.API_TARGET || 'http://backend:8000',
        BROADCAST_TARGET: process.env.BROADCAST_TARGET || 'http://backend:8000',
      },
    },
  ],
}