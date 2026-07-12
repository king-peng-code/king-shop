# M04 商品目录管理 — 执行与验收记录

> **模块：** M04  
> **日期：** 2026-07-12  
> **状态：** ✅ 完成  
> **关联计划：** [2026-07-12-M04-product-catalog.md](../plans/2026-07-12-M04-product-catalog.md)  
> **关联 Spec：** [2026-07-12-M04-product-catalog-design.md](../specs/2026-07-12-M04-product-catalog-design.md) v1.0.0

---

## 1. 执行摘要

| 交付项 | 状态 |
|--------|------|
| `categories` / `products` Migrations | ✅ |
| Domain / Application / Infrastructure / Http 分层 | ✅ |
| Admin 分类 CRUD（有商品时 DELETE 409） | ✅ |
| Admin 商品 CRUD（无 DELETE 接口） | ✅ |
| App `GET /categories`（仅 active 分类） | ✅ |
| App `GET /products` / `GET /products/{id}`（可见性过滤） | ✅ |
| `upload_id` + `image_path` 双字段 + `ProductImageResolver` | ✅ |
| `UploadRepository::findById`（M02 扩展） | ✅ |
| `UploadFactory`（测试支撑） | ✅ |
| 自动化测试 | ✅ 108 tests |
| `./scripts/docker-test.sh` 全绿 | ✅ |

---

## 2. 设计要点

- **封面图**：`upload_id` + `image_path` 双字段；有 `upload_id` 时自动同步 `image_path`
- **URL 生成**：`ProductImageResolver` 按 `image_path` + disk 动态生成 `image_url`
- **价格**：整数分存储，API 返回分
- **库存**：无 `stock` 字段（HR 手动统筹）
- **商品删除**：无 DELETE 接口，仅 `on_sale` / `off_sale`
- **分类删除**：无商品时可物理删除；有商品返回 409
- **App 可见性**：`on_sale` 商品 + `active` 分类；下架/禁用返回 404
- **鉴权**：Admin `sanctum + password.changed + admin`；App `sanctum + password.changed`

---

## 3. 验收清单

- [x] 下架商品 App 不可见（列表排除、详情 404）
- [x] 禁用分类下商品 App 不可见
- [x] 商品列表支持分类筛选、分页
- [x] 价格为分（整数）存储与返回
- [x] `image_url` 由 `image_path` + disk 动态生成
- [x] 分类有商品时 DELETE 返回 409
- [x] 无商品 DELETE 接口
- [x] App 仅返回 active 分类
- [x] `./scripts/docker-test.sh` 全绿

---

## 4. API 端点

### Admin（`auth:sanctum` + `password.changed` + `admin`）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET/POST/GET/PUT/DELETE | `/api/v1/admin/categories` | 分类 CRUD |
| GET/POST/GET/PUT | `/api/v1/admin/products` | 商品 CRUD（无 DELETE） |

### App（`auth:sanctum` + `password.changed`）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/v1/categories` | 仅 active 分类 |
| GET | `/api/v1/products` | 可见商品列表；`?category_id=&page=&per_page=` |
| GET | `/api/v1/products/{id}` | 可见商品详情 |

---

## 5. 测试覆盖

| 类型 | 文件 | 覆盖 |
|------|------|------|
| Unit | `CategoryStatusTest`, `ProductStatusTest` | 值对象 |
| Unit | `ProductImageResolverTest` | URL 解析 |
| Unit | `CreateProductHandlerTest` | upload_id 同步 path |
| Unit | `DeleteCategoryHandlerTest` | 有商品拒绝删除 |
| Feature | `CategoryApiTest` | Admin 分类 CRUD |
| Feature | `ProductApiTest` | Admin 商品 CRUD |
| Feature | `ProductCatalogApiTest` | App 分类/商品浏览与可见性 |
| Integration | `EloquentCategoryRepositoryTest` | 仓储 |
| Integration | `EloquentProductRepositoryTest` | 仓储 + 可见性查询 |

---

## 6. 验证命令

```bash
./scripts/dev-up.sh
./scripts/docker-test.sh
```

**结果（2026-07-12）：** 108 passed (229 assertions)
