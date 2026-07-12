# M00 后端 API 底座 — 执行与验收记录

> **模块：** M00  
> **日期：** 2026-07-12  
> **状态：** ✅ 已完成（Docker 测试通过）  
> **关联计划：** [2026-07-12-M00-api-foundation.md](../plans/2026-07-12-M00-api-foundation.md)  
> **关联 Spec：** [2026-07-12-internal-mall-design.md](../specs/2026-07-12-internal-mall-design.md) § M00  
> **测试规范：** [docker-testing.md](../docker-testing.md)

---

## 1. 执行摘要

M00 为后续 M01–M16 业务模块建立统一 API 底座。

| 交付项 | 状态 |
|--------|------|
| `ApiResponse` 统一响应封装 | ✅ |
| API 异常统一 JSON（含 422） | ✅ |
| Laravel Sanctum 安装与配置 | ✅ |
| `Services/` / `Requests/` / `Resources/` 目录 | ✅ |
| `ForceJsonResponse` 中间件 | ✅ |
| Feature / Unit 测试（7 用例） | ✅ |
| Docker 内 `php artisan test` | ✅ **7 passed** |

---

## 2. 变更文件清单

（略，同初版 — 见 git diff）

---

## 3. API 响应规范（已实现）

### 成功

```json
{ "code": 0, "message": "ok", "data": { } }
```

### 业务错误

```json
{ "code": 1001, "message": "Resource not found", "data": null }
```

### 422 验证错误

```json
{
  "code": 422,
  "message": "Validation failed",
  "data": { "errors": { "email": ["The email field is required."] } }
}
```

---

## 4. 测试用例清单

| 测试类 | 用例 | 结果 |
|--------|------|------|
| `ApiResponseTest` | success / error / validation | ✅ ×3 |
| `HealthCheckTest` | health 标准格式 | ✅ |
| `ApiExceptionTest` | 业务异常 / 422 | ✅ ×2 |
| `SanctumSetupTest` | Token 创建 | ✅ |

---

## 5. 本地验证步骤（Docker 标准流程）

> 详见 **[docker-testing.md](../docker-testing.md)**

```bash
# 1. 启动环境
./scripts/dev-up.sh

# 2. M00 模块测试
./scripts/docker-test.sh --filter='ApiResponseTest|HealthCheckTest|ApiExceptionTest|SanctumSetupTest'

# 3. 手动验证 health
curl -s http://localhost:8000/api/v1/health | jq
```

**实际输出（2026-07-12）：**

```
Tests:    7 passed (29 assertions)
Duration: 4.79s
PHP 8.4.3 (cli) — king-shop-backend container
```

---

## 6. Spec 验收 Checklist

| # | 验收项 | 结果 | 备注 |
|---|--------|------|------|
| 1 | 所有 API 返回 `{ code, message, data }` 格式 | ✅ | ApiResponse + health |
| 2 | 422 验证错误格式统一 | ✅ | ApiExceptionHandler |
| 3 | `php artisan test` 通过 | ✅ | Docker 内 7/7 M00 用例 |
| 4 | Sanctum 配置完成 | ✅ | migration + HasApiTokens |
| 5 | 目录规范 | ✅ | Services / Requests / Resources |

---

## 7. 执行环境记录

| 项 | 值 |
|----|-----|
| PHP | **8.4.3**（Docker `king-shop-backend`） |
| 测试方式 | `./scripts/docker-test.sh` |
| 测试 DB | sqlite `:memory:` |
| 宿主机 PHP | 不需要 |

---

## 8. 后续模块依赖

M00 完成后可启动：**M01** 配置 · **M02** 存储 · **M03** 认证

---

## 9. 变更日志

| 时间 | 动作 |
|------|------|
| 2026-07-12 AM | 实现 ApiResponse、异常处理、Sanctum、中间件 |
| 2026-07-12 PM | 建立 Docker 测试规范 `docker-testing.md` |
| 2026-07-12 PM | Docker 内 M00 测试 7/7 通过，模块验收 ✅ |
