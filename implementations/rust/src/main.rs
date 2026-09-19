use axum::{
    extract::{Path, State},
    http::{HeaderMap, StatusCode},
    response::{IntoResponse, Redirect},
    routing::{get, post},
    Form, Json, Router,
};
use chrono::{Local, NaiveDateTime};
use serde::{Deserialize, Serialize};
use sqlx::{mysql::MySqlPoolOptions, MySqlPool, Row};
use std::{env, net::IpAddr, sync::Arc};
use url::Url;

#[derive(Clone)]
struct AppState {
    db: MySqlPool,
    base_url: String,
    api_token: String,
    min_len: usize,
    max_len: usize,
}

#[derive(Deserialize)]
struct CreateForm {
    url: String,
    length: Option<usize>,
}

#[derive(Serialize)]
struct CreateResponse {
    success: bool,
    id: u64,
    length: usize,
    target: String,
    url: String,
}

#[tokio::main]
async fn main() {
    let dsn = format!(
        "mysql://{}:{}@{}:{}/{}",
        env_var("DB_USER", "admin"),
        env::var("DB_PASS").unwrap_or_default(),
        env_var("DB_HOST", "127.0.0.1"),
        env_var("DB_PORT", "3306"),
        env_var("DB_NAME", "admin")
    );
    let db = MySqlPoolOptions::new().max_connections(10).connect(&dsn).await.unwrap();

    let state = Arc::new(AppState {
        db,
        base_url: env_var("BASE_URL", "http://127.0.0.1:8080").trim_end_matches('/').to_string(),
        api_token: env::var("API_TOKEN").unwrap_or_default(),
        min_len: env_var("MIN_LENGTH", "8").parse().unwrap_or(8),
        max_len: env_var("MAX_LENGTH", "5000").parse().unwrap_or(5000),
    });

    let app = Router::new()
        .route("/", get(home))
        .route("/api/create", post(create))
        .route("/{path}", get(resolve))
        .with_state(state);

    let addr = format!("0.0.0.0:{}", env_var("PORT", "8080"));
    let listener = tokio::net::TcpListener::bind(&addr).await.unwrap();
    println!("Longurl Rust listening on {addr}");
    axum::serve(listener, app).await.unwrap();
}

async fn home() -> &'static str {
    "EEEE Long URL — Rust\n"
}

async fn create(
    State(state): State<Arc<AppState>>,
    headers: HeaderMap,
    Form(form): Form<CreateForm>,
) -> impl IntoResponse {
    if !authorized(&headers, &state.api_token) {
        return (StatusCode::UNAUTHORIZED, "Unauthorized").into_response();
    }

    let target = normalize_url(&form.url);
    if !allowed_url(&target) {
        return (StatusCode::BAD_REQUEST, "Invalid URL").into_response();
    }

    let wanted = form.length.unwrap_or(50);
    if wanted < state.min_len || wanted > state.max_len {
        return (StatusCode::BAD_REQUEST, "Invalid length").into_response();
    }

    for candidate in wanted..=state.max_len {
        let result = sqlx::query(
            "INSERT INTO links (e_length,target_url,clicks,enabled,created_at,updated_at) VALUES (?,?,0,1,NOW(),NOW())"
        )
        .bind(candidate as u32)
        .bind(&target)
        .execute(&state.db)
        .await;

        match result {
            Ok(done) => {
                let response = CreateResponse {
                    success: true,
                    id: done.last_insert_id(),
                    length: candidate,
                    target: target.clone(),
                    url: format!("{}/{}", state.base_url, "e".repeat(candidate)),
                };
                return Json(response).into_response();
            }
            Err(sqlx::Error::Database(db_err)) if db_err.code().as_deref() == Some("1062") => continue,
            Err(_) => return (StatusCode::INTERNAL_SERVER_ERROR, "Database error").into_response(),
        }
    }

    (StatusCode::CONFLICT, "No free e-length").into_response()
}

async fn resolve(
    State(state): State<Arc<AppState>>,
    Path(path): Path<String>,
    headers: HeaderMap,
) -> impl IntoResponse {
    if path.is_empty() || !path.chars().all(|c| c == 'e') {
        return StatusCode::NOT_FOUND.into_response();
    }

    let row = sqlx::query(
        "SELECT id,target_url,enabled,expires_at FROM links WHERE e_length=? LIMIT 1"
    )
    .bind(path.len() as u32)
    .fetch_optional(&state.db)
    .await;

    let Ok(Some(row)) = row else { return StatusCode::NOT_FOUND.into_response(); };

    let enabled: i8 = row.try_get("enabled").unwrap_or(0);
    let target: String = row.try_get("target_url").unwrap_or_default();
    let id: u64 = row.try_get("id").unwrap_or(0);
    let expires: Option<NaiveDateTime> = row.try_get("expires_at").ok().flatten();

    if enabled == 0 || expires.is_some_and(|t| t < Local::now().naive_local()) {
        return StatusCode::NOT_FOUND.into_response();
    }

    let ua = header(&headers, "user-agent");
    let referer = header(&headers, "referer");
    let mut tx = match state.db.begin().await {
        Ok(tx) => tx,
        Err(_) => return Redirect::temporary(&target).into_response(),
    };
    let _ = sqlx::query("UPDATE links SET clicks=clicks+1,updated_at=NOW() WHERE id=?")
        .bind(id).execute(&mut *tx).await;
    let _ = sqlx::query(
        "INSERT INTO click_logs (link_id,clicked_at,ip,user_agent,referer,request_uri) VALUES (?,NOW(),?,?,?,?)"
    )
    .bind(id)
    .bind("")
    .bind(truncate(&ua, 512))
    .bind(truncate(&referer, 1024))
    .bind(truncate(&format!("/{path}"), 2048))
    .execute(&mut *tx)
    .await;
    let _ = tx.commit().await;

    Redirect::temporary(&target).into_response()
}

fn env_var(k: &str, d: &str) -> String {
    env::var(k).unwrap_or_else(|_| d.to_string())
}

fn normalize_url(v: &str) -> String {
    let s = v.trim();
    if s.is_empty() { return String::new(); }
    if s.contains("://") { s.to_string() } else { format!("https://{s}") }
}

fn allowed_url(v: &str) -> bool {
    let Ok(u) = Url::parse(v) else { return false; };
    if u.scheme() != "http" && u.scheme() != "https" { return false; }
    let Some(host) = u.host_str() else { return false; };
    let host = host.to_lowercase();
    if host == "localhost" || host.ends_with(".local") { return false; }
    if let Ok(ip) = host.parse::<IpAddr>() {
        if ip.is_loopback() || ip.is_unspecified() { return false; }
        if let IpAddr::V4(v4) = ip {
            if v4.is_private() || v4.is_link_local() { return false; }
        }
    }
    true
}

fn authorized(headers: &HeaderMap, token: &str) -> bool {
    if token.is_empty() { return false; }
    if let Some(v) = headers.get("authorization").and_then(|v| v.to_str().ok()) {
        if let Some(v) = v.strip_prefix("Bearer ").or_else(|| v.strip_prefix("bearer ")) {
            if v.trim() == token { return true; }
        }
    }
    headers.get("x-api-token").and_then(|v| v.to_str().ok()) == Some(token)
}

fn header(headers: &HeaderMap, name: &str) -> String {
    headers.get(name).and_then(|v| v.to_str().ok()).unwrap_or("").to_string()
}

fn truncate(s: &str, n: usize) -> String {
    s.chars().take(n).collect()
}
