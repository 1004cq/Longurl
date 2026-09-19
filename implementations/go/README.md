# Go redirect sidecar

PHP remains the website. This process only answers `GET /e+` (and `/healthz`, `/api/create`).

## Run

Same MySQL as PHP. Placeholders only:

```bash
cd implementations/go
go build -o longurl-go .

export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_NAME=eeee_longurl
export DB_USER=eeee_user
export DB_PASS=CHANGE_ME
export BASE_URL=https://your-domain.example
export PORT=8081
./longurl-go
```

Check: `curl -s http://127.0.0.1:8081/healthz`

Then paste `nginx/rewrite.conf` into BaoTa 伪静态 and reload Nginx.

Do not send `/` or `/admin` to Go. Generation stays in PHP so CSRF and the slider keep working.
