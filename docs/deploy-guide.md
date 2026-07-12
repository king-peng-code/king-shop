# M16 — 部署与交付指南

> **模块：** M16 部署与交付  
> **适用环境：** 单机生产（Nginx + PHP-FPM + MySQL + Redis）  
> **技术栈：** PHP 8.4 · Laravel 12 · MySQL 8.0 · Redis 7.4 · React 18.3  
> **关联文档：** [单机架构说明](./deploy-single-server.md) · [Docker 测试规范](./superpowers/docker-testing.md)

---

## 1. 交付物清单

| 文件 | 说明 | 状态 |
|------|------|------|
| `docker-compose.prod.yml` | 生产 Compose（Nginx + PHP-FPM + MySQL + Redis） | ✅ 已有 |
| `scripts/deploy-prod.sh` | 一键部署脚本 | ✅ 已有 |
| `.env.prod.example` | Compose 层环境变量模板 | ✅ 已有 |
| `backend/.env.production.example` | Laravel 生产环境模板 | ✅ 已有 |
| `docs/deploy-single-server.md` | 架构与运维速查 | ✅ 已有 |
| `docs/deploy-guide.md` | 本文档（完整上线指引） | ✅ 本文 |
| `scripts/health-check.sh` | 部署后健康检查 | ✅ 已有 |
| `scripts/cron-scheduler.example` | Scheduler cron 模板 | ✅ 已有 |
| `frontend/.env.production.example` | 前端生产构建变量模板 | ✅ 已有 |

---

## 2. 上线前准备（甲方并行事项）

以下事项通常需要 **10–20 个工作日**，建议在开发第 1 周即启动：

| 事项 | 用途 | 建议时间 |
|------|------|----------|
| 域名 + ICP 备案 | 国内服务器对外访问 | 立即 |
| 云服务器 | 2C4G 起，Ubuntu 22.04+ | 第 2 周 |
| 微信开放平台 | App 支付、移动应用签名 | 立即 |
| 微信支付商户号 | 生产收款 | 第 1 周 |
| 微信公众号（服务号） | 找人代付 JSAPI + OAuth openid | 第 1 周 |
| 阿里云 OSS（可选） | 商品图片 CDN | 第 3 周 |

> **备案未完成时：** 可先用海外服务器 + IP 访问做演示；正式上线需备案域名 + HTTPS。

---

## 3. 系统架构

```
一台云服务器（仅开放 80/443）
┌────────────────────────────────────────────┐
│  nginx:80/443                               │
│  ├── /              → frontend 静态（管理端 + 代付 H5）│
│  ├── /api/*         → PHP-FPM（Laravel API） │
│  └── 支付回调        → /api/v1/payments/notify/* │
├────────────────────────────────────────────┤
│  backend (PHP 8.4 FPM)                      │
│  Session / Cache / Queue → Redis            │
├──────────────────┬─────────────────────────┤
│ mysql:8.0.40     │ redis:7.4               │
│ （Docker 内网）   │ （Docker 内网）          │
└──────────────────┴─────────────────────────┘
```

**与本地开发的区别：**

| 项目 | 本地 `docker-compose.yml` | 生产 `docker-compose.prod.yml` |
|------|--------------------------|-------------------------------|
| Web 服务 | `artisan serve :8000` | Nginx + PHP-FPM :80 |
| 代码 | bind mount 热更新 | 打进镜像 |
| MySQL/Redis | 暴露 3306/6379 | 仅内网，不映射公网 |
| 管理端 | Vite dev server :5173 | `frontend/dist` 静态托管 |

---

## 4. 服务器初始化

### 4.1 系统要求

- **OS：** Ubuntu 22.04+ / CentOS 8+
- **配置：** 2C4G 起（建议 4C8G）
- **软件：** Docker 24+、Docker Compose v2、Git、Node.js 20（构建前端用）

```bash
# 验证版本
docker compose version
node -v   # 推荐 v20
```

### 4.2 防火墙

仅开放 Web 端口，**禁止** MySQL/Redis 公网访问：

```bash
# ufw 示例
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

### 4.3 拉取代码

```bash
git clone <your-repo-url> king-shop
cd king-shop
```

---

## 5. 环境变量配置

### 5.1 Compose 层（`.env.prod`）

```bash
cp .env.prod.example .env.prod
```

编辑 `.env.prod`：

```env
MYSQL_ROOT_PASSWORD=<强密码>
MYSQL_DATABASE=king_shop
MYSQL_USER=king_shop
MYSQL_PASSWORD=<强密码>
APP_URL=https://your-domain.com
HTTP_PORT=80
```

### 5.2 Laravel 层

生产镜像通过 `docker-compose.prod.yml` 注入核心变量。首次部署前生成 `APP_KEY`：

```bash
docker compose -f docker-compose.prod.yml run --rm backend php artisan key:generate --show
```

将输出的 key 写入 `docker-compose.prod.yml` 的 `backend.environment.APP_KEY`，或在 Compose 中追加：

```yaml
APP_KEY: base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=
FRONTEND_URL: https://your-domain.com
```

| 变量 | 说明 | 示例 |
|------|------|------|
| `APP_URL` | API 对外根地址，**支付回调依赖此值** | `https://shop.example.com` |
| `FRONTEND_URL` | 代付 H5 链接前缀（与 `APP_URL` 同域即可） | `https://shop.example.com` |
| `APP_DEBUG` | 必须 `false` | `false` |
| `APP_KEY` | Laravel 加密密钥，**必填** | `base64:...` |

