# M00 后端 API 底座 — 执行与验收记录

> **模块：** M00  
> **日期：** 2026-07-12  
> **状态：** 🔄 代码已完成，待本地 `php artisan test` 验证  
> **关联计划：** [2026-07-12-M00-api-foundation.md](../plans/2026-07-12-M00-api-foundation.md)  
> **关联 Spec：** [2026-07-12-internal-mall-design.md](../specs/2026-07-12-internal-mall-design.md) § M00

---

## 1. 执行摘要

M00 为后续 M01–M16 业务模块建立统一 API 底座，包括：

| 交付项 | 状态 |
|--------|------|
| `ApiResponse` 统一响应封装 | ✅ 已实现 |
| API 异常统一 JSON（含 422） | ✅ 已实现 |
| Laravel Sanctum 安装与配置 | ✅ 已实现 |
| `Services/` / `Requests/` / `Resources/` 目录 | ✅ 已创建 |
| `ForceJsonResponse` 中间件 | ✅ 已实现 |
| Feature / Unit 测试 | ✅ 已编写 |
| `php artisan test` 全绿 | ⏳ 待 PHP 8.4 环境执行 |

---

## 2. 变更文件清单

### 新建

| 文件 | 说明 |
|------|------|
| `backend/app/Http/Responses/ApiResponse.php` | 成功 / 错误 / 422 统一 JSON |
| `backend/app/Exceptions/BusinessException.php` | 业务异常（code + httpStatus） |
| `backend/app/Exceptions/ApiExceptionHandler.php` | API 异常渲染注册 |
| `backend/app/Http/Middleware/ForceJsonResponse.php` | API 强制 JSON Accept |
| `backend/app/Services/.gitkeep` | Service 层占位 |
| `backend/app/Http/Requests/.gitkeep` | Form Request 占位 |
| `backend/app/Http/Resources/.gitkeep` | API Resource 占位 |
| `backend/config/sanctum.php` | Sanctum 配置 |
| `backend/database/migrations/2026_07_12_000000_create_personal_access_tokens_table.php` | Sanctum Token 表 |
| `backend/tests/CreatesApplication.php` | 测试 Application 引导 |
| `backend/tests/Unit/ApiResponseTest.php` | ApiResponse 单元测试 |
| `backend/tests/Feature/HealthCheckTest.php` | health 接口格式测试 |
| `backend/tests/Feature/ApiExceptionTest.php` | 业务异常 + 422 测试 |
| `backend/tests/Feature/SanctumSetupTest.php` | Sanctum Token 创建测试 |

### 修改

| 文件 | 变更 |
|------|------|
| `backend/bootstrap/app.php` | 注册异常处理、ForceJsonResponse 中间件 |
| `backend/routes/api.php` | health 改用 `ApiResponse::success()` |
| `backend/composer.json` | 添加 `laravel/sanctum ^4.0` |
| `backend/app/Models/User.php` | 添加 `HasApiTokens` trait |
| `backend/tests/TestCase.php` | 使用 `CreatesApplication` trait |

---

## 3. API 响应规范（已实现）

### 成功

```json
{
  "code": 0,
  "message": "ok",
  "data": { }
}
```

### 业务错误

```json
{
  "code": 1001,
  "message": "Resource not found",
  "data": null
}
```

### 422 验证错误

```json
{
  "code": 422,
  "message": "Validation failed",
  "data": {
    "errors": {
      "email": ["The email field is required."]
    }
  }
}
```

---

## 4. 测试用例清单

| 测试类 | 用例 | 验证点 |
|--------|------|--------|
| `ApiResponseTest` | `success_response_has_standard_shape` | code=0, message=ok, data 结构 |
| `ApiResponseTest` | `error_response_has_standard_shape` | 非 0 code, data=null |
| `ApiResponseTest` | `validation_error_response_has_standard_shape` | code=422, data.errors |
| `HealthCheckTest` | `health_endpoint_returns_standard_api_format` | GET /api/v1/health |
| `ApiExceptionTest` | `business_exception_returns_standard_api_format` | BusinessException → JSON |
| `ApiExceptionTest` | `validation_exception_returns_unified_422_format` | ValidationException → 422 |
| `SanctumSetupTest` | `user_can_create_api_token` | HasApiTokens + migration |

---

## 5. 本地验证步骤

```bash
# 前置：PHP 8.4 + Composer 2.x
php -v   # 应显示 8.4.x

cd backend

# 安装依赖（含 Sanctum）
composer install

# 测试环境使用 sqlite :memory:（phpunit.xml 已配置，无需 MySQL）
php artisan test

# 可选：手动验证 health
php artisan serve
curl -s http://localhost:8000/api/v1/health | jq
```

**预期输出：**

```
Tests:    7 passed
Duration: ...
```

---

## 6. Spec 验收 Checklist

| # | 验收项 | 结果 | 备注 |
|---|--------|------|------|
| 1 | 所有 API 返回 `{ code, message, data }` 格式 | ✅ | ApiResponse + health 已接入 |
| 2 | 422 验证错误格式统一 | ✅ | ApiExceptionHandler + validationError |
| 3 | `php artisan test` 通过 | ⏳ | 需 PHP 8.4 环境执行 §5 |
| 4 | Sanctum 配置完成 | ✅ | composer + migration + HasApiTokens |
| 5 | 目录规范 | ✅ | Services / Requests / Resources |

---

## 7. 执行环境记录

| 项 | 值 |
|----|-----|
| 执行 Agent | Cursor Composer |
| 执行日期 | 2026-07-12 |
| 工作区 | `/Users/king/king-shop` |
| PHP 可用性 | ❌ 当前 shell 未检测到 PHP 8.4（`.php-version` 指定 8.4） |
| vendor/ | ❌ 未安装（需 `composer install`） |
| 测试执行 | 未运行 — 阻塞原因：无 PHP 二进制 |

**解除阻塞：** 安装 PHP 8.4 后执行 §5 验证命令，将本记录 §6 第 3 项更新为 ✅。

---

## 8. 后续模块依赖

M00 完成后可并行启动：

- **M01** 系统配置管理
- **M02** 图片存储
- **M03** 用户认证与员工模型（依赖 Sanctum Token）

---

## 9. 变更日志

| 时间 | 动作 |
|------|------|
| 2026-07-12 | 创建实施计划 `docs/superpowers/plans/2026-07-12-M00-api-foundation.md` |
| 2026-07-12 | 实现 ApiResponse、异常处理、Sanctum、中间件、目录 |
| 2026-07-12 | 编写 7 个测试用例（3 Unit/Feature 文件 + Sanctum） |
| 2026-07-12 | 创建本执行/验收记录 |
