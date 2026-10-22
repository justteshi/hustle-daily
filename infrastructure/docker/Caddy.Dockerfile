FROM node:24-alpine AS web-build
WORKDIR /web
COPY web/package.json web/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY web/ ./
RUN npm run build

FROM caddy:2
COPY infrastructure/Caddyfile /etc/caddy/Caddyfile
COPY --from=web-build /web/dist /srv/web
