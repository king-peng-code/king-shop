# M04 商品目录管理 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 实现分类与商品 Admin CRUD、App 端商品浏览 API，封面图 `upload_id` + `image_path` 双字段，无库存字段。

**Architecture:** 单一 Bounded Context `Catalog`，完整 DDD 四层。`Category` / `Product` 实体 + 仓储接口；Application Handler 编排；`ProductImageResolver` 负责 URL 生成；App 端通过可见性查询过滤下架商品与禁用分类。

**Tech Stack:** PHP 8.4 · Laravel 12 · PHPUnit 11 · SQLite `:memory:`（测试）

## Global Constraints

- PHP **8.4**（禁止 8.3 及以下及 8.5+）
- Laravel **12**（禁止其他主版本）
- API 前缀 `/api/v1/`
- 成功响应 `{ "code": 0, "message": "ok", "data": {} }`
- 422 验证错误 `{ "code": 422, "message": "...", "data": { "errors": {} } }`
- 业务异常 `BusinessException` → `{ "code": <businessCode>, "message": "..." }`
- DDD 分层：Domain / Application / Infrastructure / Http
- TDD：先写失败测试，再实现
- 完成门槛：`./scripts/docker-test.sh` 全部通过

---

### Task 1: Migrations、Models、Factories

**Files:**
- Create: `backend/database/migrations/2026_07_12_130000_create_categories_table.php`
- Create: `backend/database/migrations/2026_07_12_130100_create_products_table.php`
- Create: `backend/app/Infrastructure/Persistence/Eloquent/Models/CategoryModel.php`
- Create: `backend/app/Infrastructure/Persistence/Eloquent/Models/ProductModel.php`
- Create: `backend/database/factories/CategoryFactory.php`
- Create: `backend/database/factories/ProductFactory.php`

**Interfaces:**
- Produces: `CategoryModel` with `products()` HasMany relation
- Produces: `ProductModel` with `category()` BelongsTo relation
- Produces: `CategoryFactory` states: `disabled()`
- Produces: `ProductFactory` states: `onSale()`, `offSale()`

- [ ] **Step 1: Create categories migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->integer('sort')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
```

- [ ] **Step 2: Create products migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories');
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price');
            $table->foreignId('upload_id')->nullable()->constrained('uploads')->nullOnDelete();
            $table->string('image_path', 500)->nullable();
            $table->string('status', 20)->default('off_sale');
            $table->integer('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

- [ ] **Step 3: Create CategoryModel**

```php
<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryModel extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected $table = 'categories';

    protected $fillable = ['name', 'sort', 'status'];

    public function products(): HasMany
    {
        return $this->hasMany(ProductModel::class, 'category_id');
    }
}
```

- [ ] **Step 4: Create ProductModel**

```php
<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductModel extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'category_id', 'name', 'description', 'price',
        'upload_id', 'image_path', 'status', 'sort',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(CategoryModel::class, 'category_id');
    }
}
```

- [ ] **Step 5: Create factories**

```php
// CategoryFactory.php
public function definition(): array
{
    return [
        'name' => fake()->word(),
        'sort' => 0,
        'status' => 'active',
    ];
}

public function disabled(): static
{
    return $this->state(['status' => 'disabled']);
}
```

```php
// ProductFactory.php
public function definition(): array
{
    return [
        'category_id' => CategoryModel::factory(),
        'name' => fake()->words(2, true),
        'description' => fake()->sentence(),
        'price' => 1500,
        'upload_id' => null,
        'image_path' => null,
        'status' => 'off_sale',
        'sort' => 0,
    ];
}

public function onSale(): static
{
    return $this->state(['status' => 'on_sale']);
}
```

- [ ] **Step 6: Run migration**

Run: `docker compose exec backend php artisan migrate --env=testing`
Expected: categories + products tables created

- [ ] **Step 7: Commit**

```bash
git add backend/database/migrations/2026_07_12_130000_create_categories_table.php \
        backend/database/migrations/2026_07_12_130100_create_products_table.php \
        backend/app/Infrastructure/Persistence/Eloquent/Models/CategoryModel.php \
        backend/app/Infrastructure/Persistence/Eloquent/Models/ProductModel.php \
        backend/database/factories/CategoryFactory.php \
        backend/database/factories/ProductFactory.php
