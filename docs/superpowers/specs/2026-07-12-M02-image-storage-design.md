# M02 — 图片存储 Design Spec

> **文档版本：** v1.1.0  
> **日期：** 2026-07-12  
> **依赖：** M00 后端 API 底座（已完成）、M01 系统配置（并行，首期不依赖配置切换）  
> **后续依赖方：** M04 商品管理、M14 后台商品 UI

---

## 1. 目标

建立图片上传与存储能力：管理员通过 API 上传图片，文件持久化到本地或 OSS，元数据写入 `uploads` 表，返回可访问 URL 及记录 ID。

**非目标（M02 不做）：**
- 角色/权限检查（留 M03，仅 `auth:sanctum`）
- 媒体库列表/删除 API（留后续按需扩展）
- 图片裁剪/压缩/水印
- App 端上传（仅 Admin API）

---

## 2. 设计决策摘要

| 决策 | 选择 | 理由 |
|---|---|---|
| 架构 | 完整 DDD 四层 + 驱动模式 | 与 M01 范式一致，驱动可独立单测 |
| M01 依赖 | 首期硬编码 `local` | M02 可与 M01 并行；`StorageDriverResolver` 可替换 |
| OSS 实现 | Laravel S3 兼容驱动 | 阿里云 OSS 支持 S3 协议，生产可直接切换 |
| 元数据 | `uploads` 表 | 返回 `id`，支持后续媒体库与审计 |
| URL 策略 | DB 仅存相对 `path`，输出时拼接域名 | 域名变更无需改历史数据 |
| 域名配置 | `config/storage.php` | `STORAGE_PUBLIC_BASE_URL` 默认 `APP_URL` |
| 鉴权 | 仅 `auth:sanctum` | 角色检查留 M03 |

---

## 3. 架构与分层

```
Http/
  Controllers/Admin/UploadController.php
  Requests/Admin/UploadImageRequest.php
  Resources/Admin/UploadResource.php

Application/Storage/
  UploadImage/UploadImageHandler.php
  DTO/UploadImageCommand.php
  DTO/UploadResultDto.php

Domain/Storage/
  Entities/Upload.php
  Repositories/UploadRepositoryInterface.php
  Services/StorageDriverInterface.php
  Services/StorageDriverResolverInterface.php
  Services/PublicUrlGeneratorInterface.php
  ValueObjects/StoredFile.php
  Exceptions/StorageException.php

Infrastructure/Storage/
  ConfigPublicUrlGenerator.php
  Drivers/LocalStorageDriver.php
  Drivers/OssStorageDriver.php
  Resolvers/HardcodedStorageDriverResolver.php    # 首期绑定
  Resolvers/ConfigStorageDriverResolver.php         # M01 接入后替换
  Persistence/Eloquent/EloquentUploadRepository.php
  Persistence/Eloquent/Models/UploadModel.php
```

### 数据流

```
UploadController
  → UploadImageRequest (validate multipart file)
  → UploadImageHandler
      → StorageDriverResolver::resolve() → LocalStorageDriver (首期)
      → driver.store(contents, extension, mimeType) → StoredFile { path, disk }
      → UploadRepository::save(Upload entity)          # 仅存相对 path
      → PublicUrlGenerator::generate(path, disk) → url # 输出时拼接域名
      → UploadResultDto { id, url, path, filename, size }
  → UploadResource → ApiResponse::success()
```

---

## 4. 数据模型

### Migration: `uploads`

| 字段 | 类型 | 说明 |
|---|---|---|
| `id` | bigint PK | |
| `original_name` | varchar(255) | 原始文件名 |
| `path` | varchar(500) | 存储相对路径（不含域名） |
| `disk` | varchar(20) | `local` / `oss` |
| `mime_type` | varchar(100) | |
| `size` | unsigned int | 字节 |
| `uploaded_by` | bigint nullable FK → users | Sanctum 用户 ID |
| `created_at` / `updated_at` | timestamps | |

**路径规则：** `uploads/{Y}/{m}/{uuid}.{ext}`

---

## 5. 公开 URL 生成

### 配置 — `config/storage.php`

| 键 | 环境变量 | 默认值 | 说明 |
|---|---|---|---|
| `public_base_url` | `STORAGE_PUBLIC_BASE_URL` | `APP_URL` | local 驱动的公开访问域名 |
| `oss_public_base_url` | `STORAGE_OSS_PUBLIC_BASE_URL` | `''` | OSS/CDN 公开域名（生产配置） |

### PublicUrlGenerator

输出时按 `disk` 拼接完整 URL，**不入库**：

