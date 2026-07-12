# M04 — 商品目录管理 Design Spec

> **文档版本：** v1.0.0  
> **日期：** 2026-07-12  
> **依赖：** M00 后端 API 底座（已完成）、M01 系统配置（已完成）、M02 图片存储（已完成）、M03 认证员工（已完成）  
> **后续依赖方：** M05 订单系统、M08 App 商品浏览、M12 后台商品 UI

---

## 1. 目标

建立商品目录管理能力：管理员维护分类与商品（上下架），员工 App 浏览在售商品。

**非目标（M04 不做）：**
- 前端 UI（留 M12）
- 库存字段与库存管控（下午茶由 HR 手动统筹，不做库存扣减）
- `upload_id` / `image_path` 一致性定时扫描（后续按需）
- 商品物理删除（仅上下架）
- 分类有商品时物理删除

---

## 2. 设计决策摘要

| 决策 | 选择 | 理由 |
|---|---|---|
| 架构 | 单一 Bounded Context `Catalog`，完整 DDD 四层 | 与 M01/M02/M03 范式一致 |
| 封面图 | **`upload_id` + `image_path` 双字段** | `path` 快速拼 URL；`upload_id` 追溯历史文件 |
| URL 生成 | `image_path` + `uploads.disk`（有 upload_id 时查 disk，否则默认 `local`） | 不额外存 disk 字段，保持双字段约定 |
| 分类删除 | **仅无商品时可物理删除**；有商品只能 `status=disabled` | 保留历史数据 |
| 分类禁用 | App 端该分类及下属商品均不可见 | 规则简单 |
| 商品删除 | **无 DELETE 接口**，仅 `on_sale` / `off_sale` | 订单快照不依赖商品仍存在 |
| 库存 | **不做** | 业务由 HR 手动统筹 |
| App 鉴权 | `auth:sanctum` + `password.changed` | 内部员工登录后浏览 |
| Admin 鉴权 | `auth:sanctum` + `password.changed` + `admin` | 与 M03 一致 |

---

## 3. 架构与分层

```
Http/
  Controllers/Admin/CategoryController.php
  Controllers/Admin/ProductController.php
  Controllers/Catalog/CategoryController.php      # App 端
  Controllers/Catalog/ProductController.php       # App 端
  Requests/Admin/CreateCategoryRequest.php
  Requests/Admin/UpdateCategoryRequest.php
  Requests/Admin/CreateProductRequest.php
  Requests/Admin/UpdateProductRequest.php
  Resources/Admin/CategoryResource.php
  Resources/Admin/ProductResource.php
  Resources/Catalog/CategoryResource.php
  Resources/Catalog/ProductResource.php

Application/Catalog/
  CreateCategory/CreateCategoryHandler.php
  UpdateCategory/UpdateCategoryHandler.php
  DeleteCategory/DeleteCategoryHandler.php
  ListCategories/ListCategoriesHandler.php
  GetCategory/GetCategoryHandler.php
  CreateProduct/CreateProductHandler.php
  UpdateProduct/UpdateProductHandler.php
  ListProducts/ListProductsHandler.php
  GetProduct/GetProductHandler.php
  ListVisibleCategories/ListVisibleCategoriesHandler.php
  ListVisibleProducts/ListVisibleProductsHandler.php
  GetVisibleProduct/GetVisibleProductHandler.php
  Services/ProductImageResolver.php
  DTO/CreateCategoryCommand.php
  DTO/UpdateCategoryCommand.php
  DTO/CreateProductCommand.php
  DTO/UpdateProductCommand.php
  DTO/ProductListQuery.php

Domain/Catalog/
  Entities/Category.php
  Entities/Product.php
  ValueObjects/CategoryStatus.php
  ValueObjects/ProductStatus.php
  Repositories/CategoryRepositoryInterface.php
  Repositories/ProductRepositoryInterface.php
  Exceptions/CategoryNotFoundException.php
  Exceptions/ProductNotFoundException.php
  Exceptions/CategoryHasProductsException.php
  Exceptions/UploadNotFoundException.php

Infrastructure/
  Persistence/Eloquent/EloquentCategoryRepository.php
  Persistence/Eloquent/EloquentProductRepository.php
  Persistence/Eloquent/Models/CategoryModel.php
  Persistence/Eloquent/Models/ProductModel.php
```

### 数据流（创建商品）

```
ProductController → CreateProductRequest
  → CreateProductHandler
      → 若 upload_id 有值：UploadRepository::findById() → 同步 image_path
      → ProductRepository::save(Product)
  → Admin\ProductResource（image_url 由 ProductImageResolver 生成）
```

---

## 4. 数据模型

### 4.1 Migration: `categories`

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | bigint PK | |
| `name` | varchar(100) | 分类名 |
| `sort` | int, default 0 | 排序升序 |
| `status` | varchar(20), default `active` | `active` / `disabled` |
| `created_at` / `updated_at` | timestamps | |

### 4.2 Migration: `products`

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | bigint PK | |
| `category_id` | bigint FK → categories | |
| `name` | varchar(200) | |
| `description` | text, nullable | |
| `price` | unsignedBigInteger | **分**，如 1500 = ¥15.00 |
| `upload_id` | bigint nullable FK → uploads | 追溯上传记录 |
| `image_path` | varchar(500), nullable | 相对路径，快速拼 URL |
| `status` | varchar(20), default `off_sale` | `on_sale` / `off_sale` |
| `sort` | int, default 0 | 排序升序 |
| `created_at` / `updated_at` | timestamps | |

