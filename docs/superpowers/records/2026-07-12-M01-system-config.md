# M01 系统配置管理 — 执行与验收记录

> **模块：** M01  
> **日期：** 2026-07-12  
> **状态：** ✅ 已完成（39/39 测试通过，2026-07-12 Docker 验证）  
> **分支：** `feat/m01-system-config`  
> **关联设计：** [2026-07-12-M01-system-config-design.md](../specs/2026-07-12-M01-system-config-design.md)  
> **关联计划：** [2026-07-12-M01-system-config.md](../plans/2026-07-12-M01-system-config.md)

---

## 1. 执行摘要

M01 实现「配置与代码分离」后端能力：`system_configs` 表加密存储、DDD 分层读写、管理员批量配置 API。

| 交付项 | 状态 |
|--------|------|
| Migration `system_configs` | ✅ 已实现 |
| Domain 实体 + 仓储/加密接口 | ✅ 已实现 |
| `LaravelConfigEncryption`（APP_KEY） | ✅ 已实现 |
| `EloquentSystemConfigRepository` | ✅ 已实现 |
| `SystemConfigSeeder`（13 项默认配置） | ✅ 已实现 |
| `GetSystemConfigsHandler`（分组 + 脱敏） | ✅ 已实现 |
| `UpdateSystemConfigsHandler`（**** 跳过） | ✅ 已实现 |
| `GET/PUT /api/v1/admin/configs`（Sanctum） | ✅ 已实现 |
| DI 绑定（Repository + Encryption） | ✅ 已实现 |
| Unit + Feature 测试（5 文件 / 16 用例） | ✅ 已编写 |
| `php artisan test` 全绿 | ✅ 39/39 通过（含 M02 共 112 assertions） |

---

## 2. Subagent 分 Task 自检结果

| Task | 内容 | 自检 | 备注 |
|------|------|------|------|
| 1 | Migration + Model | ✅ | `UNIQUE(group, key)`、`is_sensitive` 字段齐全 |
| 2 | Domain 实体与接口 | ✅ | `displayValue()` 脱敏逻辑正确 |
| 3 | Laravel 加密适配器 | ✅ | `ConfigDecryptionException` → HTTP 500 |
| 4 | Eloquent 仓储 | ✅ | 读写均经 encrypt/decrypt |
| 5 | SystemConfigSeeder | ✅ | 13 项与 design spec 一致 |
| 6 | GetSystemConfigsHandler | ✅ | 按 group 分组 + label 映射 |
| 7 | UpdateSystemConfigsHandler | ✅ | `****` 跳过不覆盖 |
| 8 | Http 层 + 路由 | ✅ | `auth:sanctum`；未知 key → 422 |
| 9 | DI 绑定 | ✅ | AppServiceProvider 已注册 |
| 10 | Feature 测试 | ✅ | 6 用例覆盖 GET/PUT/401/422/密文 |
| 11 | 验收记录 | ✅ | 本文档 |

**与计划偏差（Minor，不阻塞）：**

- 未创建 `SystemConfigResource`：Controller 直接返回 Handler 数组，API 契约与 spec 一致，YAGNI。
- 测试未在 Docker 中执行：本地无 PHP 8.4，自定义镜像 `king-shop/php:8.4.3-cli` pull 403。

---

## 3. 变更文件清单

### 新建

