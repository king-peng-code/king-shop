# 单机生产部署指南

> **完整上线指引（备案、SSL、微信商户、App 构建）见 [deploy-guide.md](./deploy-guide.md)。**  
> 本文侧重架构说明与运维速查。

## 架构

**MySQL、Redis、PHP 全部在同一台云服务器上**，通过 Docker 内网互访，不拆云数据库/云 Redis。

```
一台云服务器
┌──────────────────────────────────────┐
│  nginx:80          （唯一对外端口）     │
│  ├── /          → frontend 静态页面   │
│  ├── /api/*     → PHP-FPM (节流)     │
│  └── 登录接口    → 更严格节流          │
├──────────────────────────────────────┤
│  backend (PHP 8.4 FPM)               │
│  缓存/Session/队列 → Redis            │
├──────────────┬───────────────────────┤
│ mysql:8.0.40 │ redis:7.4             │
│ （仅内网）    │ （仅内网）              │
└──────────────┴───────────────────────┘
```

> MySQL 和 Redis **不映射公网端口**，只在 Docker 内网通过服务名 `mysql` / `redis` 访问。

## 与本地开发的区别

| 项目 | 本地 `docker-compose.yml` | 生产 `docker-compose.prod.yml` |
|------|--------------------------|-------------------------------|
| 服务 | backend + MySQL + Redis | **Nginx** + PHP-FPM + MySQL + Redis |
| Web 服务器 | artisan serve | Nginx + PHP-FPM |
| 代码 | bind mount 热更新 | 打进镜像 |
| MySQL/Redis 端口 | 暴露 3306/6379 | **仅内网，不暴露** |
| 对外端口 | 8000 | **80**（+ 管理端静态页） |
| 节流 | 无 | Nginx + Laravel 双层 |

## 节流策略

| 层级 | 规则 | 作用 |
|------|------|------|
| Nginx | API 30 req/s，burst 60 | 防 DDoS / 恶意刷接口 |
| Nginx | 登录 5 req/min | 防暴力破解 |
| Laravel | `throttleApi()` 60 req/min | 应用层兜底 |

## 部署步骤

### 1. 准备服务器

- 系统：Ubuntu 22.04+ / CentOS 8+
- 配置：2C4G 起
- 安装 Docker + Docker Compose v2

### 2. 配置环境变量

```bash
cp .env.prod.example .env.prod
# 编辑 .env.prod，修改密码和域名
```

### 3. 一键部署

```bash
chmod +x scripts/deploy-prod.sh
./scripts/deploy-prod.sh
```

或手动：

```bash
cd frontend && npm ci && npm run build && cd ..
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --build
```

### 4. 验证

```bash
./scripts/health-check.sh
docker compose -f docker-compose.prod.yml ps
```

## 运维命令

```bash
# 查看日志
docker compose -f docker-compose.prod.yml logs -f

# 数据库迁移
docker compose -f docker-compose.prod.yml exec backend php artisan migrate

# 重启
docker compose -f docker-compose.prod.yml restart

# 更新部署
git pull
./scripts/deploy-prod.sh
```

## 安全清单

- [ ] 修改 `.env.prod` 中所有默认密码
- [ ] 设置 `APP_KEY`（`php artisan key:generate --show`）
- [ ] `APP_DEBUG=false`
- [ ] 配置防火墙，**仅开放 80/443**（MySQL/Redis 不对外开放）
- [ ] 后续加 HTTPS（Let's Encrypt + Certbot）

## 后续扩展

业务量大时再拆分（非必须）：
- MySQL → 云 RDS
- Redis → 云 Redis
- 多实例 → 负载均衡 + 共享 MySQL/Redis
