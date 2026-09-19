FROM golang:1.23-alpine AS build
WORKDIR /src
COPY go.mod ./
RUN go mod download
COPY . .
RUN CGO_ENABLED=0 go build -trimpath -ldflags="-s -w" -o /out/longurl .

FROM alpine:3.21
RUN adduser -D -H -s /sbin/nologin app && mkdir -p /app && chown app:app /app
WORKDIR /app
USER app
COPY --from=build /out/longurl /usr/local/bin/longurl
EXPOSE 8080
VOLUME ["/app"]
ENTRYPOINT ["/usr/local/bin/longurl"]