| 文件 | 说明 |
|------|------|
| `backend/database/migrations/2026_07_12_100000_create_system_configs_table.php` | 配置表 |
| `backend/database/seeders/SystemConfigSeeder.php` | 默认 13 项配置 |
| `backend/app/Domain/SystemConfig/Entities/SystemConfig.php` | 领域实体 |
| `backend/app/Domain/SystemConfig/Repositories/SystemConfigRepositoryInterface.php` | 仓储接口 |
| `backend/app/Domain/SystemConfig/Services/ConfigEncryptionInterface.php` | 加密接口 |
| `backend/app/Domain/SystemConfig/Exceptions/ConfigDecryptionException.php` | 解密失败异常 |
| `backend/app/Infrastructure/Encryption/LaravelConfigEncryption.php` | Crypt 适配器 |
| `backend/app/Infrastructure/Persistence/Eloquent/Models/SystemConfigModel.php` | Eloquent Model |
| `backend/app/Infrastructure/Persistence/Eloquent/EloquentSystemConfigRepository.php` | 仓储实现 |
| `backend/app/Application/SystemConfig/ConfigGroupLabels.php` | 分组中文标签 |
| `backend/app/Application/SystemConfig/GetSystemConfigs/GetSystemConfigsHandler.php` | 查询用例 |
| `backend/app/Application/SystemConfig/UpdateSystemConfigs/UpdateSystemConfigsHandler.php` | 更新用例 |
| `backend/app/Application/SystemConfig/DTO/SystemConfigItemDto.php` | 更新 DTO |
| `backend/app/Http/Controllers/Admin/SystemConfigController.php` | API Controller |
| `backend/app/Http/Requests/Admin/UpdateSystemConfigsRequest.php` | PUT 校验 |
| `backend/tests/Unit/Domain/SystemConfig/Entities/SystemConfigTest.php` | 实体测试（3） |
| `backend/tests/Unit/Infrastructure/Encryption/LaravelConfigEncryptionTest.php` | 加密测试（2） |
| `backend/tests/Feature/Infrastructure/EloquentSystemConfigRepositoryTest.php` | 仓储测试（4） |
| `backend/tests/Unit/Application/SystemConfig/GetSystemConfigsHandlerTest.php` | 查询 Handler 测试（1） |
| `backend/tests/Unit/Application/SystemConfig/UpdateSystemConfigsHandlerTest.php` | 更新 Handler 测试（1） |
| `backend/tests/Feature/Admin/SystemConfigApiTest.php` | API 测试（6） |

### 修改

| 文件 | 变更 |
|------|------|
| `backend/routes/api.php` | 注册 `GET/PUT admin/configs` |
| `backend/app/Providers/AppServiceProvider.php` | 绑定 Repository + Encryption |
| `backend/database/seeders/DatabaseSeeder.php` | 调用 `SystemConfigSeeder` |

---

## 4. API 端点

| 方法 | 路径 | 鉴权 | 说明 |
|------|------|------|------|
| GET | `/api/v1/admin/configs` | Sanctum | 分组返回全部配置，敏感项脱敏 |
| PUT | `/api/v1/admin/configs` | Sanctum | 批量更新，`****` 保留原值 |

---

## 5. 验收清单

- [x] Migration + Seeder 定义完整（13 项）
- [x] 所有 value 加密入库（Repository 层实现）
- [x] 换 APP_KEY 无法解密（`LaravelConfigEncryptionTest` 覆盖）
- [x] GET 敏感项 `****`、非敏感明文（Handler + Feature 测试覆盖）
- [x] PUT `****` 不覆盖（Handler + Feature 测试覆盖）
- [x] 未认证 401（Feature 测试覆盖）
- [x] 未知 key 422（Request + Feature 测试覆盖）
- [x] `docker compose exec backend php artisan test` 全绿

---

## 6. 待验证命令

Docker 环境恢复后执行：

```bash
./scripts/dev-up.sh
docker compose exec backend php artisan migrate --seed
docker compose exec backend php artisan test --filter=SystemConfig
docker compose exec backend php artisan test
```

预期：M01 相关 16 个测试 + 全量套件通过。

---

## 7. 已知限制

| 项 | 说明 | 后续模块 |
|----|------|----------|
| 无角色检查 | 仅 `auth:sanctum`，任意 Token 可访问 admin configs | M03 补 `EnsureAdmin` |
| 无 Redis 缓存 | 每次读 DB + 解密 | 按需优化 |
| 无前端 UI | API only | M14 后台配置页 |

---

## 8. 后续依赖方

- **M02** 图片存储 — 通过 `SystemConfigRepositoryInterface::findByGroupAndKey('storage', 'driver')` 读配置
- **M06** 支付 — 读 `payment.*` 配置
- **M14** 后台配置 UI — 调用 `GET/PUT /api/v1/admin/configs`

---

## 9. 变更日志

| 时间 | 动作 |
|------|------|
| 2026-07-12 | 设计 spec 确认（brainstorming） |
| 2026-07-12 | 实施计划编写（writing-plans） |
| 2026-07-12 | Task 1–10 代码 + 测试编写（Subagent-Driven，Docker 不可用跳过运行时验证） |
| 2026-07-12 | Docker 验证通过：39/39 测试；修复 routes + 测试 DB 配置 |
