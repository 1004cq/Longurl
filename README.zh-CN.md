# EEEE Long URL

[English](README.md) · [中文](README.zh-CN.md)

用更长的链接替换真实 URL。这不是短链接。

生成出来的 path **只能全是字母 `e`**。用「有多少个 e」作为唯一 ID，不用随机字符串。

```
原始：  https://example.com/test
生成：  https://your-domain.example/eeeeeeeeee
```

上面这条的 path 有 10 个 `e`。数据库里 `e_length = 10` 就对应这条记录。  
如果 10 已被占用，系统会自动试 11、12、…直到找到空位。

访问这个全 e path 时：

- 找到且已启用 → `302` 跳到目标 URL，并记录点击
- 找不到 / 已禁用 / 已过期 → 自定义 404（This e is lost.）
- path 里出现任何非 `e` 的字符 → 也是 404

---

## 功能

- 首页生成长链接：输入 URL、选择或手填长度（8–5000，快捷 50 / 100 / 200 / 500 / 1000 / 2000）
- 实时预览最终链接长度
- 复制 / 打开；Enter 提交；Loading 与错误提示
- URL 自动补 `https://`；只允许公网 `http` / `https`
- 后台 V2：中英文切换、Dashboard、搜索、分页、编辑目标地址/过期时间、启用/禁用/删除、访问日志、API 与设置
- 统计：7 天 / 30 天流量、Top Links、最近访问、CSV 导出\n- API：`POST /api/create`，使用请求头 Token
- Web 安装器：`/install/`，安装完锁定

---

## 技术栈

| 项目 | 要求 |
|---|---|
| 语言 | PHP 8.3 / 8.4 / 8.5（声明 `strict_types`） |
| 数据库 | MySQL 5.7+ / 8.x，InnoDB，utf8mb4 |
| 网站 | Nginx（宝塔可直接用） |
| 依赖 | 不要 Node.js，不要 Composer |
| 扩展 | `pdo` `pdo_mysql` `mbstring` `openssl` `json` `session` `filter` |

---

## 目录说明

```
.
├── config/
│   └── local.php.example    # 范例；真实 local.php 由安装器生成，不入仓
├── public/                 # 站点运行目录（Nginx root）
│   ├── index.php             # 唯一入口：首页 / 跳转 / API
│   ├── admin/                # 后台
│   ├── install/              # 安装器
│   └── assets/               # CSS / JS
├── src/                    # 业务代码
│   ├── bootstrap.php
│   ├── Database.php
│   ├── LinkService.php
│   ├── Auth.php
│   ├── RateLimiter.php
│   ├── Helpers.php
│   └── views/
├── sql/schema.sql          # 建表
├── nginx/rewrite.conf      # 宝塔/伪静态
├── storage/                # 安装锁 installed.lock
├── LICENSE                 # MIT
├── README.md               # English
└── README.zh-CN.md         # 中文
```

建议服务器落盘路径：

```
/www/wwwroot/YOUR_DOMAIN/e/          # 项目根
/www/wwwroot/YOUR_DOMAIN/e/public    # 网站运行目录
```

`config/` 和 `src/` 不在 Web 根目录内。不要把密码写进 `public/`。

---

## 安装步骤

### 1. 上传代码

把仓库内容放到服务器项目目录，例如 `/www/wwwroot/YOUR_DOMAIN/e/`。

### 2. 建 MySQL 库

```sql
CREATE DATABASE eeee_longurl CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'eeee_user'@'localhost' IDENTIFIED BY 'CHANGE_ME';
GRANT ALL PRIVILEGES ON eeee_longurl.* TO 'eeee_user'@'localhost';
FLUSH PRIVILEGES;
```

把 `CHANGE_ME` 换成自己的密码。表结构由安装器自动导入 `sql/schema.sql`，一般不用手动导。

表：

- `links` — e 长度、目标 URL、点击数、启用状态、时间
- `click_logs` — IP、UA、Referer、URI、时间
- `rate_limits` — 接口 / 登录限速

### 3. 权限

宝塔运行用户一般是 `www`：

