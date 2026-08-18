# syntax=docker/dockerfile:1

# --- Build stage: compile the Vite bundle --------------------------------
FROM node:22-alpine AS build
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

# Reverb connection settings are baked into the bundle at build time
# (import.meta.env.VITE_REVERB_* in src/lib/echo.ts). Compose passes these
# through as build args; defaults match the backend's Reverb config.
ARG VITE_REVERB_APP_KEY=reverb-app-key-placeholder
ARG VITE_REVERB_HOST=localhost
ARG VITE_REVERB_PORT=8080
ARG VITE_REVERB_SCHEME=http
ENV VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME

RUN npm run build

# --- Runtime stage: pm2 static server + reverse proxy --------------------
FROM node:22-alpine
WORKDIR /app

RUN npm i -g pm2

COPY --from=build /app/dist ./dist
COPY ecosystem.config.cjs server.mjs ./

EXPOSE 5173

CMD ["pm2-runtime", "start", "ecosystem.config.cjs"]