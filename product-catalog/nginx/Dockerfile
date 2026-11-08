# syntax=docker/dockerfile:1

# ---------- Stage 1: build Angular application ----------
FROM node:22-alpine AS frontend

WORKDIR /app

COPY frontend/package*.json ./
RUN npm ci

COPY frontend/ .
RUN npm run build

# ---------- Stage 2: nginx runtime ----------
FROM nginx:1.27-alpine AS runtime

COPY nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=frontend /app/dist/frontend/browser /usr/share/nginx/html

EXPOSE 80
