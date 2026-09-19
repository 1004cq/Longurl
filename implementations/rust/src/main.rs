use axum::{
    extract::{Path, State},
    http::{HeaderMap, StatusCode},
    response::{IntoResponse, Redirect},
    routing::{get, post},
    Form, Json, Router,
};
use chrono::{Local, NaiveDateTime};
use rand::Rng;
use serde::{Deserialize, Serialize};
use sqlx::{mysql::MySqlPoolOptions, MySqlPool, Row};
use std::{collections::HashSet, env, net::IpAddr, sync::Arc};
use url::Url;

#[derive(Clone)]
struct AppState {
    db: MySqlPool,
    base_url: String,
    api_token: String,
    min_len: i32,
    max_len: i32,
}

#[derive(Deserialize)]
struct CreateForm {
    url: String,
    length: Option<i32>,
    token: Option<String>,
}

#[derive(Serialize)]
struct CreateResponse {
    success: bool,
    id: u64,
    length: i32,
    target: String,
    url: String,
}

#[tokio::main]
async fn main() {
    let dsn = format!(
        "mysql://{}:{}@{}:{}/{}",
        env_var("DB_USER", "eeee_user"),
        env::var("DB_PASS").unwrap_or_default(),
        env_var("DB_HOST", "127.0.0.1"),
        env_var("DB_PORT", "3306"),
        env_var("DB_NAME", "eeee_longurl")
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
        .route("/healthz", get(healthz))
        .route("/api/create", post(create))
        .route("/{path}", get(resolve))
        .with_state(state);
    let addr = format!("0.0.0.0:{}", env_var("PORT", "8080"));
    let listener = tokio::net::TcpListener::bind(&addr).await.unwrap();
    println!("Longurl Rust listening on {addr}");
    axum::serve(listener, app).await.unwrap();
}

async fn home() -> &'static str { "EEEE Long URL — Rust\n" }

async fn healthz(State(state): State<Arc<AppState>>) -> impl IntoResponse {
    match sqlx::query("SELECT 1").execute(&state.db).await {
        Ok(_) => (StatusCode::OK, Json(serde_json::json!({"ok": true, "db": true}))).into_response(),
        Err(_) => (StatusCode::SERVICE_UNAVAILABLE, Json(serde_json::json!({"ok": false, "db": false}))).into_response(),
    }
}

async fn create(State(state): State<Arc<AppState>>, headers: HeaderMap, Form(form): Form<CreateForm>) -> impl IntoResponse {
    if !authorized(&headers, form.token.as_deref(), &state.api_token) {
        return (StatusCode::UNAUTHORIZED, "Unauthorized").into_response();
    }
    let target = normalize_url(&form.url);
    if !allowed_url(&target) { return (StatusCode::BAD_REQUEST, "Invalid URL").into_response(); }
    let wanted = form.length.unwrap_or(100);
    if wanted < state.min_len || wanted > state.max_len {
        return (StatusCode::BAD_REQUEST, "Invalid length").into_response();
    }
    let mut tried = HashSet::new();
    for _ in 0..24 {
        let Some(candidate) = pick_nearby(&state, wanted, &tried).await else { break; };
        tried.insert(candidate);
        let result = sqlx::query(
            "INSERT INTO links (e_length,target_url,clicks,enabled,created_at,updated_at) VALUES (?,?,0,1,NOW(),NOW())"
        ).bind(candidate).bind(&target).execute(&state.db).await;
        match result {
            Ok(done) => {
                return Json(CreateResponse {
                    success: true,
                    id: done.last_insert_id(),
                    length: candidate,
                    target: target.clone(),
                    url: format!("{}/{}", state.base_url, "e".repeat(candidate as usize)),
                }).into_response();
            }
            Err(sqlx::Error::Database(db_err)) if db_err.code().as_deref() == Some("1062") => continue,
            Err(_) => return (StatusCode::INTERNAL_SERVER_ERROR, "Database error").into_response(),
        }
    }
    (StatusCode::CONFLICT, "No free e-length").into_response()
}

async fn pick_nearby(state: &AppState, wanted: i32, tried: &HashSet<i32>) -> Option<i32> {
    for radius in [0, 4, 12, 32, 64, 128, 256] {
        let low = (wanted - radius).max(state.min_len);
        let high = (wanted + radius).min(state.max_len);
        let rows = sqlx::query("SELECT e_length FROM links WHERE e_length>=? AND e_length<=?")
            .bind(low).bind(high).fetch_all(&state.db).await.ok()?;
        let used: HashSet<i32> = rows.iter().filter_map(|r| r.try_get::<i32,_>("e_length").ok()).collect();
        let mut free: Vec<i32> = (low..=high).filter(|n| !used.contains(n) && !tried.contains(n)).collect();
        if free.is_empty() { continue; }
        free.sort_by_key(|n| ((n - wanted).abs(), *n));
        let pool = free.len().min((radius + 1).max(4) as usize);
        let idx = rand::thread_rng().gen_range(0..pool);
        return Some(free[idx]);
    }
    (wanted..=state.max_len).find(|n| !tried.contains(n))
}

async fn resolve(State(state): State<Arc<AppState>>, Path(path): Path<String>, headers: HeaderMap) -> impl IntoResponse {
    if path.is_empty() || !path.chars().all(|c| c == 'e') { return StatusCode::NOT_FOUND.into_response(); }
    let row = sqlx::query("SELECT id,target_url,enabled,expires_at FROM links WHERE e_length=? LIMIT 1")
        .bind(path.len() as i32).fetch_optional(&state.db).await;
    let Ok(Some(row)) = row else { return StatusCode::NOT_FOUND.into_response(); };
    let enabled: i8 = row.try_get("enabled").unwrap_or(0);
    let target: String = row.try_get("target_url").unwrap_or_default();
    let id: u64 = row.try_get("id").unwrap_or(0);
    let expires: Option<NaiveDateTime> = row.try_get("expires_at").ok().flatten();
    if enabled == 0 || expires.is_some_and(|t| t < Local::now().naive_local()) {
        return StatusCode::NOT_FOUND.into_response();
    }
    let ip = client_ip(&headers);
    let _ = sqlx::query("UPDATE links SET clicks=clicks+1,updated_at=NOW() WHERE id=?").bind(id).execute(&state.db).await;
    let _ = sqlx::query("INSERT INTO click_logs (link_id,clicked_at,ip,user_agent,referer,request_uri) VALUES (?,NOW(),?,?,?,?)")
        .bind(id).bind(ip).bind(truncate(&header(&headers, "user-agent"), 512))
        .bind(truncate(&header(&headers, "referer"), 1024)).bind(truncate(&format!("/{path}"), 2048))
        .execute(&state.db).await;
    Redirect::temporary(&target).into_response()
}

fn env_var(k: &str, d: &str) -> String { env::var(k).unwrap_or_else(|_| d.to_string()) }
fn normalize_url(v: &str) -> String {
    let s = v.trim();
    if s.is_empty() { String::new() } else if s.contains("://") { s.to_string() } else { format!("https://{s}") }
}
fn allowed_url(v: &str) -> bool {
    let Ok(u) = Url::parse(v) else { return false; };
    if u.scheme() != "http" && u.scheme() != "https" { return false; }
    let Some(host) = u.host_str() else { return false; };
    let host = host.to_lowercase();
    if host == "localhost" || host.ends_with(".local") { return false; }
    if let Ok(ip) = host.parse::<IpAddr>() {
        if ip.is_loopback() || ip.is_unspecified() { return false; }
        if let IpAddr::V4(v4) = ip { if v4.is_private() || v4.is_link_local() { return false; } }
    }
    true
}
fn authorized(headers: &HeaderMap, form_token: Option<&str>, token: &str) -> bool {
    if token.is_empty() { return false; }
    if let Some(v) = headers.get("authorization").and_then(|v| v.to_str().ok()) {
        if let Some(v) = v.strip_prefix("Bearer ").or_else(|| v.strip_prefix("bearer ")) {
            if v.trim() == token { return true; }
        }
    }
    headers.get("x-api-token").and_then(|v| v.to_str().ok()) == Some(token) || form_token == Some(token)
}
fn header(headers: &HeaderMap, name: &str) -> String {
    headers.get(name).and_then(|v| v.to_str().ok()).unwrap_or("").to_string()
}
fn client_ip(headers: &HeaderMap) -> String {
    let xff = header(headers, "x-forwarded-for");
    if !xff.is_empty() { return xff.split(',').next().unwrap_or("").trim().to_string(); }
    header(headers, "x-real-ip")
}
fn truncate(s: &str, n: usize) -> String { s.chars().take(n).collect() }
