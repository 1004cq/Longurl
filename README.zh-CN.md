# EEEE Long URL

[English](README.md) · [中文](README.zh-CN.md)

这是一个「超长链接生成器」，不是短链接。

用户贴一个真实 URL，系统生成一条 path **只有字母 `e`** 的长链接。  
唯一标识是 **e 的个数**（`e_length`），不用随机字母数字混合码。

```
原始：  https://example.com/test
生成：  https://your-domain.example/eeeeeeeeee
```

上面 path 有 10 个 `e`，数据库对应 `e_length = 10`。

本仓库不包含任何私人部署信息。请勿把真实域名、服务器 IP、数据库账密、API Token、管理员密码写进 README 或提交到 Git。

---

## 工作原理

1. 用户选一个希望长度，例如 100。
2. 系统在 100 附近找还没被使用的 `e_length`。
3. 多人同时生成时，优先在目标附近随机抽取空位，避免大家都挤在 100、101、102。
4. 近处满了就扩大搜索半径；再不行就从目标往上找下一个空位。
5. `e_length` 有唯一索引。两个请求抽到同一个数，后写入的会被拒绝并自动重抽。
6. 访问 `/` + 一串 `e` 时，用字符串长度查表。命中且启用、未过期 → `302` 跳目标，并记一条点击日志。
7. path 里出现任何非 `e` 字符，或找不到记录 → 404。

长度范围默认 8–5000，可在服务器配置里改。首页用滑块选长度，下方仍有 50 / 100 / 200 / 500 / 1000 / 2000 刻度和数字框。

---

## 功能清单

### 前台

- 输入目标 URL，缺少协议时自动补 `https://`
- 滑块 + 刻度 + 手输长度
- 实时预览（域名 + 若干个 e）
- 生成后可复制、新窗口打开
- 中英文切换
- 手机适配；超长 e 串不会撑宽页面

### 跳转

- 只识别纯 `e` path
- 302 跳转，记录 IP、UA、Referer、URI、时间
- 禁用或过期的链接走 404

### 后台 `/admin/`

- Dashboard：链接数、总点击、今日点击、近期趋势
- Links：搜索、分页、编辑目标/过期、启用、禁用、删除
- Analytics：访问日志、7/30 天、Top Links、CSV 导出
- API / Settings：说明；轮换 Token 或密码只改服务器上的配置文件

### API

`POST /api/create`，需 Token。

### 安装器

`/install/` 填库信息和站点公网地址，安装完写锁。

---

## 环境要求

| 项目 | 要求 |
|---|---|
| 系统 | Ubuntu + Nginx（宝塔可用） |
| PHP | 8.3 / 8.4 / 8.5，`declare(strict_types=1)` |
| 扩展 | `pdo` `pdo_mysql` `mbstring` `openssl` `json` `session` `filter` |
| 数据库 | MySQL 5.7+ 或 8.x，InnoDB，utf8mb4 |
| 不要 | Node.js、Composer |

HTTPS 用的是站点 SSL证书（Let's Encrypt 等），不是 SSH 登录密钥。两者不能混用。

---

## 目录

```
.
├── config/
│   └── local.php.example    #范例；真实 local.php 由安装器生成，已 gitignore
├── public/                 #网站运行目录
│   ├── index.php             #首页 / 跳转 / API
│   ├── admin/                #后台入口
│   ├── install/              #安装器
│   └── assets/               #CSS / JS
├── src/                    #业务与视图
├── sql/schema.sql
├── nginx/rewrite.conf
├── storage/                #安装锁
├── LICENSE
├── README.md
└── README.zh-CN.md
```

建议落盘（把 `YOUR_DOMAIN` 换成你自己的目录名，不要写回仓库）：

```
/www/wwwroot/YOUR_DOMAIN/e/          #项目根
/www/wwwroot/YOUR_DOMAIN/e/public    #宝塔「运行目录」
```

`config/`、`src/` 必须在 Web 根之外。

---

## 不要提交的内容

- `config/local.php`
- `storage/installed.lock`
- 任何 `.env`、数据库账号密码、API Token、证书私钥
- 真实站点域名、面板端口、公网 IP

仓库里只保留 `config/local.php.example`。

---

## 安装

### 1. 下载代码

```bash
cd /www/wwwroot/YOUR_DOMAIN/e
git clone https://github.com/1004cq/Longurl.git .
```

或下载 ZIP 解压到同目录，确保能看到 `public/` 与 `src/`。

更新已部署的站：

```bash
cd /www/wwwroot/YOUR_DOMAIN/e
git pull
```

不要把服务器上已生成的 `config/local.php` 盖掉。

### 2. 建库

```sql
CREATE DATABASE eeee_longurl CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'eeee_user'@'localhost' IDENTIFIED BY 'CHANGE_ME';
GRANT ALL PRIVILEGES ON eeee_longurl.* TO 'eeee_user'@'localhost';
FLUSH PRIVILEGES;
```

