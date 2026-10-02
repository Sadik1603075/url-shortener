# LinkForge SPA — build with Vite, serve static with nginx.
# Build-time config is baked into the bundle (Vite inlines import.meta.env at build).
# Behind the k8s Ingress the SPA and API share an origin, so the API base defaults to
# the relative "/api/v1"; override per-env with build args.

# --- Stage 1: build -------------------------------------------------------
FROM node:20-alpine AS build
WORKDIR /app

ARG VITE_API_BASE_URL=/api/v1
ARG VITE_APP_NAME=LinkForge
ARG VITE_SHORT_URL_BASE=

COPY package*.json ./
RUN npm ci

COPY . .

# Write .env.production so Vite inlines these (it wins over .env in prod builds).
RUN printf 'VITE_API_BASE_URL=%s\nVITE_APP_NAME=%s\nVITE_SHORT_URL_BASE=%s\n' \
      "$VITE_API_BASE_URL" "$VITE_APP_NAME" "$VITE_SHORT_URL_BASE" > .env.production \
  && npm run build

# --- Stage 2: serve -------------------------------------------------------
FROM nginx:1.27-alpine AS serve
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