git commit -m "feat(M04): add categories and products migrations"
```

---

### Task 2: Domain 值对象、实体、接口、异常

**Files:**
- Create: `backend/app/Domain/Catalog/ValueObjects/CategoryStatus.php`
- Create: `backend/app/Domain/Catalog/ValueObjects/ProductStatus.php`
- Create: `backend/app/Domain/Catalog/Entities/Category.php`
- Create: `backend/app/Domain/Catalog/Entities/Product.php`
- Create: `backend/app/Domain/Catalog/Repositories/CategoryRepositoryInterface.php`
- Create: `backend/app/Domain/Catalog/Repositories/ProductRepositoryInterface.php`
- Create: `backend/app/Domain/Catalog/Exceptions/CategoryNotFoundException.php`
- Create: `backend/app/Domain/Catalog/Exceptions/ProductNotFoundException.php`
- Create: `backend/app/Domain/Catalog/Exceptions/CategoryHasProductsException.php`
- Create: `backend/app/Domain/Catalog/Exceptions/UploadNotFoundException.php`
- Test: `backend/tests/Unit/Domain/Catalog/ValueObjects/CategoryStatusTest.php`
- Test: `backend/tests/Unit/Domain/Catalog/ValueObjects/ProductStatusTest.php`

**Interfaces:**
- Produces: `CategoryStatus::ACTIVE`, `CategoryStatus::DISABLED`, `isActive(): bool`
- Produces: `ProductStatus::ON_SALE`, `ProductStatus::OFF_SALE`, `isOnSale(): bool`
- Produces: `Category` entity: `id, name, sort, status`
- Produces: `Product` entity: `id, categoryId, name, description, price, uploadId, imagePath, status, sort, categoryName?`
- Produces: `CategoryRepositoryInterface`:
  - `findById(int $id): ?Category`
  - `save(Category $category): Category`
  - `delete(int $id): void`
  - `listAll(): array{items: Category[]}`
  - `listActive(): array{items: Category[]}`
  - `countProducts(int $categoryId): int`
- Produces: `ProductRepositoryInterface`:
  - `findById(int $id): ?Product`
  - `save(Product $product): Product`
  - `searchAdmin(?int $categoryId, ?string $status, string $keyword, int $page, int $perPage): array{items: Product[], total: int}`
  - `searchVisible(?int $categoryId, int $page, int $perPage): array{items: Product[], total: int}`
  - `findVisibleById(int $id): ?Product`

- [ ] **Step 1: Write failing CategoryStatusTest**

```php
#[Test]
public function active_status_is_active(): void
{
    $this->assertTrue(CategoryStatus::active()->isActive());
}

#[Test]
public function disabled_status_is_not_active(): void
{
    $this->assertFalse(CategoryStatus::disabled()->isActive());
}
```

- [ ] **Step 2: Implement value objects, entities, exceptions**

```php
// CategoryStatus.php
final class CategoryStatus
{
    public const ACTIVE = 'active';
    public const DISABLED = 'disabled';

    private function __construct(public readonly string $value) {}

    public static function active(): self { return new self(self::ACTIVE); }
    public static function disabled(): self { return new self(self::DISABLED); }
    public static function fromString(string $value): self { return new self($value); }
    public function isActive(): bool { return $this->value === self::ACTIVE; }
}
```

```php
// CategoryHasProductsException.php
class CategoryHasProductsException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(40901, '分类下存在商品，无法删除', 409);
    }
}
```

- [ ] **Step 3: Run unit tests — PASS**

Run: `docker compose exec backend php artisan test --filter=CategoryStatusTest`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git commit -m "feat(M04): add Catalog domain layer"
```

---

### Task 3: 扩展 UploadRepository + Eloquent 仓储实现

**Files:**
- Modify: `backend/app/Domain/Storage/Repositories/UploadRepositoryInterface.php`
- Modify: `backend/app/Infrastructure/Persistence/Eloquent/EloquentUploadRepository.php`
- Create: `backend/app/Infrastructure/Persistence/Eloquent/EloquentCategoryRepository.php`
- Create: `backend/app/Infrastructure/Persistence/Eloquent/EloquentProductRepository.php`
- Modify: `backend/app/Providers/AppServiceProvider.php`
- Test: `backend/tests/Feature/Infrastructure/EloquentUploadRepositoryFindByIdTest.php`
- Test: `backend/tests/Feature/Infrastructure/EloquentCategoryRepositoryTest.php`
- Test: `backend/tests/Feature/Infrastructure/EloquentProductRepositoryTest.php`