`CHANGE_ME` 换成你自己的密码，不要写进 Git。表由安装器导入 `sql/schema.sql`。

| 表 | 用途 |
|---|---|
| `links` | e 个数、目标 URL、点击、启用、过期 |
| `click_logs` | 每次跳转的 IP / UA / Referer / URI |
| `rate_limits` | 生成、API、登录限速 |

### 3. 权限

宝塔 PHP 用户常见为 `www`：

```bash
chown -R www:www /www/wwwroot/YOUR_DOMAIN/e
chmod 750 /www/wwwroot/YOUR_DOMAIN/e/config
chmod 750 /www/wwwroot/YOUR_DOMAIN/e/storage
```

PHP 必须能写：

- `config/local.php`
- `storage/installed.lock`

### 4. 宝塔站点

1. 添加站点。
2. **网站目录**指项目根 `.../e`。
3. **运行目录** 填 `/public`（界面里常显示成 `/public`）。
4. PHP 选 8.x。
5. 伪静态粘贴 `nginx/rewrite.conf`。
6. 把 `fastcgi_pass` 改成本机实际 sock（和当前站点配置里的一致）。
7. 重载 Nginx。

超长 path 需要较大 header 缓冲，示例配置已包含：

```nginx
large_client_header_buffers 8 32k;
client_header_buffer_size 16k;
```

### 5. 站点 HTTPS

浏览器报 `ERR_SSL_PROTOCOL_ERROR` 时，一般是 443 没有站点证书，或 `listen 443` 没加 `ssl`。

1. 先用 `http://YOUR_DOMAIN/install/` 确认程序能打开。
2. 宝塔 → 站点 → SSL → 申请 Let's Encrypt。
3. 证书成功前不要开「强制 HTTPS」。
4. 云安全组放行 80 和 443。
5. SSH 密钥只用来登录服务器，不能当作网站证书。

### 6. Web 安装

打开 `https://YOUR_DOMAIN/install/`（证书还没好就用 http）。

| 字段 | 说明 |
|---|---|
| DB host / port | 常见 `127.0.0.1` 与 `3306` |
| DB name / user / password | 上一步建的库，别发到公开地方 |
| Website URL | 对外根地址，如 `https://your-domain.example`，无末尾 `/` |
| Admin password | 只存 hash |

安装器会测连接、导表、写配置、生成 API Token、锁定安装器。然后打开 `/admin/`。

---

## 配置说明

`config/local.php` 结构见 `config/local.php.example`，大致为：

- `db.dsn` / `db.user` / `db.pass` — 数据库
- `app.base_url` — 生成长链接时用的公网根
- `app.min_length` / `app.max_length` — 默认 8 与 5000
- `admin.password_hash` — 后台密码 hash
- `admin.api_token` — API 密钥
- `rate.*` — 每分钟限次

轮换 Token：在服务器上改 `api_token`，不要把新值贴到仓库。

---

## API

```
POST /api/create
```

任意一种验证：

- `Authorization: Bearer YOUR_TOKEN`
- `X-API-Token: YOUR_TOKEN`
- 表单字段 `token`

参数：`url`（目标）、`length`（希望的 e 个数）。

成功示例：

```json
{
  "success": true,
  "url": "https://your-domain.example/eeeeeeeeee",
  "length": 10,
  "target": "https://example.com"
}
```

失败时 `success` 为 false，状态码可能 400 / 401 / 429。`length` 是实际写入的 e 个数，可能和请求值略有差异。

---

## 安全

- PDO 预编译
- 输出转义
- 只允许公网 `http`/`https`，拒绝 `javascript:` `data:` `file:`、localhost、私网 IP
- CSRF + Session
- Cookie：httponly，HTTPS 下 secure，SameSite=Lax
- 生成 / API / 登录限速
- 生产环境不打详细错误，也不会把库密码输出到页面
- 管理密码仅 hash

---

## 排错

| 现象 | 处理 |
|---|---|
| `SQLSTATE[HY093]` | 已修复：插入语句里不能重复使用同一个 PDO 占位符。`git pull` 后重试 |
| 一直跳 `/install/` | 缺配置或锁文件，或目录不可写 |
| 安装失败 | 库账号不对，或 `config`/`storage` 不可写 |
| 500 | 看 PHP 日志，生产环境不要开 `display_errors` |
| 400 / 414 | 加大 Nginx header buffer |
| 404 跳转 | path 不是纯 e，或该长度未生成/已禁用 |
| 登录失败 | 密码错或 1 分钟超 8 次 |
| 502 | `fastcgi_pass` 与 PHP 版本不一致 |
| 伪静态无效 | 运行目录不是 `public` |
| `ERR_SSL_PROTOCOL_ERROR` | 站点证书未部署，见上文 HTTPS |

---

## 协议

MIT，见 [LICENSE](LICENSE)。
