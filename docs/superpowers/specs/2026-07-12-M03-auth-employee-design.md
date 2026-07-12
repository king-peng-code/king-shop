# M03 — 用户认证与员工模型 Design Spec

> **文档版本：** v1.0.0  
> **日期：** 2026-07-12  
> **依赖：** M00 后端 API 底座（已完成）、M01 系统配置（已完成）、M02 图片存储（已完成）  
> **后续依赖方：** M04 商品管理、M08 App 登录、M11 后台员工 UI

---

## 1. 目标

建立用户身份认证与员工管理能力：手机号 + 密码登录（Sanctum Token）、首次登录强制改密、管理员 CRUD 员工 API，并通过 `EnsureAdmin` 保护全部 `/admin/*` 路由（含 M01/M02 已有接口）。

**非目标（M03 不做）：**
- 前端登录页 / 员工管理 UI（留 M11/M14）
- 短信验证码
- 企业微信 SSO
- 员工自助修改密码（非首次改密场景；本期仅首次改密）
- App 端注册（仅管理员创建员工）

---

## 2. 设计决策摘要

| 决策 | 选择 | 理由 |
|---|---|---|
| 架构 | 单一 Bounded Context `Identity`，完整 DDD 四层 | 与 M01/M02 范式一致；员工即 User |
| `email` 字段 | 保留，改为 **nullable** | 最小改动，保留 Laravel 生态兼容 |
| 登录凭证 | **phone** + password | 内部员工，简单可靠 |
| 删除员工 | **软删除**（`status=disabled`） | 保留订单等历史关联数据 |
| 默认/重置密码 | **`123456`** | 管理员口头告知，内部系统够用 |
| 首次改密 | 登录发 token + `must_change_password: true`，中间件拦截其他 API | 后端强制，不依赖前端自觉 |
| admin 权限 | admin/super_admin 均可管理普通员工 | 日常运营分工 |
| super_admin 特权 | 仅 super_admin 可分配 `admin`/`super_admin` 角色 | 角色提升需更高权限 |
| 员工列表 | 分页 + `keyword` 搜索（姓名/手机号/工号） | 满足后台基本检索 |
| Seeder 超管 | `13800000000` / `admin123`，`must_change_password=false` | 引导安装后可立即使用后台 |

---

## 3. 架构与分层

```
Http/
  Controllers/AuthController.php
  Controllers/Admin/EmployeeController.php
  Middleware/EnsureAdmin.php
  Middleware/EnsurePasswordChanged.php
  Requests/Auth/LoginRequest.php
  Requests/Auth/ChangePasswordRequest.php
  Requests/Admin/CreateEmployeeRequest.php
  Requests/Admin/UpdateEmployeeRequest.php
  Resources/AuthUserResource.php
  Resources/Admin/EmployeeResource.php

Application/Identity/
  Login/LoginHandler.php
  Logout/LogoutHandler.php
  GetCurrentUser/GetCurrentUserHandler.php
  ChangePassword/ChangePasswordHandler.php
  CreateEmployee/CreateEmployeeHandler.php
  UpdateEmployee/UpdateEmployeeHandler.php
  DisableEmployee/DisableEmployeeHandler.php
  GetEmployee/GetEmployeeHandler.php
  ListEmployees/ListEmployeesHandler.php
  DTO/LoginResultDto.php
  DTO/EmployeeListDto.php

Domain/Identity/
  Entities/User.php
  ValueObjects/Role.php
  ValueObjects/UserStatus.php
  Repositories/UserRepositoryInterface.php
  Exceptions/InvalidCredentialsException.php
  Exceptions/AccountDisabledException.php
  Exceptions/ForbiddenRoleAssignmentException.php
  Exceptions/PasswordChangeRequiredException.php

Infrastructure/
  Persistence/Eloquent/EloquentUserRepository.php
  Persistence/Eloquent/Models/UserModel.php

config/
  identity.php          # DEFAULT_PASSWORD 等常量
```

### 数据流

