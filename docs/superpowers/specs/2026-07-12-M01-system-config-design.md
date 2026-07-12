# M01 — 系统配置管理 Design Spec

> **文档版本：** v1.0.0  
> **日期：** 2026-07-12  
> **依赖：** M00 后端 API 底座（已完成）  
> **后续依赖方：** M02 图片存储、M06 支付、M14 后台配置 UI

---

## 1. 目标

建立「配置与代码分离」的后端能力：业务配置（支付、存储、商城参数）存 `system_configs` 表，值 AES 加密，提供管理员批量读写 API。M02/M06 等业务模块通过仓储接口读取明文配置，不经 HTTP。

**非目标（M01 不做）：**
- 角色/权限检查（留 M03）
- 前端配置页面（留 M14）
- Redis 配置缓存（YAGNI，后续按需加）

---

## 2. 设计决策摘要

| 决策 | 选择 | 理由 |
|---|---|---|
| 架构 | 完整 DDD 四层 | 首个业务模块，建立范式供 M02–M16 复用 |
| 鉴权 | 仅 `auth:sanctum` | 角色检查留 M03，不阻塞 M01 |
| API 形态 | 批量 GET/PUT | 匹配 M14 分组表单，减少往返 |
| 敏感标识 | DB `is_sensitive` 字段 | 规则清晰、可测试、Seeder 即文档 |
| 加密 | Laravel `Crypt`（APP_KEY） | 原生支持，满足「换 key 无法解密」验收 |

---

## 3. 架构与分层

```
Http/
  Controllers/Admin/SystemConfigController.php
  Requests/Admin/UpdateSystemConfigsRequest.php
  Resources/Admin/SystemConfigResource.php

Application/SystemConfig/
  GetSystemConfigs/GetSystemConfigsHandler.php
  UpdateSystemConfigs/UpdateSystemConfigsHandler.php
  DTO/SystemConfigItemDto.php

Domain/SystemConfig/
  Entities/SystemConfig.php
  Repositories/SystemConfigRepositoryInterface.php
  Services/ConfigEncryptionInterface.php

Infrastructure/
  Persistence/Eloquent/EloquentSystemConfigRepository.php
  Persistence/Eloquent/Models/SystemConfigModel.php
  Encryption/LaravelConfigEncryption.php
```

### 数据流

**GET：**
```
Controller → GetSystemConfigsHandler → Repository::all()
  → decrypt each → mask sensitive → group by `group` → Resource
```

**PUT：**
```
Controller → UpdateSystemConfigsRequest (validate)
  → UpdateSystemConfigsHandler → skip value="****"
  → encrypt → Repository::upsert() → return refreshed list (GET shape)
```

**内部读取（M02/M06 等）：**
```
SystemConfigRepositoryInterface::findByKey(group, key) → decrypt → plain string
```

---

## 4. 数据模型

### Migration: `system_configs`

| 字段 | 类型 | 说明 |
|---|---|---|
| `id` | bigint PK | |
| `group` | varchar(50) | 分组：`app` / `payment` / `storage` / `order` |
| `key` | varchar(100) | 组内键名 |
| `value` | text | 加密密文 |
| `is_sensitive` | boolean | 是否敏感 |
| `description` | varchar(255) nullable | 中文说明 |
| `created_at` / `updated_at` | timestamps | |

**约束：** `UNIQUE(group, key)`

### Seeder 默认配置项

| group | key | 默认值 | is_sensitive | description |
|---|---|---|---|---|
| `app` | `name` | `内部下午茶` | false | 商城名称 |
| `order` | `auto_cancel_minutes` | `30` | false | 未支付自动取消（分钟） |
| `payment` | `provider` | `alipay_sandbox` | false | 支付渠道 |
| `payment` | `wechat.mch_id` | `` | true | 微信商户号 |
| `payment` | `wechat.api_key` | `` | true | 微信 API 密钥 |
| `payment` | `wechat.cert` | `` | true | 微信商户证书 |
| `payment` | `alipay.app_id` | `` | true | 支付宝 App ID |
| `payment` | `alipay.private_key` | `` | true | 支付宝私钥 |
| `storage` | `driver` | `local` | false | 存储驱动 |
| `storage` | `oss.bucket` | `` | true | OSS Bucket |
| `storage` | `oss.endpoint` | `` | true | OSS Endpoint |
| `storage` | `oss.access_key` | `` | true | OSS Access Key |
| `storage` | `oss.secret_key` | `` | true | OSS Secret Key |

