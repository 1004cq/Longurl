# EEEE Long URL

[English](README.md) · [中文](README.zh-CN.md)

EEEE 是一个基于 **Go + Vue 3 + Three.js + MySQL** 的超长链接生成器。

**主程序已经完全移除 PHP。**

生成链接的 path 只包含字母 `e`，e 的数量就是唯一标识。

```
原始：  https://example.com/test
生成：  https://your-domain.example/eeeeeeeeee
```

## 当前架构

- Go：HTTP 服务、API、跳转、限流、MySQL、静态资源
- Vue 3：网页前端
- Three.js：3D 动画背景
- MySQL：链接、点击日志、限流
- Nginx / 宝塔：反向代理到 Go

Go 二进制会直接嵌入 `public/`，生产环境不再需要 PHP、PHP-FPM、Composer 或 Node.js。

## 环境要求

- Go 1.23+
- MySQL 5.7+ / 8.x
- 推荐 Nginx + HTTPS
- 不需要 PHP

## 安装

先在 MySQL 建好数据库和用户，然后编译并启动 Go：

```bash
go mod tidy
go build -o longurl .
./longurl
```

全新安装时，即使还没有数据库配置，Go 也可以启动，并会把网站引导到：

```
/install/
```

Go 安装器会测试 MySQL、导入 `sql/schema.sql`、以 0640 权限写入 `.env`、哈希管理员密码，并生成 API Token 和 Session 密钥。整个过程不需要 PHP。

安装完成后：

- `/admin/login` — 后台登录
- `/admin/` — Dashboard
- Links — 搜索、分页、编辑目标/过期时间、启用/禁用、删除、CSV 导出
- Analytics — 点击统计、近期访问、7 天趋势、点击日志 CSV
- `Settings` — Base URL、e 长度范围、限流、Token 轮换、管理员账号和密码修改

也可以复制 `.env.example` 后手动配置，不使用 Web 安装器。

健康检查：

```bash
curl http://127.0.0.1:8080/healthz
```

## 宝塔 / Nginx

现在整个站点直接反代给 Go：

```nginx
location / {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}

large_client_header_buffers 8 32k;
client_header_buffer_size 16k;
```

不再需要设置 PHP 运行目录，也不需要 PHP-FPM。

## 接口

- `GET /` — Vue 3 首页
- `GET /healthz` — 健康检查
- `GET /api/config` — 前端运行配置
- `POST /api/create` — 生成长链接
- `GET /eeee...` — 按 e 数量 302 跳转
- `GET/POST /install/` — Go 首次安装器
- `GET /admin/` — Go 管理后台
- 其他路径 — 404 页面

网页生成接口有 IP 限流。外部 API 可以使用：

```
Authorization: Bearer TOKEN
```

或：

```
X-API-Token: TOKEN
```

## 安全

- SQL 参数化
- 目标地址限制为公网 http/https
- 创建/API 限流
- API Token
- 管理员账号与密码使用 `.env` 配置，密码使用 bcrypt 哈希保存
- 修改管理员账号或密码后自动轮换 Session 密钥，使旧登录全部失效
- 不向客户端输出数据库异常
- 基础安全响应头
- 仓库不保存真实密码和 Token

## 其他语言版本

`implementations/` 仍保留 Python、Node/TypeScript、Rust、Java、C# 和旧 Go 兼容实现。

仓库根目录的 Go 程序是正式主版本。

## 协议

MIT。
