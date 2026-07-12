# Docker 开发环境配置说明

## PHP 自有基础镜像

后端不直接依赖官方 `php:` 镜像，使用项目自封装的基础镜像：

| 镜像 | 用途 |
|------|------|
| `king-shop/php:8.4.3-cli` | 本地开发 |
| `king-shop/php:8.4.3-fpm` | 生产环境 |

```bash
./scripts/build-php-base.sh          # 构建
./scripts/build-php-base.sh --save   # 导出 tar 备份
REGISTRY=your-registry ./scripts/build-php-base.sh --push
```

扩展新 PHP 扩展：编辑 `docker/php/install-extensions.sh` → 重新构建。  
详见 [`docker/php/README.md`](../docker/php/README.md)。

## 服务一览

| 服务 | 镜像 | 端口 | 作用 |
|------|------|------|------|
| `mysql` | `mysql:8.0.40` | 3306 | 业务数据库 |
| `redis` | `redis:7.4-alpine` | 6379 | 缓存 / Session / 队列 |
| `backend` | `king-shop-backend:php8.4` | 8000 | Laravel API |

## 启动顺序

```
mysql (healthcheck) ──┐
                      ├──► backend (entrypoint 自动初始化)
redis (healthcheck) ──┘
```

`backend` 依赖 mysql 和 redis 健康后才启动。

## backend 自动初始化（entrypoint.sh）

容器首次启动时自动执行：

1. `cp .env.example .env` + `php artisan key:generate`
2. `composer install`（若无 vendor）
3. 等待 MySQL 可连接
4. `php artisan migrate --force`
5. `php artisan serve --host=0.0.0.0 --port=8000`

## 环境变量（容器内）

| 变量 | 值 | 说明 |
|------|-----|------|
| DB_HOST | mysql | Docker 网络服务名 |
| DB_DATABASE | king_shop | 数据库名 |
| DB_USERNAME | king_shop | 用户名 |
| DB_PASSWORD | secret | 密码 |
| REDIS_HOST | redis | Redis 服务名 |
| CACHE_STORE | redis | 缓存驱动 |
| SESSION_DRIVER | redis | Session 驱动 |
| QUEUE_CONNECTION | redis | 队列驱动 |

## 一键命令

```bash
./scripts/dev-up.sh      # 启动 + 健康检查
./scripts/dev-down.sh    # 停止
./scripts/dev-logs.sh    # 查看日志
./scripts/dev-logs.sh backend  # 只看后端日志
```

或直接使用：

```bash
docker compose up -d --build
docker compose ps
curl http://localhost:8000/api/v1/health
```

## 常用运维

```bash
# artisan
docker compose exec backend php artisan migrate
docker compose exec backend php artisan test

# composer
docker compose exec backend composer install

# 进入容器
docker compose exec backend sh

# 重建 backend 镜像
docker compose build --no-cache backend
docker compose up -d backend
```

## 数据持久化

| Volume | 挂载 |
|--------|------|
| `mysql_data` | MySQL 数据文件 |
| `redis_data` | Redis AOF 持久化 |
| `./backend` | 代码热更新（bind mount） |

## 网络

所有服务在 `king-shop` bridge 网络内，容器间通过服务名互访（`mysql`、`redis`）。

宿主机访问：
- API: `http://localhost:8000`
- MySQL: `127.0.0.1:3306`
- Redis: `127.0.0.1:6379`

## 镜像加速（已配置）

项目已在 `~/.docker/daemon.json` 配置国内镜像源：

```json
"registry-mirrors": [
  "https://docker.m.daocloud.io",
  "https://docker.1ms.run",
  "https://dockerpull.org"
]
```

修改后需重启 Docker 生效：

```bash
./scripts/docker-restart.sh
```

## 镜像拉取失败（手动排查）
