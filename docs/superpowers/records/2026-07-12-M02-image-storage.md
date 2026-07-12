# M02 图片存储 — 执行与验收记录

> **模块：** M02  
> **日期：** 2026-07-12  
> **状态：** ✅ 已验收（39 tests passed，2026-07-12 Docker 验证）  
> **关联计划：** [2026-07-12-M02-image-storage.md](../plans/2026-07-12-M02-image-storage.md)  
> **关联 Spec：** [2026-07-12-M02-image-storage-design.md](../specs/2026-07-12-M02-image-storage-design.md) v1.1.0

---

## 1. 执行摘要

| 交付项 | 状态 |
|--------|------|
| `uploads` 表 Migration（仅存相对 `path`，无 `url`） | ✅ 已实现 |
| Domain / Application / Infrastructure / Http 分层 | ✅ 已实现 |
| `LocalStorageDriver` + `OssStorageDriver` | ✅ 已实现 |
| `HardcodedStorageDriverResolver`（首期绑定） | ✅ 已实现 |
| `ConfigStorageDriverResolver`（就绪未绑定） | ✅ 已实现 |
| `PublicUrlGenerator` + `config/storage.php` | ✅ 已实现 |
| `POST /api/v1/admin/upload` | ✅ 已实现 |
| 自动化测试（8 个测试文件） | ✅ 已编写 |
| `php artisan test` 全绿 | ✅ 39 passed (112 assertions) |

---

## 2. 设计要点（v1.1.0）

- **DB 仅存相对路径** `path`，不含域名
- **API 输出 `url`** 由 `ConfigPublicUrlGenerator` 按 config 动态拼接：
  - `local`: `{STORAGE_PUBLIC_BASE_URL}/storage/{path}`
  - `oss`: `{STORAGE_OSS_PUBLIC_BASE_URL}/{path}`
- **首期驱动**：`HardcodedStorageDriverResolver` 始终 `local`

---

## 3. 变更文件清单

### 新建

| 文件 | 说明 |
|------|------|
| `backend/database/migrations/2026_07_12_110000_create_uploads_table.php` | uploads 表 |
| `backend/config/storage.php` | 公开 URL 域名配置 |
| `backend/app/Domain/Storage/**` | 实体、VO、接口、异常 |
| `backend/app/Application/Storage/**` | Handler、DTO |
| `backend/app/Infrastructure/Storage/**` | 驱动、Resolver、PublicUrlGenerator |
| `backend/app/Infrastructure/Persistence/Eloquent/EloquentUploadRepository.php` | 仓储实现 |
| `backend/app/Infrastructure/Persistence/Eloquent/Models/UploadModel.php` | Eloquent Model |
| `backend/app/Http/Controllers/Admin/UploadController.php` | 上传 API |
| `backend/app/Http/Requests/Admin/UploadImageRequest.php` | 校验 |
| `backend/app/Http/Resources/Admin/UploadResource.php` | 响应 Resource |
| `backend/tests/Unit/Domain/Storage/ValueObjects/StoredFileTest.php` | |
| `backend/tests/Unit/Infrastructure/Storage/ConfigPublicUrlGeneratorTest.php` | |
| `backend/tests/Unit/Application/Storage/UploadImageHandlerTest.php` | |
| `backend/tests/Feature/Infrastructure/LocalStorageDriverTest.php` | |
| `backend/tests/Feature/Infrastructure/OssStorageDriverTest.php` | |
| `backend/tests/Feature/Infrastructure/ConfigStorageDriverResolverTest.php` | |
| `backend/tests/Feature/Infrastructure/EloquentUploadRepositoryTest.php` | |
| `backend/tests/Feature/Admin/UploadApiTest.php` | |

### 修改

| 文件 | 变更 |
|------|------|
| `backend/routes/api.php` | 注册 `POST /admin/upload` |
| `backend/app/Providers/AppServiceProvider.php` | 绑定 Storage 相关接口 |
| `backend/app/Exceptions/ApiExceptionHandler.php` | 注册 `StorageException` |
| `backend/composer.json` | 添加 `league/flysystem-aws-s3-v3` |
| `backend/docker/entrypoint.sh` | 补充 `storage:link` |
| `backend/.env.example` | 补充 `STORAGE_PUBLIC_BASE_URL` 等 |

---

## 4. 代码自检（无 Docker）

| 检查项 | 结果 |
|--------|------|
| `uploads` 表无 `url` 列 | ✅ migration 已确认 |
| `Upload` 实体无 `url` 字段 | ✅ |
| `StoredFile` 仅 `path` + `disk` | ✅ |
| `UploadImageHandler` 调用 `PublicUrlGenerator` | ✅ |
| 路由 `POST /api/v1/admin/upload` 已注册 | ✅ |
| `AppServiceProvider` 绑定 `HardcodedStorageDriverResolver` | ✅ |
| 测试文件 8 个齐全 | ✅ |
| `php artisan test` 全量 | ✅ **39 passed** |
| 上传 API 冒烟 | ✅ `SMOKE_OK` |

### 测试修复（环境）

Docker `DB_*` 环境变量曾覆盖 `phpunit.xml`，导致 sqlite 文件 `king_shop` 损坏。已在 `phpunit.xml` 为测试 env 添加 `force="true"`，并修复 `LaravelConfigEncryptionTest` 的 encrypter 缓存清理。

---

## 5. 验收 Checklist

- [x] `POST /api/v1/admin/upload` 返回含 `id` 的完整响应
- [x] `local` 驱动文件存 `storage/app/public/uploads/`
- [x] `uploads` 表仅存相对 `path`（无 `url` 列）
- [x] API 响应 `url` 由 `PublicUrlGenerator` 按 config 动态拼接
- [x] jpg/png/webp ≤2MB 校验生效
- [x] `OssStorageDriver` S3 兼容实现可单测
- [x] `ConfigStorageDriverResolver` 实现就绪，首期绑定 `HardcodedStorageDriverResolver`
- [x] `docker compose exec backend php artisan test` 全部通过

---

## 6. Docker 恢复后验证命令

```bash
./scripts/dev-up.sh
docker compose exec backend composer install
docker compose exec backend php artisan migrate
docker compose exec backend php artisan test
```

手动冒烟：

```bash
# 登录获 token 后
curl -X POST http://localhost:8000/api/v1/admin/upload \
  -H "Authorization: Bearer <token>" \
  -F "file=@/path/to/photo.jpg"
```

预期响应 `data.url` 含配置的公开域名，`data.path` 为相对路径。

---

## 7. 后续切换 OSS

1. `AppServiceProvider` 将 `StorageDriverResolverInterface` 绑定改为 `ConfigStorageDriverResolver`
2. M01 后台配置 `storage.driver=oss` 及 `storage.oss.*`
3. 设置 `STORAGE_OSS_PUBLIC_BASE_URL` 为 CDN/OSS 公开域名