**登录：**
```
AuthController → LoginRequest → LoginHandler
  → UserRepository::findByPhone()
  → 校验 status=active、密码 Hash::check
  → createToken('api') → LoginResultDto { token, user, must_change_password }
```

**首次改密拦截：**
```
auth:sanctum → EnsurePasswordChanged
  → must_change_password=true 且路由不在白名单 → 403 (code=40301)
  白名单: GET /auth/me, PUT /auth/password, POST /auth/logout
```

**Admin 路由：**
```
auth:sanctum → EnsurePasswordChanged → EnsureAdmin → Controller → Handler
```

---

## 4. 数据模型

### 4.1 Migration 扩展 `users`

| 字段 | 类型 | 说明 |
|------|------|------|
| `email` | string, **nullable**, unique | 修改现有字段为 nullable |
| `phone` | string(11), unique, not null | 登录凭证 |
| `employee_no` | string(50), unique, nullable | 工号 |
| `department` | string(100), nullable | 部门 |
| `role` | string(20), default `employee` | `employee` / `admin` / `super_admin` |
| `status` | string(20), default `active` | `active` / `disabled` |
| `avatar` | string, nullable | 头像 URL |
| `must_change_password` | boolean, default `true` | 首次改密标记 |

### 4.2 值对象

**Role：**
- `employee`, `admin`, `super_admin`
- `isAdmin(): bool` → role ∈ {admin, super_admin}
- `canAssignRole(Role $target): bool` → super_admin 可分配任意；admin 只能分配 employee

**UserStatus：**
- `active`, `disabled`
- `isActive(): bool`

### 4.3 常量

```php
// config/identity.php
'default_password' => '123456',
'super_admin_phone' => '13800000000',
'super_admin_password' => 'admin123',
```

---

## 5. API 设计

### 5.1 认证（`/api/v1/auth/*`）

| 方法 | 路径 | 鉴权 | 说明 |
|------|------|------|------|
| POST | `/auth/login` | 无 | 手机号 + 密码登录 |
| POST | `/auth/logout` | sanctum | 撤销当前 token |
| GET | `/auth/me` | sanctum | 当前用户信息 |
| PUT | `/auth/password` | sanctum | 修改密码（首次改密） |

#### POST `/auth/login`

**Request:**
```json
{ "phone": "13800000001", "password": "123456" }
```

**Response 200:**
```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "token": "1|xxx",
    "user": {
      "id": 1,
      "name": "张三",
      "phone": "13800000001",
      "employee_no": "E001",
      "department": "技术部",
      "role": "employee",
      "status": "active",
      "avatar": null,
      "must_change_password": true
    },
    "must_change_password": true
  }
}
```

**错误：**
| 场景 | HTTP | code | message |
|------|------|------|---------|
| 手机号或密码错误 | 401 | 401 | 手机号或密码错误 |
| 账号已禁用 | 403 | 403 | 账号已禁用 |

#### PUT `/auth/password`

**Request:**
```json
{
  "current_password": "123456",
  "new_password": "newpass1",
  "new_password_confirmation": "newpass1"
}
```

**规则：** 新密码最少 6 位；成功后 `must_change_password=false`。

**错误：**
| 场景 | HTTP | code | message |
|------|------|------|---------|
| 当前密码错误 | 422 | 422 | validation errors |

---

### 5.2 员工管理（`/api/v1/admin/employees`）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/admin/employees` | 分页列表 + keyword 搜索 |
| POST | `/admin/employees` | 创建员工 |
| GET | `/admin/employees/{id}` | 员工详情 |
| PUT | `/admin/employees/{id}` | 更新员工 |
| DELETE | `/admin/employees/{id}` | 禁用员工（软删除） |

**中间件：** `auth:sanctum` + `EnsurePasswordChanged` + `EnsureAdmin`

#### GET `/admin/employees`

**Query:** `?keyword=张&page=1&per_page=20`

**Response:**
```json
{
  "code": 0,
  "data": {
    "items": [ { /* EmployeeResource */ } ],
    "meta": { "total": 50, "page": 1, "per_page": 20 }
  }
}
```

