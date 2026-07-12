# Backend — Laravel API

Laravel 12 API 后端，通过 **Docker** 运行，无需本机安装 PHP。

## 前置要求

- Docker Compose v2
- MySQL **8.0**、Redis **7.4**、PHP **8.4** 均由 `docker-compose.yml` 提供

## 快速开始

```bash
# 在项目根目录
docker compose up -d --build
```

首次启动自动完成：
1. `composer install`
2. 复制 `.env.example` → `.env` 并生成 APP_KEY
3. `php artisan migrate`
4. 启动 `http://localhost:8000`

API 健康检查：`GET http://localhost:8000/api/v1/health`

## 常用命令

```bash
# artisan（无需本机 PHP）
docker compose exec backend php artisan migrate
docker compose exec backend php artisan make:controller XxxController

# 测试（推荐快捷脚本）
./scripts/docker-test.sh
./scripts/docker-test.sh --filter=HealthCheckTest

# composer
docker compose exec backend composer require 包名

# 查看日志
docker compose logs -f backend
```

**测试规范：** 见 [docs/superpowers/docker-testing.md](../docs/superpowers/docker-testing.md)  
宿主机无需安装 PHP；PHPUnit 使用 sqlite `:memory:`，不依赖 MySQL 容器。

## 目录说明

```
backend/
├── Dockerfile          # PHP 8.4 镜像
├── docker/entrypoint.sh
├── app/                # 应用核心
├── routes/api.php      # API 路由 (/api/v1/)
└── ...
```

## 环境变量

容器内通过 Docker 网络连接数据库，`.env.example` 默认：

| 变量 | 值 | 说明 |
|------|-----|------|
| DB_HOST | mysql | Docker 服务名 |
| REDIS_HOST | redis | Docker 服务名 |
| CACHE_STORE | redis | 缓存驱动 |
| QUEUE_CONNECTION | redis | 队列驱动 |

宿主机工具直连数据库时，将 `DB_HOST` / `REDIS_HOST` 改为 `127.0.0.1`。
