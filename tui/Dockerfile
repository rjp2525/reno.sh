# syntax=docker/dockerfile:1

# ---- build -------------------------------------------------------------
FROM golang:1.27.3-alpine AS build
WORKDIR /src

ENV CGO_ENABLED=0 GOFLAGS=-mod=readonly GOTOOLCHAIN=local
COPY go.mod go.sum ./
RUN go mod download && go mod verify

COPY . .
ARG VERSION=docker
RUN go build -trimpath -ldflags "-s -w -X main.version=${VERSION}" -o /out/reno-tui .

# ---- runtime -----------------------------------------------------------
FROM alpine:3.22

RUN apk add --no-cache ca-certificates \
    && addgroup -S -g 10001 reno \
    && adduser -S -D -H -u 10001 -G reno -s /sbin/nologin reno \
    && mkdir -p /data \
    && chown reno:reno /data \
    && chmod 0700 /data

COPY --from=build /out/reno-tui /usr/local/bin/reno-tui

ENV RENO_API_URL=https://reno.sh \
    RENO_SITE_URL=https://reno.sh \
    RENO_SSH_PORT=2222 \
    RENO_HOST_KEY_PATH=/data/host_ed25519

USER reno:reno
VOLUME ["/data"]
EXPOSE 2222

HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD nc -z 127.0.0.1 "${RENO_SSH_PORT}" || exit 1

STOPSIGNAL SIGTERM
ENTRYPOINT ["/usr/local/bin/reno-tui"]