```bash
chown -R www:www /www/wwwroot/YOUR_DOMAIN/e
chmod 750 /www/wwwroot/YOUR_DOMAIN/e/config
chmod 750 /www/wwwroot/YOUR_DOMAIN/e/storage
```

PHP 必须能写：

- `config/local.php`（安装时生成）
- `storage/installed.lock`（安装完锁定）

### 4. 宝塔站点

1. 新建站点，**运行目录**选 `.../e/public`，不是项目根
2. PHP 选 8.x
3. 伪静态 / Nginx 配置粘贴 `nginx/rewrite.conf`
4. 重载 Nginx

`nginx/rewrite.conf` 里的 `fastcgi_pass` 要改成你机器实际 PHP socket。宝塔里常见：

```
unix:/tmp/php-cgi-85.sock
unix:/tmp/php-cgi-84.sock
unix:/tmp/php-cgi-80.sock
```

长 path（1000+ 个 e）需要更大的 header 缓冲，规则里已写：

```nginx
large_client_header_buffers 8 32k;
client_header_buffer_size 16k;
```

### 5. Web 安装

浏览器打开：

```
https://YOUR_DOMAIN/install/
```

填：

| 字段 | 说明 |
|---|---|
| DB host / port | 一般 `127.0.0.1` 和 `3306` |
| DB name / user / password | 上一步建的库 |
| Website URL | 对外访问的完整根地址，带 `https://`，不要末尾斜杠 |
| Admin password | 后台登录密码，会存 `password_hash` |

安装器会：

1. 测试数据库
2. 导入表
3. 写 `config/local.php`
4. 生成 API Token
5. 写 `storage/installed.lock` 锁死安装器

安装完后打开 `/admin/` 登录。

API Token 只在服务器的 `config/local.php` 里，**不要提交到 Git**。

---

## API

```
POST /api/create
```

验证任意一种：

- Header：`Authorization: Bearer YOUR_TOKEN`
- Header：`X-API-Token: YOUR_TOKEN`
- 表单字段：`token`

参数：

| 字段 | 说明 |
|---|---|
| `url` | 目标地址 |
| `length` | 希望的 e 个数 |

成功：

```json
{
  "success": true,
  "url": "https://your-domain.example/eeeeeeeeee",
  "length": 10,
  "target": "https://example.com"
}
```

失败时 `success` 为 `false`，HTTP 状态可能是 400 / 401 / 429。

---

## 后台

地址：`/admin/`

- Dashboard：链接总数、总点击、今日点击、最近生成、最近访问
- Links：搜索、启用、禁用、删除
- Analytics：IP、UA、Referer、URI、时间
- API / Settings：说明；轮换 Token 或密码请直接改服务器上的 `config/local.php`

---

## 安全

- PDO 预编译，不拼 SQL
- 页面输出 `htmlspecialchars`
- URL：`FILTER_VALIDATE_URL`，仅 `http`/`https`，拒绝 `javascript:` `data:` `file:` 以及私网 IP / localhost
- 表单 CSRF；Session 登录
- Cookie：httponly、HTTPS 下 secure、SameSite=Lax
- 生成接口 / API / 后台登录都有每分钟限速
- 生产环境关闭详细 PHP 错误；数据库异常不会把账密打到页面
- 管理密码只存 hash

---

## 常见问题

| 现象 | 处理 |
|---|---|
| 一直跳到 `/install/` | 缺 `config/local.php` 或 `storage/installed.lock`，检查写入权限 |
| 安装失败 | 库名/账号/密码不对，或 `config`、`storage` 不可写 |
| 页面 500 | 看 PHP 错误日志；别把 `display_errors` 开在生产 |
| 超长 path 报 400 / 414 | 加大 Nginx header buffer，见 `nginx/rewrite.conf` |
| 跳转 404 | path 必须纯 `e`；或这个长度还没生成/已禁用 |
| 后台登不进去 | 密码错，或同 IP 1 分钟超 8 次被限速 |
| PHP socket 报 502 | `rewrite.conf` 里的 `fastcgi_pass` 和宝塔 PHP 版本不一致 |
| 伪静态没生效 | 运行目录是否真的是 `public`；规则是否写进当前站点 Nginx |

---

## 开源协议

MIT License，见 [LICENSE](LICENSE)。
