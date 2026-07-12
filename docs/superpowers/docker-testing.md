# Docker 后端测试规范

> **适用范围：** 所有 backend 模块（M00–M16）及后续 DDD 业务开发  
> **原则：** PHP 8.4 **只在 Docker 内运行**，宿主机无需安装 PHP / Composer  
> **完成门槛：** 模块验收前必须在本规范流程下跑通测试

---

## 1. 架构概览

```
宿主机                          Docker Compose
┌─────────────┐                ┌──────────────────────────────┐
│ Node.js     │                │ backend (PHP 8.4.3 CLI)      │
│ frontend/   │──HTTP:8000──▶  │   php artisan serve          │
│ app/        │                │   php artisan test  ← 测试   │
└─────────────┘                ├──────────────────────────────┤
                               │ mysql:8.0.40  (3307→3306)    │
                               │ redis:7.4     (6380→6379)    │
                               └──────────────────────────────┘
         king-shop/php:8.4.3-cli（自有基础镜像）
```

| 组件 | 说明 |
|------|------|
| `king-shop/php:8.4.3-cli` | 自有 PHP 基础镜像，预装扩展 + Composer |
| `king-shop-backend:dev` | 应用镜像，挂载 `./backend` 源码 |
| 测试数据库 | **sqlite `:memory:`**（`phpunit.xml` 配置，不依赖 MySQL） |
| 运行时数据库 | MySQL（容器内 `mysql:3306`，供 `artisan serve` 使用） |

---

## 2. 一键启动

```bash
# 项目根目录
./scripts/dev-up.sh
```

自动完成：构建 PHP 基础镜像（若缺失）→ 构建 backend → 启动 mysql/redis/backend → composer install → migrate。

**健康检查：**

```bash
curl -s http://localhost:8000/api/v1/health | jq
```

---

## 3. 运行测试（标准命令）

### 3.1 全量测试

```bash
./scripts/docker-test.sh
# 等价于：
docker compose exec backend php artisan test
```

### 3.2 单模块 / 单测试类

```bash
# 按 PHPUnit filter
./scripts/docker-test.sh --filter=HealthCheckTest
./scripts/docker-test.sh --filter=SystemConfig

# 或直接 exec
docker compose exec backend php artisan test --filter=ApiResponseTest
```

### 3.3 其他常用命令

```bash
# 进入容器调试
docker compose exec backend sh

# 容器内 composer
docker compose exec backend composer install
docker compose exec backend composer require some/package

# 迁移 + 种子（运行时 MySQL）
docker compose exec backend php artisan migrate --seed

# 查看 backend 日志
./scripts/dev-logs.sh
```

---

## 4. 测试环境约定

### 4.1 phpunit.xml 固定配置

| 变量 | 值 | 说明 |
|------|-----|------|
| `APP_ENV` | `testing` | 测试环境 |
| `APP_KEY` | `base64:AAAA...` | **必须配置**，否则 Crypt 相关测试失败 |
| `DB_CONNECTION` | `sqlite` | 内存数据库 |
| `DB_DATABASE` | `:memory:` | 每测试类隔离 |
| `CACHE_STORE` | `array` | 不依赖 Redis |
| `SESSION_DRIVER` | `array` | 不依赖 Redis |

> **禁止**在测试中连接 Docker MySQL，除非明确的 Integration 测试需要且文档说明。

### 4.2 测试分层

| 类型 | 目录 | 数据库 |
|------|------|--------|
| Unit | `tests/Unit/` | 一般不连 DB |
| Feature | `tests/Feature/` | `RefreshDatabase` + sqlite |
| Infrastructure | `tests/Feature/Infrastructure/` | `RefreshDatabase` + sqlite |

---

## 5. 模块验收流程（每个 Mxx 必须执行）

每个模块完成后，Agent / 开发者按以下顺序验收并写入 `docs/superpowers/records/`：

```
1. ./scripts/dev-up.sh                          # 确保环境运行
2. ./scripts/docker-test.sh --filter=<Module>   # 模块相关测试
3. ./scripts/docker-test.sh                     # 全量回归（推荐）
4. 更新 records 文档 §6 验收 Checklist
5. 更新 spec §7.2 模块状态 → ✅
```

### 5.1 M00 验收命令

```bash
./scripts/docker-test.sh --filter='ApiResponseTest|HealthCheckTest|ApiExceptionTest|SanctumSetupTest'
```

### 5.2 记录模板（records 文档必含）

| 章节 | 内容 |
|------|------|
| §5 本地验证步骤 | 使用 **Docker 命令**，不写宿主机 `php artisan` |
| §6 验收 Checklist | 每项标注 ✅ / ⏳，附测试输出摘要 |
| §7 执行环境 | Docker 镜像版本、测试通过数/失败数 |

---

## 6. PHP 扩展变更

编辑 `docker/php/install-extensions.sh` 后重新构建：

```bash
./scripts/build-php-base.sh
docker compose build backend
docker compose up -d backend
```

| 扩展 | 用途 |
|------|------|
| pdo_mysql, redis | 运行时 DB/缓存 |
| gd | 图片上传测试（`UploadedFile::fake()->image()`） |
| zip, mbstring, xml, bcmath, pcntl | Laravel 常规依赖 |

---

## 7. 故障排查

| 现象 | 原因 | 处理 |
|------|------|------|
| `command not found: php` | 在宿主机直接跑 php | 改用 `docker compose exec backend php ...` |
| `MissingAppKeyException` | phpunit.xml 缺 APP_KEY | 确认 §4.1 配置 |
| `GD extension is not installed` | 基础镜像未含 gd | 重建 PHP 基础镜像（§6） |
| backend 容器 Exited | migrate/composer 失败 | `docker compose logs backend` |
| 测试连不上 MySQL | 测试误用 mysql 连接 | 检查 phpunit.xml 用 sqlite |

---

## 8. 相关文档

| 文档 | 说明 |
|------|------|
| `docker/php/README.md` | PHP 基础镜像构建 |
| `scripts/dev-up.sh` | 开发环境启动 |
| `scripts/docker-test.sh` | 测试快捷脚本 |
| `.cursor/rules/ddd-development.mdc` | DDD 测试强制要求 |
| `.cursor/rules/versions.mdc` | 版本锁定 |

---

## 9. Agent 执行约定

1. **禁止**假设宿主机有 PHP 8.4
2. **必须**用 `docker compose exec backend php artisan test` 验证
3. 模块 records 文档 §5 统一引用本文档
4. 实施计划（plans）中的测试命令统一写 Docker 形式
5. 声明模块完成前，附测试通过的实际输出