**Interfaces:**
- Consumes: `CategoryModel`, `ProductModel`, Domain entities/VOs
- Produces: `UploadRepositoryInterface::findById(int $id): ?Upload`
- Produces: `EloquentCategoryRepository implements CategoryRepositoryInterface`
- Produces: `EloquentProductRepository implements ProductRepositoryInterface`
- Produces: DI binds in `AppServiceProvider`

- [ ] **Step 1: Write failing findById test**

```php
#[Test]
public function find_by_id_returns_upload_entity(): void
{
    $model = UploadModel::factory()->create(['path' => 'uploads/2026/07/test.jpg']);
    $repo = app(UploadRepositoryInterface::class);
    $upload = $repo->findById($model->id);
    $this->assertNotNull($upload);
    $this->assertSame('uploads/2026/07/test.jpg', $upload->path);
}
```

- [ ] **Step 2: Add findById to UploadRepository**

```php
public function findById(int $id): ?Upload
{
    $model = UploadModel::query()->find($id);
    return $model ? $this->toEntity($model) : null;
}
```

- [ ] **Step 3: Write failing visible products repository test**

```php
#[Test]
public function search_visible_excludes_off_sale_products(): void
{
    $category = CategoryModel::factory()->create();
    ProductModel::factory()->onSale()->create(['category_id' => $category->id, 'name' => '可见']);
    ProductModel::factory()->create(['category_id' => $category->id, 'status' => 'off_sale', 'name' => '隐藏']);

    $repo = app(ProductRepositoryInterface::class);
    $result = $repo->searchVisible(null, 1, 20);

    $this->assertSame(1, $result['total']);
    $this->assertSame('可见', $result['items'][0]->name);
}

#[Test]
public function search_visible_excludes_disabled_category_products(): void
{
    $category = CategoryModel::factory()->disabled()->create();
    ProductModel::factory()->onSale()->create(['category_id' => $category->id]);

    $repo = app(ProductRepositoryInterface::class);
    $result = $repo->searchVisible(null, 1, 20);

    $this->assertSame(0, $result['total']);
}
```

- [ ] **Step 4: Implement EloquentCategoryRepository and EloquentProductRepository**

`searchVisible` 查询条件：
```php
$query = ProductModel::query()
    ->join('categories', 'categories.id', '=', 'products.category_id')
    ->where('products.status', 'on_sale')
    ->where('categories.status', 'active')
    ->select('products.*', 'categories.name as category_name');
```

- [ ] **Step 5: Register bindings**

```php
use App\Domain\Catalog\Repositories\CategoryRepositoryInterface;
use App\Domain\Catalog\Repositories\ProductRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\EloquentCategoryRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentProductRepository;

$this->app->bind(CategoryRepositoryInterface::class, EloquentCategoryRepository::class);
$this->app->bind(ProductRepositoryInterface::class, EloquentProductRepository::class);
```

- [ ] **Step 6: Run tests — PASS**

- [ ] **Step 7: Commit**

```bash
git commit -m "feat(M04): add Catalog repositories and extend UploadRepository"
```

---

### Task 4: ProductImageResolver + Category Handlers

**Files:**
- Create: `backend/app/Application/Catalog/Services/ProductImageResolver.php`
- Create: `backend/app/Application/Catalog/DTO/CreateCategoryCommand.php`
- Create: `backend/app/Application/Catalog/DTO/UpdateCategoryCommand.php`
- Create: `backend/app/Application/Catalog/CreateCategory/CreateCategoryHandler.php`
- Create: `backend/app/Application/Catalog/UpdateCategory/UpdateCategoryHandler.php`
- Create: `backend/app/Application/Catalog/DeleteCategory/DeleteCategoryHandler.php`
- Create: `backend/app/Application/Catalog/ListCategories/ListCategoriesHandler.php`
- Create: `backend/app/Application/Catalog/GetCategory/GetCategoryHandler.php`
- Test: `backend/tests/Unit/Application/Catalog/ProductImageResolverTest.php`
- Test: `backend/tests/Unit/Application/Catalog/DeleteCategoryHandlerTest.php`

**Interfaces:**
- Produces: `ProductImageResolver::resolveUrl(?string $imagePath, ?int $uploadId): ?string`
- Produces: `DeleteCategoryHandler::handle(int $id): void` — throws `CategoryHasProductsException` if count > 0

- [ ] **Step 1: Write failing ProductImageResolverTest**