> 业务配置（支付、OSS 等）存数据库 `system_configs` 表，通过管理后台修改，**不入 `.env`**。

---

## 6. 一键部署

```bash
chmod +x scripts/deploy-prod.sh
./scripts/deploy-prod.sh
```

脚本会依次：

1. 检查 `.env.prod`（不存在则从模板创建）
2. 若 `APP_KEY` 为空则自动生成并追加到 `.env.prod`
3. 构建 `king-shop/php:8.4.3-fpm` 基础镜像（若不存在）
4. 构建 `king-shop-backend:prod` 镜像
5. 根据 `APP_URL` 生成 `frontend/.env.production` 并 `npm run build`
6. 启动 `mysql` → `redis` → `backend` → `nginx`
7. 执行迁移与种子数据（含超级管理员、系统配置默认值）
8. 调用 `scripts/health-check.sh` 做完整冒烟检查

### 6.1 前端 API 地址

`deploy-prod.sh` 会根据 `.env.prod` 中的 `APP_URL` 自动生成 `frontend/.env.production`：

```env
VITE_API_BASE_URL=https://your-domain.com/api/v1
VITE_APP_NAME=King Shop
```

也可参考 `frontend/.env.production.example` 手动配置。

### 6.2 手动部署（等价命令）

```bash
cd frontend && npm ci && npm run build && cd ..
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --build
```

---

## 7. 域名、备案与 HTTPS

### 7.1 域名解析

将备案域名 A 记录指向服务器公网 IP。Compose 中 `APP_URL` 与 `FRONTEND_URL` 改为 `https://` 域名。

### 7.2 SSL 证书（Let's Encrypt）

推荐在宿主机用 Certbot 申请证书，再挂载到 Nginx：

```bash
sudo apt install certbot
sudo certbot certonly --standalone -d your-domain.com
```

在 `docker/nginx/nginx.prod.conf` 增加 443 server 块，挂载证书：

```yaml
# docker-compose.prod.yml nginx volumes 追加
- /etc/letsencrypt:/etc/letsencrypt:ro
```

并将 `HTTP_PORT` 保持 80，同时配置 443 监听与 HTTP→HTTPS 跳转。

> 微信支付回调、JSAPI 代付 **要求 HTTPS**，上线前必须完成。

### 7.3 支付回调 URL

部署完成后，在微信商户平台配置：

| 类型 | URL |
|------|-----|
| 支付结果通知 | `https://your-domain.com/api/v1/payments/notify/wechat` |
| 支付宝通知（沙箱/生产） | `https://your-domain.com/api/v1/payments/notify/alipay` |

回调地址由 `PaymentConfigReader::notifyBaseUrl()` 读取 `APP_URL` 自动拼接。

---

## 8. 管理后台配置（M14）

首次登录后，使用 `super_admin` 在 **系统配置** 页面完成生产参数。

默认超级管理员（`SuperAdminSeeder`，**上线后立即改密**）：

| 字段 | 默认值 |
|------|--------|
| 手机号 | `13800000000` |
| 密码 | `admin123` |

访问：`https://your-domain.com` → 管理端登录页。

### 8.1 支付配置

路径：**系统配置 → 支付配置**（仅 `super_admin` 可编辑敏感项）

| 配置键 | 说明 |
|--------|------|
| `payment.provider` | 演示用 `alipay_sandbox`；上线切 `wechat` |
| `payment.wechat.app_id` | 微信开放平台移动应用 AppID |
| `payment.wechat.mch_id` | 微信支付商户号 |
| `payment.wechat.api_key` | API v2 密钥 |
| `payment.wechat.cert` | 商户 API 证书（PEM 内容） |

**微信商户平台还需配置：**

- App 支付：绑定移动应用包名与签名
- JSAPI 支付：绑定公众号 AppID（代付 H5）
- 支付授权目录：`https://your-domain.com/proxy-pay/`

### 8.2 存储配置

| 配置键 | 开发 | 生产 |
|--------|------|------|
| `storage.driver` | `local` | `oss` |
| `storage.oss.*` | — | Bucket、Endpoint、AK/SK |
| `storage.oss.public_base_url` | — | CDN 公开域名，如 `https://cdn.example.com` |