| disk | 拼接规则 | 示例 |
|---|---|---|
| `local` | `{public_base_url}/storage/{path}` | `http://localhost:8000/storage/uploads/2026/07/abc.jpg` |
| `oss` | `{oss_public_base_url}/{path}` | `https://cdn.example.com/uploads/2026/07/abc.jpg` |

---

## 6. 存储驱动

### LocalStorageDriver

- 写入 Laravel `public` disk → `storage/app/public/uploads/...`
- 返回 `StoredFile { path, disk: 'local' }`（不含 URL）
- Docker entrypoint 补充 `php artisan storage:link`

### OssStorageDriver

- 使用 Laravel Filesystem `s3` driver（S3 协议对接阿里云 OSS）
- 依赖：`league/flysystem-aws-s3-v3`
- 配置来源：M01 `storage.oss.*`（bucket、endpoint、access_key、secret_key）
- 运行时动态构建 disk config，`use_path_style_endpoint` 按 OSS 要求配置

### 驱动解析

| 解析器 | 行为 | 绑定时机 |
|---|---|---|
| `HardcodedStorageDriverResolver` | 始终返回 `LocalStorageDriver` | M02 首期 |
| `ConfigStorageDriverResolver` | 读 M01 `storage.driver` 选择驱动 | M01 接入后替换绑定 |

---

## 7. API 契约

**路由：** `POST /api/v1/admin/upload`  
**中间件：** `auth:sanctum`

### 请求

`multipart/form-data`，字段名 `file`

### 校验

| 规则 | 值 |
|---|---|
| `file` | required, file, image |
| mimes | jpeg, jpg, png, webp |
| max | 2048 KB（2MB） |

### 成功响应

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "id": 1,
    "url": "http://localhost:8000/storage/uploads/2026/07/abc123.jpg",
    "path": "uploads/2026/07/abc123.jpg",
    "filename": "abc123.jpg",
    "size": 102400
  }
}
```

### 错误

| HTTP | 场景 |
|---|---|
| 401 | 未提供/无效 Token |
| 422 | 格式/大小校验失败 |
| 500 | 存储写入失败（`StorageException` → `BusinessException` code 2001） |

---

## 8. 测试计划

| 类型 | 文件 | 覆盖点 |
|---|---|---|
| Unit | `tests/Unit/Domain/Storage/ValueObjects/StoredFileTest.php` | 值对象 |
| Unit | `tests/Unit/Application/Storage/UploadImageHandlerTest.php` | Handler 编排（Mock 驱动+仓储） |
| Feature | `tests/Feature/Admin/UploadApiTest.php` | 上传成功、401、422、DB 记录 |
| Unit | `tests/Unit/Infrastructure/Storage/ConfigPublicUrlGeneratorTest.php` | local/oss URL 拼接 |
| Feature | `tests/Feature/Infrastructure/LocalStorageDriverTest.php` | 文件落盘（仅 path） |
| Feature | `tests/Feature/Infrastructure/OssStorageDriverTest.php` | Storage::fake('s3') 写入 |
| Feature | `tests/Feature/Infrastructure/ConfigStorageDriverResolverTest.php` | 读 M01 配置切换驱动 |

**完成门槛：**

```bash
docker compose exec backend php artisan test
```

---

## 9. 验收标准

- [ ] `POST /api/v1/admin/upload` 返回含 `id` 的完整响应
- [ ] `local` 驱动文件存 `storage/app/public/uploads/`
- [ ] `uploads` 表仅存相对 `path`（无 `url` 列）
- [ ] API 响应 `url` 由 `PublicUrlGenerator` 按 config 动态拼接
- [ ] jpg/png/webp ≤2MB 校验生效
- [ ] `OssStorageDriver` S3 兼容实现可单测（fake disk）
- [ ] `ConfigStorageDriverResolver` 实现就绪，首期绑定 `HardcodedStorageDriverResolver`
- [ ] `php artisan test` 全部通过

**预估工时：** 0.5 天

---

## 10. 交付物清单

- [ ] `config/storage.php` + `.env.example` 补充 `STORAGE_PUBLIC_BASE_URL`
- [ ] `database/migrations/*_create_uploads_table.php`
- [ ] Domain / Application / Infrastructure / Http 分层代码
- [ ] `routes/api.php` 注册 admin upload 路由
- [ ] `AppServiceProvider` 绑定 Repository + Driver + Resolver
- [ ] `composer.json` 添加 `league/flysystem-aws-s3-v3`
- [ ] `docker/entrypoint.sh` 补充 `storage:link`
- [ ] 上述 6 个测试文件