```php
#[Test]
public function resolves_url_with_local_disk_by_default(): void
{
    config(['storage.public_base_url' => 'http://localhost:8000']);
    $resolver = app(ProductImageResolver::class);
    $url = $resolver->resolveUrl('uploads/2026/07/a.jpg', null);
    $this->assertSame('http://localhost:8000/storage/uploads/2026/07/a.jpg', $url);
}

#[Test]
public function resolves_url_using_upload_disk_when_upload_id_present(): void
{
    $upload = UploadModel::factory()->create([
        'path' => 'uploads/2026/07/b.jpg',
        'disk' => 'local',
    ]);
    config(['storage.public_base_url' => 'http://localhost:8000']);
    $resolver = app(ProductImageResolver::class);
    $url = $resolver->resolveUrl('uploads/2026/07/b.jpg', $upload->id);
    $this->assertStringContainsString('/storage/uploads/2026/07/b.jpg', $url);
}
```

- [ ] **Step 2: Implement ProductImageResolver and Category handlers**

- [ ] **Step 3: Write failing DeleteCategoryHandlerTest**

```php
#[Test]
public function delete_category_with_products_throws(): void
{
    $category = CategoryModel::factory()->create();
    ProductModel::factory()->create(['category_id' => $category->id]);

    $this->expectException(CategoryHasProductsException::class);
    app(DeleteCategoryHandler::class)->handle($category->id);
}
```

- [ ] **Step 4: Run tests — PASS**

- [ ] **Step 5: Commit**

```bash
git commit -m "feat(M04): add ProductImageResolver and Category handlers"
```

---

### Task 5: Product Handlers

**Files:**
- Create: `backend/app/Application/Catalog/DTO/CreateProductCommand.php`
- Create: `backend/app/Application/Catalog/DTO/UpdateProductCommand.php`
- Create: `backend/app/Application/Catalog/DTO/ProductListQuery.php`
- Create: `backend/app/Application/Catalog/CreateProduct/CreateProductHandler.php`
- Create: `backend/app/Application/Catalog/UpdateProduct/UpdateProductHandler.php`
- Create: `backend/app/Application/Catalog/ListProducts/ListProductsHandler.php`
- Create: `backend/app/Application/Catalog/GetProduct/GetProductHandler.php`
- Create: `backend/app/Application/Catalog/ListVisibleProducts/ListVisibleProductsHandler.php`
- Create: `backend/app/Application/Catalog/GetVisibleProduct/GetVisibleProductHandler.php`
- Create: `backend/app/Application/Catalog/ListVisibleCategories/ListVisibleCategoriesHandler.php`
- Test: `backend/tests/Unit/Application/Catalog/CreateProductHandlerTest.php`

**Interfaces:**
- Produces: `CreateProductHandler::handle(CreateProductCommand $command): Product`
  - 若 `uploadId` 非空：`UploadRepository::findById` → 不存在抛 `UploadNotFoundException` → 同步 `imagePath`
- Produces: `GetVisibleProductHandler::handle(int $id): Product` — 不可见抛 `ProductNotFoundException`

- [ ] **Step 1: Write failing CreateProductHandlerTest**

```php
#[Test]
public function create_product_syncs_image_path_from_upload_id(): void
{
    $category = CategoryModel::factory()->create();
    $upload = UploadModel::factory()->create(['path' => 'uploads/2026/07/cover.jpg']);

    $product = app(CreateProductHandler::class)->handle(
        new CreateProductCommand(
            categoryId: $category->id,
            name: '拿铁',
            description: null,
            price: 1500,
            uploadId: $upload->id,
            imagePath: null,
            status: ProductStatus::onSale(),
            sort: 0,
        ),
    );

    $this->assertSame($upload->id, $product->uploadId);
    $this->assertSame('uploads/2026/07/cover.jpg', $product->imagePath);
}

#[Test]
public function create_product_with_invalid_upload_id_throws(): void
{
    $category = CategoryModel::factory()->create();

    $this->expectException(UploadNotFoundException::class);
    app(CreateProductHandler::class)->handle(
        new CreateProductCommand(
            categoryId: $category->id,
            name: '拿铁',
            description: null,
            price: 1500,
            uploadId: 99999,
            imagePath: null,
            status: ProductStatus::offSale(),
            sort: 0,
        ),
    );
}
```

- [ ] **Step 2: Implement all Product handlers**

- [ ] **Step 3: Run tests — PASS**

- [ ] **Step 4: Commit**

```bash
git commit -m "feat(M04): add Product application handlers"
```

---

### Task 6: Admin Http 层 + Feature 测试