Seeder 写入前对 value 加密；敏感项默认空字符串。

### 分组标签（API 响应用）

| group | label |
|---|---|
| `app` | 基础信息 |
| `payment` | 支付配置 |
| `storage` | 存储配置 |
| `order` | 订单配置 |

---

## 5. 加密与脱敏

- **算法：** `Crypt::encryptString()` / `decryptString()`（基于 `APP_KEY`）
- **范围：** 仅 `is_sensitive=true` 的 value 加密入库；非敏感项明文存储
- **脱敏规则：** GET 时 `is_sensitive=true` 且 value 非空 → 返回 `"****"`；空值返回 `""`
- **更新保护：** PUT 时 value 为 `"****"` → 跳过该项，保留原值
- **解密失败：** 抛出领域异常，API 返回 500 + 明确 message

---

## 6. API 契约

**路由前缀：** `/api/v1/admin/configs`  
**中间件：** `auth:sanctum`

### GET — 获取全部配置

响应 `data.groups[]` 结构：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "groups": [
      {
        "name": "app",
        "label": "基础信息",
        "items": [
          {
            "key": "name",
            "value": "内部下午茶",
            "is_sensitive": false,
            "description": "商城名称"
          }
        ]
      }
    ]
  }
}
```

### PUT — 批量更新配置

请求体：

```json
{
  "configs": [
    { "group": "app", "key": "name", "value": "内部晚餐" },
    { "group": "payment", "key": "wechat.mch_id", "value": "1234567890" }
  ]
}
```

校验：
- `configs` — required, array, min:1
- `configs.*.group` — required, string, in:app,payment,storage,order
- `configs.*.key` — required, string, max:100
- `configs.*.value` — required, string（允许空字符串）
- 未知 `(group, key)` 组合 → 422

响应：更新后的完整配置（同 GET 格式）

### 错误码

| HTTP | 场景 |
|---|---|
| 401 | 未提供/无效 Token |
| 422 | 验证失败（统一 `{ code: 422, data: { errors: {} } }`） |
| 500 | 解密失败（APP_KEY 不匹配） |

---

## 7. 测试计划

| 类型 | 文件 | 覆盖点 |
|---|---|---|
| Unit | `tests/Unit/Infrastructure/Encryption/LaravelConfigEncryptionTest.php` | 加解密往返；换 APP_KEY 解密失败 |
| Unit | `tests/Unit/Domain/SystemConfig/Entities/SystemConfigTest.php` | 实体行为 |
| Unit | `tests/Unit/Application/SystemConfig/GetSystemConfigsHandlerTest.php` | 敏感脱敏、非敏感明文 |
| Unit | `tests/Unit/Application/SystemConfig/UpdateSystemConfigsHandlerTest.php` | upsert、**** 跳过 |
| Feature | `tests/Feature/Admin/SystemConfigApiTest.php` | GET/PUT 链路、401、422、DB 密文 |

**完成门槛：**
```bash
docker compose exec backend php artisan test
```

---

## 8. 验收标准

- [ ] Migration + Seeder 可运行，默认配置项齐全
- [ ] 配置写入后可读取，数据库 `value` 列为密文（非明文）
- [ ] 更换 `APP_KEY` 后旧密文无法解密（测试覆盖）
- [ ] GET 敏感项返回 `****`，非敏感项返回明文
- [ ] PUT 传 `****` 不覆盖原敏感值
- [ ] 未认证请求返回 401
- [ ] `php artisan test` 全部通过

**预估工时：** 0.5 天

---

## 9. 交付物清单

- [ ] `database/migrations/*_create_system_configs_table.php`
- [ ] `database/seeders/SystemConfigSeeder.php`
- [ ] Domain / Application / Infrastructure / Http 分层代码
- [ ] `routes/api.php` 注册 admin configs 路由
- [ ] `AppServiceProvider` 绑定 Repository + Encryption 接口
- [ ] 上述 5 个测试文件
