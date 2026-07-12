# M00 后端 API 底座 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 建立 Laravel 后端统一 API 规范（响应格式、异常处理、Sanctum、目录结构），供 M01–M16 业务模块复用。

**Architecture:** 通过 `ApiResponse` 静态类封装 `{ code, message, data }`；在 `bootstrap/app.php` 的 `withExceptions` 中注册 API 异常渲染（Laravel 12 无传统 `Handler.php`，逻辑放 `App\Exceptions\ApiExceptionHandler`）；Sanctum 提供 Bearer Token 鉴权基础；`Services/`、`Requests/`、`Resources/` 目录预建供后续模块使用。

**Tech Stack:** PHP 8.4 · Laravel 12 · Laravel Sanctum · PHPUnit 11

## Global Constraints

- PHP **8.4**（禁止 8.3 及以下及 8.5+）
- Laravel **12**（禁止其他主版本）
- API 前缀 `/api/v1/`
- 成功响应 `{ "code": 0, "message": "ok", "data": {} }`
- 业务错误 `{ "code": <非0>, "message": "...", "data": null }`
- 422 验证错误统一 JSON 格式
- 鉴权 Bearer Token（Sanctum）
- 测试使用 sqlite `:memory:`（phpunit.xml 已配置）

---

### Task 1: ApiResponse 统一响应类

**Files:**
- Create: `backend/app/Http/Responses/ApiResponse.php`
- Test: `backend/tests/Unit/ApiResponseTest.php`

**Interfaces:**
- Produces: `ApiResponse::success($data, $message, $httpStatus): JsonResponse`
- Produces: `ApiResponse::error($code, $message, $data, $httpStatus): JsonResponse`
- Produces: `ApiResponse::validationError($errors, $message): JsonResponse`

- [ ] **Step 1: Write the failing test**

```php
public function test_success_response_has_standard_shape(): void
{
    $response = ApiResponse::success(['status' => 'healthy']);
    $payload = $response->getData(true);
    $this->assertSame(0, $payload['code']);
    $this->assertSame('ok', $payload['message']);
    $this->assertSame(['status' => 'healthy'], $payload['data']);
    $this->assertSame(200, $response->getStatusCode());
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./scripts/docker-test.sh --filter=ApiResponseTest`
Expected: FAIL — class not found

- [ ] **Step 3: Write minimal implementation**

实现 `success()`、`error()`、`validationError()` 三个静态方法。

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS

---

### Task 2: 业务异常与 API 异常渲染

**Files:**
- Create: `backend/app/Exceptions/BusinessException.php`
- Create: `backend/app/Exceptions/ApiExceptionHandler.php`
- Modify: `backend/bootstrap/app.php`
- Test: `backend/tests/Feature/ApiExceptionTest.php`

**Interfaces:**
- Consumes: `ApiResponse::error()`, `ApiResponse::validationError()`
- Produces: `BusinessException`（code + message + httpStatus）
- Produces: `ApiExceptionHandler::register(Exceptions $exceptions): void`

- [ ] **Step 1: Write failing tests**

- 抛出 `BusinessException(1001, 'not found')` → `{ code: 1001, message, data: null }`
- 验证失败 → HTTP 422，`code: 422`，`data.errors` 含字段错误

- [ ] **Step 2–4: Implement and verify**

在 `bootstrap/app.php` 调用 `ApiExceptionHandler::register($exceptions)`。

---

### Task 3: 重构 health 路由 + Feature Test

**Files:**
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/HealthCheckTest.php`

- [ ] health 路由改用 `ApiResponse::success()`
- [ ] Feature test 断言 `GET /api/v1/health` 返回标准格式

---

### Task 4: Sanctum 安装与配置

**Files:**
- Modify: `backend/composer.json`（laravel/sanctum）
- Modify: `backend/app/Models/User.php`（HasApiTokens）
- Modify: `backend/bootstrap/app.php`（statefulApi 可选，API 用 token）
- Create: migration via `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"`

- [ ] `composer require laravel/sanctum`
- [ ] User 模型添加 `HasApiTokens`
- [ ] 发布配置与 migration
- [ ] `config/sanctum.php` 确认 token 驱动

---

### Task 5: 目录规范与基础中间件

**Files:**
- Create: `backend/app/Services/.gitkeep`
- Create: `backend/app/Http/Requests/.gitkeep`
- Create: `backend/app/Http/Resources/.gitkeep`
- Create: `backend/app/Http/Middleware/ForceJsonResponse.php`
- Modify: `backend/bootstrap/app.php`（api 中间件组追加 ForceJsonResponse）

- [ ] API 路由强制 `Accept: application/json`，确保异常也返回 JSON

---

### Task 6: 全量测试与文档

**Files:**
- Create: `docs/superpowers/records/2026-07-12-M00-api-foundation.md`

- [ ] `./scripts/docker-test.sh` 全部通过
- [ ] 更新 spec 模块状态表 M00 → ✅
- [ ] 填写执行/验收记录文档

---

## Self-Review

| Spec 要求 | 对应 Task |
|-----------|-----------|
| ApiResponse.php | Task 1 |
| 异常 Handler 统一 JSON | Task 2 |
| Sanctum 配置 | Task 4 |
| Services/Requests/Resources 目录 | Task 5 |
| health Feature Test | Task 3 |
| 422 格式统一 | Task 2 |
| php artisan test 通过 | Task 6 |

> 测试命令统一使用 Docker，见 [docker-testing.md](../docker-testing.md)