**Files:**
- Create: `backend/app/Http/Controllers/Admin/CategoryController.php`
- Create: `backend/app/Http/Controllers/Admin/ProductController.php`
- Create: `backend/app/Http/Requests/Admin/CreateCategoryRequest.php`
- Create: `backend/app/Http/Requests/Admin/UpdateCategoryRequest.php`
- Create: `backend/app/Http/Requests/Admin/CreateProductRequest.php`
- Create: `backend/app/Http/Requests/Admin/UpdateProductRequest.php`
- Create: `backend/app/Http/Resources/Admin/CategoryResource.php`
- Create: `backend/app/Http/Resources/Admin/ProductResource.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Admin/CategoryApiTest.php`
- Test: `backend/tests/Feature/Admin/ProductApiTest.php`

**Interfaces:**
- Consumes: all Category/Product handlers, `ProductImageResolver`
- Produces: Admin routes under `/admin/categories`, `/admin/products`
- Produces: `Admin\ProductResource` includes `image_url` via resolver

- [ ] **Step 1: Write failing CategoryApiTest**

```php
#[Test]
public function admin_can_create_category(): void
{
    $response = $this->withToken($this->adminToken())
        ->postJson('/api/v1/admin/categories', ['name' => '饮品', 'sort' => 1]);

    $response->assertCreated()
        ->assertJsonPath('data.name', '饮品')
        ->assertJsonPath('data.status', 'active');
}

#[Test]
public function cannot_delete_category_with_products(): void
{
    $category = CategoryModel::factory()->create();
    ProductModel::factory()->create(['category_id' => $category->id]);

    $this->withToken($this->adminToken())
        ->deleteJson("/api/v1/admin/categories/{$category->id}")
        ->assertStatus(409)
        ->assertJsonPath('code', 40901);
}

#[Test]
public function can_delete_empty_category(): void
{
    $category = CategoryModel::factory()->create();

    $this->withToken($this->adminToken())
        ->deleteJson("/api/v1/admin/categories/{$category->id}")
        ->assertOk();

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
}
```

- [ ] **Step 2: Write failing ProductApiTest**

```php
#[Test]
public function admin_can_create_product_with_upload_id(): void
{
    $category = CategoryModel::factory()->create();
    $upload = UploadModel::factory()->create(['path' => 'uploads/2026/07/p.jpg']);

    $response = $this->withToken($this->adminToken())
        ->postJson('/api/v1/admin/products', [
            'category_id' => $category->id,
            'name' => '拿铁',
            'price' => 1500,
            'upload_id' => $upload->id,
            'status' => 'on_sale',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', '拿铁')
        ->assertJsonPath('data.price', 1500)
        ->assertJsonPath('data.image_path', 'uploads/2026/07/p.jpg');
}

#[Test]
public function admin_products_have_no_delete_route(): void
{
    $product = ProductModel::factory()->create();

    $this->withToken($this->adminToken())
        ->deleteJson("/api/v1/admin/products/{$product->id}")
        ->assertNotFound();
}
```

- [ ] **Step 3: Implement Admin controllers, requests, resources, routes**

`routes/api.php` 追加：
```php
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;

Route::middleware(['auth:sanctum', 'password.changed', 'admin'])->prefix('admin')->group(function (): void {
    // ...existing...
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('products', ProductController::class)->except(['destroy']);
});
```

`ProductController` 不实现 `destroy` 方法。

- [ ] **Step 4: Run tests — PASS**

- [ ] **Step 5: Commit**

```bash
git commit -m "feat(M04): add Admin category and product APIs"
```

---

### Task 7: App Http 层 + Feature 测试

**Files:**
- Create: `backend/app/Http/Controllers/Catalog/CategoryController.php`
- Create: `backend/app/Http/Controllers/Catalog/ProductController.php`
- Create: `backend/app/Http/Resources/Catalog/CategoryResource.php`
- Create: `backend/app/Http/Resources/Catalog/ProductResource.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Catalog/ProductCatalogApiTest.php`

**Interfaces:**
- Produces: `GET /api/v1/categories`, `GET /api/v1/products`, `GET /api/v1/products/{id}`
- Middleware: `auth:sanctum`, `password.changed`

- [ ] **Step 1: Write failing ProductCatalogApiTest**