**双字段写入规则：**
- 传 `upload_id` 时：从 `uploads` 读取 `path` 写入 `image_path`（保持同步）
- 仅传 `image_path` 时：`upload_id` 可为 null
- 后续一致性扫描任务（非本期）：比对 `upload_id` 对应 path 与 `image_path`

### 4.3 值对象

**CategoryStatus：** `active`, `disabled` — `isActive(): bool`

**ProductStatus：** `on_sale`, `off_sale` — `isOnSale(): bool`

---

## 5. App 可见性规则

App 端商品可见条件（同时满足）：
- `product.status = on_sale`
- 所属 `category.status = active`

App 分类列表：仅 `status = active`，按 `sort` 升序。

App 商品详情：不满足可见条件 → `ProductNotFoundException`（404）。

---

## 6. API 设计

### 6.1 Admin — 分类（`/api/v1/admin/categories`）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/admin/categories` | 列表（含 disabled），按 sort 升序 |
| POST | `/admin/categories` | 创建 |
| GET | `/admin/categories/{id}` | 详情 |
| PUT | `/admin/categories/{id}` | 更新（含 status） |
| DELETE | `/admin/categories/{id}` | 仅无关联商品时可删 |

**中间件：** `auth:sanctum` + `password.changed` + `admin`

#### POST `/admin/categories`

```json
{ "name": "饮品", "sort": 1, "status": "active" }
```

#### DELETE 错误

| 场景 | HTTP | code | message |
|------|------|------|---------|
| 分类下有商品 | 409 | 40901 | 分类下存在商品，无法删除 |

---

### 6.2 Admin — 商品（`/api/v1/admin/products`）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/admin/products` | 分页列表 |
| POST | `/admin/products` | 创建 |
| GET | `/admin/products/{id}` | 详情 |
| PUT | `/admin/products/{id}` | 更新（含上下架） |

**Query（GET）：** `?category_id=&status=on_sale&keyword=&page=1&per_page=20`

**keyword 匹配：** `name LIKE %kw%`

#### POST `/admin/products`

```json
{
  "category_id": 1,
  "name": "拿铁",
  "description": "热饮",
  "price": 1500,
  "upload_id": 3,
  "status": "on_sale",
  "sort": 0
}
```

- `price`：正整数，单位分
- `upload_id`：可选；有值时自动同步 `image_path`
- `image_path`：可选；无 `upload_id` 时可直接传

**无 DELETE 接口。**

---

### 6.3 App — 分类与商品

| 方法 | 路径 | 鉴权 | 说明 |
|------|------|------|------|
| GET | `/categories` | sanctum + password.changed | 仅 active 分类 |
| GET | `/products` | sanctum + password.changed | 仅可见商品；`?category_id=&page=&per_page=` |
| GET | `/products/{id}` | sanctum + password.changed | 可见商品详情 |

#### GET `/products` Response

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "items": [
      {
        "id": 1,
        "name": "拿铁",
        "description": "热饮",
        "price": 1500,
        "image_url": "http://localhost:8000/storage/uploads/2026/07/abc.jpg",
        "category_id": 1,
        "category_name": "饮品",
        "status": "on_sale"
      }
    ],
    "meta": { "total": 1, "page": 1, "per_page": 20 }
  }
}
```

---

## 7. 图片 URL 解析

`ProductImageResolver`（Application 服务）：

```php
public function resolveUrl(?string $imagePath, ?int $uploadId): ?string
{
    if ($imagePath === null || $imagePath === '') {
        return null;
    }
    $disk = 'local';
    if ($uploadId !== null) {
        $upload = $this->uploadRepository->findById($uploadId);
        if ($upload !== null) {
            $disk = $upload->disk;
        }
    }
    return $this->urlGenerator->generate($imagePath, $disk);
}
```

---

## 8. M02 扩展

`UploadRepositoryInterface` 新增：

```php
public function findById(int $id): ?Upload;
```

---

## 9. 测试计划

| 类型 | 覆盖 |
|------|------|
| Unit | `CategoryStatus`、`ProductStatus` |
| Unit | `ProductImageResolver` |
| Unit | `CreateProductHandler`（upload_id 同步 path） |
| Unit | `DeleteCategoryHandler`（有商品拒绝） |
| Feature | Admin 分类 CRUD |
| Feature | Admin 商品 CRUD（无 DELETE） |
| Feature | App 列表/详情可见性 |
| Feature | 下架商品、禁用分类 App 不可见 |
| Integration | `EloquentCategoryRepository`、`EloquentProductRepository` |

---

## 10. 验收标准

- [ ] 下架商品 App 不可见
- [ ] 禁用分类下商品 App 不可见
- [ ] 商品列表支持分类筛选、分页
- [ ] 价格为分（整数）存储与返回
- [ ] `image_url` 由 `image_path` + disk 动态生成
- [ ] 分类有商品时 DELETE 返回 409
- [ ] 无商品 DELETE 接口
- [ ] `./scripts/docker-test.sh` 全绿

---

## 11. 对总体 Spec 的偏离说明

| 总体 Spec | M04 调整 | 原因 |
|-----------|----------|------|
| `products.stock` 字段 | **移除** | HR 手动统筹，不做库存管控 |
| 库存扣减策略文档 | **不做** | 同上；M05 亦不做库存扣减 |
| `image_url` 单字段 | **`upload_id` + `image_path`** | 用户确认的双字段方案 |