### 8.3 订单配置

| 配置键 | 默认 | 说明 |
|--------|------|------|
| `order.auto_cancel_minutes` | `30` | 未支付订单自动取消 |

---

## 9. 定时任务（Scheduler）

订单超时取消、支付状态补偿依赖 Laravel Scheduler：

```php
// backend/routes/console.php
Schedule::command('orders:cancel-expired')->everyMinute();
Schedule::command('payments:query-pending')->everyFiveMinutes();
```

**生产环境必须在宿主机配置 cron：**

```bash
crontab -e
```

参考 `scripts/cron-scheduler.example`，将路径改为实际项目目录后追加。

验证：

```bash
docker compose -f docker-compose.prod.yml exec backend php artisan schedule:list
```

---

## 10. Android App 生产构建

App API 地址在 `app/src/config/api.ts`，生产需改为正式域名：

```typescript
export const API_BASE_URL = 'https://your-domain.com/api/v1';
```

**微信 APP 支付额外步骤：**

1. 微信开放平台登记应用包名与签名
2. `android/app/build.gradle` 配置 `WECHAT_APP_ID`
3. `AndroidManifest.xml` 注册微信回调 Activity
4. Release 签名 APK/AAB 分发（内部分发或企业应用商店）

```bash
cd app
npm ci
cd android && ./gradlew assembleRelease
```

---

## 11. 部署验证清单

### 11.1 基础健康

```bash
# 完整健康检查（容器 + API + 管理端 + 构建产物）
./scripts/health-check.sh

# 单独检查 API
curl -s https://your-domain.com/api/v1/health

# 容器状态
docker compose -f docker-compose.prod.yml ps
```

### 11.2 业务冒烟

- [ ] 超级管理员登录管理后台，修改密码
- [ ] 创建员工账号，App 端可登录
- [ ] 上传商品图片（local 或 OSS）
- [ ] App 下单 → 支付（沙箱或微信）→ 订单变 `paid`
- [ ] 找人代付：生成链接 → 微信内打开 → 代付成功
- [ ] 管理后台订单列表、数据统计与数据库一致
- [ ] 未支付订单超时后自动取消（等待 `auto_cancel_minutes` 或手动触发 `orders:cancel-expired`）

### 11.3 安全

- [ ] 修改所有默认密码（MySQL、超级管理员）
- [ ] `APP_DEBUG=false`
- [ ] 防火墙仅开放 22/80/443
- [ ] MySQL/Redis 无公网端口
- [ ] HTTPS 生效，HTTP 跳转 HTTPS

---

## 12. 运维命令

```bash
# 查看日志
docker compose -f docker-compose.prod.yml logs -f backend nginx

# 数据库迁移
docker compose -f docker-compose.prod.yml exec backend php artisan migrate --force

# 重启服务
docker compose -f docker-compose.prod.yml restart

# 更新部署
git pull
./scripts/deploy-prod.sh
```

---

## 13. 常见问题

| 现象 | 可能原因 | 处理 |
|------|----------|------|
| 支付回调未到账 | `APP_URL` 非 HTTPS 或域名错误 | 检查 `.env.prod` 与微信商户回调 URL |
| 代付链接 404 | `FRONTEND_URL` 未配置或前端未 build | 确认 `frontend/dist` 存在且 Nginx 托管 `/proxy-pay/` |
| 管理端请求 localhost | `VITE_API_BASE_URL` 未设 | 重建前端并重新 `npm run build` |
| 图片无法访问 | `storage.driver=local` 但未配置公开域名 | 后台设置 `storage.local.public_base_url` 或切 OSS |
| 订单不自动取消 | Scheduler cron 未配置 | 见 §9 |
| `APP_KEY` 报错 | 生产未设置密钥 | `key:generate --show` 写入 Compose 环境变量 |

---

## 14. M16 验收标准

对照 [总体设计 Spec](./superpowers/specs/2026-07-12-internal-mall-design.md) § M16：

- [x] `docker compose -f docker-compose.prod.yml up -d` 可启动全套服务
- [x] 本文档覆盖：域名备案、SSL、微信商户配置
- [x] `scripts/health-check.sh` 返回 0（部署成功后）
- [x] 生产 `.env` 模板完整（`.env.prod.example` + `backend/.env.production.example`）

---

## 15. 关联文档

- [单机架构速查](./deploy-single-server.md)
- [M14 系统配置 Spec](./superpowers/specs/2026-07-12-M14-admin-settings-design.md)
- [M06 支付对接 Spec](./superpowers/specs/2026-07-12-M06-payment-integration-design.md)
- [M07 找人代付 Spec](./superpowers/specs/2026-07-12-M07-proxy-pay-design.md)
- [版本锁定约束](../.cursor/rules/versions.mdc)