**keyword 匹配：** `name LIKE %kw% OR phone LIKE %kw% OR employee_no LIKE %kw%`

#### POST `/admin/employees`

**Request:**
```json
{
  "name": "张三",
  "phone": "13800000002",
  "employee_no": "E001",
  "department": "技术部",
  "role": "employee"
}
```

- 密码自动设为 `123456`，`must_change_password=true`
- `role` 默认 `employee`；admin 创建 admin/super_admin → 422

#### PUT `/admin/employees/{id}`

**Request:**
```json
{
  "name": "张三",
  "employee_no": "E001",
  "department": "研发部",
  "role": "employee",
  "status": "active",
  "reset_password": false
}
```

- `reset_password: true` → 密码重置为 `123456`，`must_change_password=true`
- 不可禁用或降级 **自己**
- admin 不可将 role 改为 admin/super_admin

#### DELETE `/admin/employees/{id}`

- 设置 `status=disabled`（幂等）
- 不可禁用自己

---

### 5.3 中间件错误响应

| 中间件 | HTTP | code | message |
|--------|------|------|---------|
| EnsureAdmin | 403 | 403 | 无权访问 |
| EnsurePasswordChanged | 403 | 40301 | 请先修改密码 |

---

## 6. 权限矩阵

| 操作 | employee | admin | super_admin |
|------|----------|-------|-------------|
| POST /auth/login | ✅ | ✅ | ✅ |
| GET /auth/me 等 | ✅ | ✅ | ✅ |
| GET /admin/* | ❌ 403 | ✅ | ✅ |
| CRUD 普通员工 | ❌ | ✅ | ✅ |
| 分配 admin/super_admin | ❌ | ❌ 422 | ✅ |
| M01/M02 admin API | ❌ 403 | ✅ | ✅ |

---

## 7. Seeder

**SuperAdminSeeder：**
```php
UserModel::create([
    'name' => '超级管理员',
    'phone' => '13800000000',
    'password' => Hash::make('admin123'),
    'role' => 'super_admin',
    'status' => 'active',
    'must_change_password' => false,
]);
```

`DatabaseSeeder` 调用 `SuperAdminSeeder`（保留 `SystemConfigSeeder`）。

---

## 8. 测试策略

| 类型 | 文件 | 覆盖 |
|------|------|------|
| Unit | `tests/Unit/Domain/Identity/**` | Role、UserStatus、User 实体规则 |
| Unit | `tests/Unit/Application/Identity/**` | 各 Handler（Mock Repository） |
| Feature | `tests/Feature/Auth/AuthApiTest.php` | 登录/登出/me/改密/首次改密拦截 |
| Feature | `tests/Feature/Admin/EmployeeApiTest.php` | 员工 CRUD、权限、keyword |
| Feature | `tests/Feature/Admin/SystemConfigApiTest.php` | 补充 employee token → 403 |
| Feature | `tests/Feature/Admin/UploadApiTest.php` | 补充 employee token → 403 |
| Integration | `tests/Feature/Infrastructure/EloquentUserRepositoryTest.php` | 仓储 CRUD、搜索 |

**完成门槛：** `./scripts/docker-test.sh` 全部通过。

---

## 9. 验收标准

- [ ] 员工/管理员可手机号登录获 token
- [ ] 禁用员工无法登录
- [ ] 首次登录须改密，改密前无法访问 `/admin/*` 及其他业务 API
- [ ] 非 admin 角色无法访问 `/admin/*`（含 M01 configs、M02 upload）
- [ ] 手机号唯一、员工工号唯一
- [ ] admin 不可分配 admin/super_admin 角色
- [ ] DELETE 员工为软删除（status=disabled）
- [ ] Seeder 超管 `13800000000` / `admin123` 可登录
- [ ] 全部自动化测试通过

---

**Spec 路径：** `docs/superpowers/specs/2026-07-12-M03-auth-employee-design.md`
