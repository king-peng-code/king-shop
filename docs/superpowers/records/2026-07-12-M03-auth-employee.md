# M03 用户认证与员工 — 执行与验收记录

> **模块：** M03  
> **日期：** 2026-07-12  
> **状态：** ✅ 完成  
> **关联计划：** [2026-07-12-M03-auth-employee.md](../plans/2026-07-12-M03-auth-employee.md)  
> **关联 Spec：** [2026-07-12-M03-auth-employee-design.md](../specs/2026-07-12-M03-auth-employee-design.md) v1.0.0

---

## 1. 执行摘要

| 交付项 | 状态 |
|--------|------|
| `users` 表扩展（phone, role, status 等） | ✅ |
| Domain / Application / Infrastructure / Http 分层 | ✅ |
| `POST/GET/PUT /auth/*` 登录/登出/me/改密 | ✅ |
| `CRUD /admin/employees` 员工 API | ✅ |
| `EnsureAdmin` + `EnsurePasswordChanged` 中间件 | ✅ |
| M01/M02 admin 路由角色保护 | ✅ |
| `SuperAdminSeeder` | ✅ |
| 自动化测试 | ✅ 67 tests |

---

## 2. 设计要点

- **登录**：手机号 + 密码，Sanctum Token
- **默认密码**：`123456`；Seeder 超管 `13800000000` / `admin123`
- **首次改密**：`must_change_password=true` 时中间件拦截（40301），仅允许 `/auth/me`、`/auth/password`、`/auth/logout`
- **员工删除**：软删除（`status=disabled`）
- **角色权限**：admin 可管普通员工；仅 super_admin 可分配 admin/super_admin

---

## 3. 验收清单

- [x] 员工/管理员可登录获 token
- [x] 禁用员工无法登录
- [x] 首次登录须改密，改密前无法访问 `/admin/*`
- [x] 非 admin 无法访问 `/admin/*`（含 M01 configs、M02 upload）
- [x] 手机号唯一、工号唯一
- [x] admin 不可分配 admin/super_admin 角色
- [x] DELETE 员工为软删除
- [x] Seeder 超管可登录
- [x] `./scripts/docker-test.sh` 全绿

---

## 4. API 端点

| 方法 | 路径 | 鉴权 |
|------|------|------|
| POST | `/api/v1/auth/login` | 无 |
| POST | `/api/v1/auth/logout` | sanctum |
| GET | `/api/v1/auth/me` | sanctum |
| PUT | `/api/v1/auth/password` | sanctum |
| GET/POST/GET/PUT/DELETE | `/api/v1/admin/employees` | sanctum + password.changed + admin |