```php
private function employeeToken(): string
{
    $user = UserModel::factory()->create(['role' => 'employee', 'must_change_password' => false]);
    return $user->createToken('test')->plainTextToken;
}

#[Test]
public function employee_can_list_visible_products(): void
{
    $category = CategoryModel::factory()->create();
    ProductModel::factory()->onSale()->create([
        'category_id' => $category->id,
        'name' => '拿铁',
        'price' => 1500,
    ]);
    ProductModel::factory()->create([
        'category_id' => $category->id,
        'status' => 'off_sale',
        'name' => '下架商品',
    ]);

    $this->withToken($this->employeeToken())
        ->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.name', '拿铁');
}

#[Test]
public function off_sale_product_returns_404_on_detail(): void
{
    $product = ProductModel::factory()->create(['status' => 'off_sale']);

    $this->withToken($this->employeeToken())
        ->getJson("/api/v1/products/{$product->id}")
        ->assertNotFound();
}

#[Test]
public function disabled_category_products_not_visible(): void
{
    $category = CategoryModel::factory()->disabled()->create();
    $product = ProductModel::factory()->onSale()->create(['category_id' => $category->id]);

    $this->withToken($this->employeeToken())
        ->getJson("/api/v1/products/{$product->id}")
        ->assertNotFound();
}

#[Test]
public function products_filter_by_category_id(): void
{
    $cat1 = CategoryModel::factory()->create();
    $cat2 = CategoryModel::factory()->create();
    ProductModel::factory()->onSale()->create(['category_id' => $cat1->id, 'name' => 'A']);
    ProductModel::factory()->onSale()->create(['category_id' => $cat2->id, 'name' => 'B']);

    $this->withToken($this->employeeToken())
        ->getJson("/api/v1/products?category_id={$cat1->id}")
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.name', 'A');
}

#[Test]
public function unauthenticated_cannot_access_products(): void
{
    $this->getJson('/api/v1/products')->assertUnauthorized();
}
```

- [ ] **Step 2: Implement App controllers, resources, routes**

```php
Route::middleware(['auth:sanctum', 'password.changed'])->group(function (): void {
    Route::get('/categories', [CatalogCategoryController::class, 'index']);
    Route::get('/products', [CatalogProductController::class, 'index']);
    Route::get('/products/{product}', [CatalogProductController::class, 'show']);
});
```

- [ ] **Step 3: Run tests — PASS**

- [ ] **Step 4: Commit**

```bash
git commit -m "feat(M04): add App catalog browse APIs"
```

---

### Task 8: 收尾 — 异常注册、UploadFactory、全量测试、记录

**Files:**
- Create: `backend/database/factories/UploadFactory.php`（若不存在）
- Modify: `backend/app/Exceptions/ApiExceptionHandler.php`（如需显式注册，BusinessException 已通用处理）
- Create: `docs/superpowers/records/2026-07-12-M04-product-catalog.md`
- Modify: `docs/superpowers/specs/2026-07-12-internal-mall-design.md`（M04 状态 → 完成）

- [ ] **Step 1: Ensure UploadFactory exists for tests**

```php
// UploadFactory.php
public function definition(): array
{
    return [
        'original_name' => 'test.jpg',
        'path' => 'uploads/2026/07/'.fake()->uuid().'.jpg',
        'disk' => 'local',
        'mime_type' => 'image/jpeg',
        'size' => 1024,
        'uploaded_by' => null,
    ];
}
```

- [ ] **Step 2: Run full test suite**

Run: `./scripts/docker-test.sh`
Expected: ALL PASS（含 M00–M03 回归）

- [ ] **Step 3: Write execution record**

`docs/superpowers/records/2026-07-12-M04-product-catalog.md` 含验收 checklist。

- [ ] **Step 4: Update module status in internal-mall-design.md**

M04 状态 → ✅ 完成

- [ ] **Step 5: Commit**

```bash
git commit -m "feat(M04): complete product catalog module with tests and records"
```

---

## Plan Self-Review

| Spec 要求 | 对应 Task |
|-----------|-----------|
| categories + products migrations | Task 1 |
| upload_id + image_path 双字段 | Task 1, 5 |
| Admin 分类/商品 CRUD（无商品 DELETE） | Task 4, 6 |
| App 浏览 API | Task 7 |
| 下架/禁用不可见 | Task 3, 7 |
| 价格分存储 | Task 1, 6 |
| image_url 动态生成 | Task 4, 6, 7 |
| 无库存字段 | Task 1（migration 无 stock） |
| UploadRepository findById | Task 3 |
| 全量测试 | Task 8 |

无 placeholder。类型与接口在各 Task Interfaces 块一致。
