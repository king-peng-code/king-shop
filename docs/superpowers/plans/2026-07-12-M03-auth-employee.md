# M03 用户认证与员工模型 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 实现手机号登录（Sanctum）、首次改密强制、员工 CRUD API，以及 `EnsureAdmin` / `EnsurePasswordChanged` 中间件保护全部 `/admin/*` 路由。

**Architecture:** 单一 Bounded Context `Identity`，完整 DDD 四层。`UserModel`（Infrastructure Eloquent）映射 Domain `User` 实体；Application Handler 编排登录/改密/员工 CRUD；Http 层 Controller 仅校验与响应。M01/M02 admin 路由追加 `EnsureAdmin` + `EnsurePasswordChanged`。

**Tech Stack:** PHP 8.4 · Laravel 12 · Laravel Sanctum · PHPUnit 11 · SQLite `:memory:`（测试）

## Global Constraints

- PHP **8.4**（禁止 8.3 及以下及 8.5+）
- Laravel **12**（禁止其他主版本）
- API 前缀 `/api/v1/`
- 成功响应 `{ "code": 0, "message": "ok", "data": {} }`
- 422 验证错误 `{ "code": 422, "message": "...", "data": { "errors": {} } }`
- 业务异常 `BusinessException` → `{ "code": <businessCode>, "message": "..." }`
- DDD 分层：Domain / Application / Infrastructure / Http
- TDD：先写失败测试，再实现
- 完成门槛：`./scripts/docker-test.sh` 或 `docker compose exec backend php artisan test` 全部通过

---

### Task 1: Migration、UserModel、config/identity.php

**Files:**
- Create: `backend/database/migrations/2026_07_12_120000_extend_users_table_for_identity.php`
- Create: `backend/app/Infrastructure/Persistence/Eloquent/Models/UserModel.php`
- Create: `backend/config/identity.php`
- Modify: `backend/config/auth.php`（model → UserModel）
- Modify: `backend/database/factories/UserFactory.php`
- Delete: `backend/app/Models/User.php`（迁移至 UserModel）
- Modify: `backend/tests/Feature/SanctumSetupTest.php`（use UserModel）

**Interfaces:**
- Produces: `UserModel` Authenticatable + HasApiTokens，`$fillable` 含全部 Identity 字段
- Produces: `config('identity.default_password')` = `'123456'`

- [ ] **Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('phone', 11)->unique()->after('email');
            $table->string('employee_no', 50)->nullable()->unique()->after('phone');
            $table->string('department', 100)->nullable()->after('employee_no');
            $table->string('role', 20)->default('employee')->after('department');
            $table->string('status', 20)->default('active')->after('role');
            $table->string('avatar')->nullable()->after('status');
            $table->boolean('must_change_password')->default(true)->after('avatar');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'employee_no', 'department', 'role',
                'status', 'avatar', 'must_change_password',
            ]);
            $table->string('email')->nullable(false)->change();
        });
    }
};
```

> 若 SQLite 测试环境不支持 `->change()`，migration 内对 SQLite 使用 `Schema::drop` + recreate 或 `doctrine/dbal`；与 M01 做法保持一致。

- [ ] **Step 2: Create UserModel**

```php
<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class UserModel extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'name', 'email', 'phone', 'employee_no', 'department',
        'role', 'status', 'avatar', 'must_change_password', 'password',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }
}
```

- [ ] **Step 3: Create config/identity.php**

```php
<?php

return [
    'default_password' => '123456',
    'super_admin_phone' => '13800000000',
    'super_admin_password' => 'admin123',
];
```

- [ ] **Step 4: Update auth.php, UserFactory, remove App\Models\User**

`config/auth.php`:
```php
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
// providers.users.model => UserModel::class
```

`UserFactory.php` — update model reference + default state:
```php
protected $model = UserModel::class;

public function definition(): array
{
    return [
        'name' => fake()->name(),
        'email' => null,
        'phone' => fake()->unique()->numerify('138########'),
        'employee_no' => fake()->unique()->bothify('E###'),
        'department' => fake()->word(),
        'role' => 'employee',
        'status' => 'active',
        'must_change_password' => false,
        'password' => static::$password ??= Hash::make('password'),
    ];
}
```

Add factory states: `admin()`, `superAdmin()`, `disabled()`, `mustChangePassword()`.

- [ ] **Step 5: Run migration**

Run: `docker compose exec backend php artisan migrate --env=testing`
Expected: users table extended

- [ ] **Step 6: Commit**

```bash
git add backend/database/migrations/2026_07_12_120000_extend_users_table_for_identity.php \
        backend/app/Infrastructure/Persistence/Eloquent/Models/UserModel.php \
        backend/config/identity.php backend/config/auth.php \
        backend/database/factories/UserFactory.php \
        backend/tests/Feature/SanctumSetupTest.php
git rm backend/app/Models/User.php
git commit -m "feat(M03): extend users table and add UserModel"
```

---

### Task 2: Domain 值对象、实体、接口、异常

**Files:**
- Create: `backend/app/Domain/Identity/ValueObjects/Role.php`
- Create: `backend/app/Domain/Identity/ValueObjects/UserStatus.php`
- Create: `backend/app/Domain/Identity/Entities/User.php`
- Create: `backend/app/Domain/Identity/Repositories/UserRepositoryInterface.php`
- Create: `backend/app/Domain/Identity/Exceptions/InvalidCredentialsException.php`
- Create: `backend/app/Domain/Identity/Exceptions/AccountDisabledException.php`
- Create: `backend/app/Domain/Identity/Exceptions/ForbiddenRoleAssignmentException.php`
- Test: `backend/tests/Unit/Domain/Identity/ValueObjects/RoleTest.php`
- Test: `backend/tests/Unit/Domain/Identity/Entities/UserTest.php`

**Interfaces:**
- Produces: `Role::EMPLOYEE`, `Role::ADMIN`, `Role::SUPER_ADMIN`
- Produces: `Role::isAdmin(): bool`, `Role::canAssignRole(Role $target): bool`
- Produces: `UserStatus::ACTIVE`, `UserStatus::DISABLED`
- Produces: `User` entity with `canLogin(): bool`, `isAdmin(): bool`
- Produces: `UserRepositoryInterface` with:
  - `findById(int $id): ?User`
  - `findByPhone(string $phone): ?User`
  - `save(User $user): User`
  - `search(string $keyword, int $page, int $perPage): array{items: User[], total: int}`

- [ ] **Step 1: Write failing RoleTest**

```php
#[Test]
public function super_admin_can_assign_any_role(): void
{
    $superAdmin = Role::superAdmin();
    $this->assertTrue($superAdmin->canAssignRole(Role::admin()));
    $this->assertTrue($superAdmin->canAssignRole(Role::employee()));
}

#[Test]
public function admin_can_only_assign_employee_role(): void
{
    $admin = Role::admin();
    $this->assertTrue($admin->canAssignRole(Role::employee()));
    $this->assertFalse($admin->canAssignRole(Role::admin()));
}
```

- [ ] **Step 2: Implement Role, UserStatus, User entity**

```php
// Role.php
final class Role
{
    public const EMPLOYEE = 'employee';
    public const ADMIN = 'admin';
    public const SUPER_ADMIN = 'super_admin';

    public function isAdmin(): bool
    {
        return in_array($this->value, [self::ADMIN, self::SUPER_ADMIN], true);
    }

    public function canAssignRole(self $target): bool
    {
        if ($this->value === self::SUPER_ADMIN) {
            return true;
        }
        return $target->value === self::EMPLOYEE;
    }
}
```

```php
// User.php entity — key methods
public function canLogin(): bool
{
    return $this->status->isActive();
}
```

- [ ] **Step 3: Run unit tests**

Run: `docker compose exec backend php artisan test --filter=RoleTest`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git commit -m "feat(M03): add Identity domain entities and value objects"
```

---

### Task 3: EloquentUserRepository

**Files:**
- Create: `backend/app/Infrastructure/Persistence/Eloquent/EloquentUserRepository.php`
- Modify: `backend/app/Providers/AppServiceProvider.php`
- Test: `backend/tests/Feature/Infrastructure/EloquentUserRepositoryTest.php`

**Interfaces:**
- Consumes: `UserModel`, Domain `User`, `Role`, `UserStatus`
- Produces: `EloquentUserRepository implements UserRepositoryInterface`
- Produces: DI bind `UserRepositoryInterface` → `EloquentUserRepository`

- [ ] **Step 1: Write failing repository test**

```php
#[Test]
public function find_by_phone_returns_domain_user(): void
{
    UserModel::factory()->create(['phone' => '13800000001']);
    $repo = app(UserRepositoryInterface::class);
    $user = $repo->findByPhone('13800000001');
    $this->assertNotNull($user);
    $this->assertSame('13800000001', $user->phone);
}

#[Test]
public function search_by_keyword_matches_name(): void
{
    UserModel::factory()->create(['name' => '张三', 'phone' => '13800000001']);
    UserModel::factory()->create(['name' => '李四', 'phone' => '13800000002']);
    $repo = app(UserRepositoryInterface::class);
    $result = $repo->search('张', 1, 20);
    $this->assertSame(1, $result['total']);
}
```

- [ ] **Step 2: Implement EloquentUserRepository with toDomain()/toModel() mappers**

- [ ] **Step 3: Register in AppServiceProvider**

```php
$this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
```

- [ ] **Step 4: Run tests — PASS**

- [ ] **Step 5: Commit**

```bash
git commit -m "feat(M03): add EloquentUserRepository"
```

---

### Task 4: LoginHandler + Auth Feature Tests

**Files:**
- Create: `backend/app/Application/Identity/Login/LoginHandler.php`
- Create: `backend/app/Application/Identity/DTO/LoginResultDto.php`
- Create: `backend/app/Http/Controllers/AuthController.php`（login only）
- Create: `backend/app/Http/Requests/Auth/LoginRequest.php`
- Create: `backend/app/Http/Resources/AuthUserResource.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Unit/Application/Identity/LoginHandlerTest.php`
- Test: `backend/tests/Feature/Auth/AuthApiTest.php`（login cases）

**Interfaces:**
- Produces: `LoginHandler::handle(string $phone, string $password): LoginResultDto`
- Produces: `POST /api/v1/auth/login`

- [ ] **Step 1: Write failing LoginHandlerTest**

```php
#[Test]
public function login_with_valid_credentials_returns_token(): void
{
    $user = UserModel::factory()->create([
        'phone' => '13800000001',
        'password' => Hash::make('123456'),
        'must_change_password' => true,
    ]);
    $handler = new LoginHandler(app(UserRepositoryInterface::class));
    $result = $handler->handle('13800000001', '123456');
    $this->assertNotEmpty($result->token);
    $this->assertTrue($result->mustChangePassword);
}

#[Test]
public function login_with_disabled_account_throws(): void
{
    UserModel::factory()->disabled()->create([
        'phone' => '13800000001',
        'password' => Hash::make('123456'),
    ]);
    $this->expectException(AccountDisabledException::class);
    (new LoginHandler(app(UserRepositoryInterface::class)))->handle('13800000001', '123456');
}
```

- [ ] **Step 2: Implement LoginHandler**

Token creation via UserModel lookup (repository returns entity; handler loads model for `createToken` or repository returns model id):

```php
public function handle(string $phone, string $password): LoginResultDto
{
    $user = $this->repository->findByPhone($phone);
    if ($user === null || ! Hash::check($password, $user->passwordHash)) {
        throw new InvalidCredentialsException();
    }
    if (! $user->canLogin()) {
        throw new AccountDisabledException();
    }
    $model = UserModel::findOrFail($user->id);
    $token = $model->createToken('api')->plainTextToken;
    return new LoginResultDto($token, $user, $user->mustChangePassword);
}
```

> Domain User 实体含 `passwordHash` 字段（Infrastructure 映射时填入，Resource 不输出）。

- [ ] **Step 3: Write failing AuthApiTest login cases**

```php
#[Test]
public function login_returns_token_and_must_change_password_flag(): void
{
    UserModel::factory()->mustChangePassword()->create([
        'phone' => '13800000001',
        'password' => Hash::make('123456'),
    ]);
    $this->postJson('/api/v1/auth/login', [
        'phone' => '13800000001',
        'password' => '123456',
    ])->assertOk()
      ->assertJsonPath('data.must_change_password', true)
      ->assertJsonPath('data.token', fn ($v) => ! empty($v));
}

#[Test]
public function disabled_user_cannot_login(): void
{
    UserModel::factory()->disabled()->create([
        'phone' => '13800000001',
        'password' => Hash::make('123456'),
    ]);
    $this->postJson('/api/v1/auth/login', [
        'phone' => '13800000001',
        'password' => '123456',
    ])->assertForbidden();
}
```

- [ ] **Step 4: Implement AuthController::login, LoginRequest, route**

- [ ] **Step 5: Run tests — PASS**

- [ ] **Step 6: Commit**

```bash
git commit -m "feat(M03): add login API"
```

---

### Task 5: ChangePassword、Logout、GetCurrentUser

**Files:**
- Create: `backend/app/Application/Identity/ChangePassword/ChangePasswordHandler.php`
- Create: `backend/app/Application/Identity/Logout/LogoutHandler.php`
- Create: `backend/app/Application/Identity/GetCurrentUser/GetCurrentUserHandler.php`
- Create: `backend/app/Http/Requests/Auth/ChangePasswordRequest.php`
- Modify: `backend/app/Http/Controllers/AuthController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Unit/Application/Identity/ChangePasswordHandlerTest.php`
- Test: `backend/tests/Feature/Auth/AuthApiTest.php`（extend）

**Interfaces:**
- Produces: `ChangePasswordHandler::handle(int $userId, string $current, string $new): void`
- Produces: `PUT /auth/password`, `POST /auth/logout`, `GET /auth/me`

- [ ] **Step 1: Write failing ChangePasswordHandlerTest**

```php
#[Test]
public function change_password_clears_must_change_password_flag(): void
{
    $model = UserModel::factory()->mustChangePassword()->create([
        'password' => Hash::make('123456'),
    ]);
    $handler = new ChangePasswordHandler(app(UserRepositoryInterface::class));
    $handler->handle($model->id, '123456', 'newpass1');
    $model->refresh();
    $this->assertFalse($model->must_change_password);
}
```

- [ ] **Step 2: Implement handlers + AuthController methods + routes**

`ChangePasswordRequest` rules:
```php
'current_password' => ['required', 'string'],
'new_password' => ['required', 'string', 'min:6', 'confirmed'],
```

- [ ] **Step 3: Feature tests for logout, me, change password**

- [ ] **Step 4: Run tests — PASS**

- [ ] **Step 5: Commit**

```bash
git commit -m "feat(M03): add logout, me, and change password API"
```

---

### Task 6: Middleware EnsurePasswordChanged + EnsureAdmin

**Files:**
- Create: `backend/app/Http/Middleware/EnsureAdmin.php`
- Create: `backend/app/Http/Middleware/EnsurePasswordChanged.php`
- Modify: `backend/bootstrap/app.php`
- Test: `backend/tests/Feature/Auth/PasswordChangeMiddlewareTest.php`
- Test: `backend/tests/Feature/Auth/EnsureAdminMiddlewareTest.php`

**Interfaces:**
- Produces: middleware aliases `admin`, `password.changed`
- Produces: EnsurePasswordChanged whitelists `auth/me`, `auth/password`, `auth/logout`
- Produces: 40301 response for password change required

- [ ] **Step 1: Write failing PasswordChangeMiddlewareTest**

```php
#[Test]
public function must_change_password_blocks_admin_routes(): void
{
    $user = UserModel::factory()->mustChangePassword()->create(['role' => 'admin']);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/admin/employees')
        ->assertForbidden()
        ->assertJsonPath('code', 40301);
}

#[Test]
public function must_change_password_allows_password_change_route(): void
{
    $user = UserModel::factory()->mustChangePassword()->create([
        'password' => Hash::make('123456'),
    ]);
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/v1/auth/password', [
            'current_password' => '123456',
            'new_password' => 'newpass1',
            'new_password_confirmation' => 'newpass1',
        ])
        ->assertOk();
}
```

- [ ] **Step 2: Implement middleware**

```php
// EnsurePasswordChanged.php
private const ALLOWED_ROUTES = [
    'api/v1/auth/me',
    'api/v1/auth/password',
    'api/v1/auth/logout',
];

public function handle(Request $request, Closure $next): Response
{
    $user = $request->user();
    if ($user && $user->must_change_password) {
        $path = $request->path();
        if (! in_array($path, self::ALLOWED_ROUTES, true)) {
            return ApiResponse::error(40301, '请先修改密码', null, 403);
        }
    }
    return $next($request);
}
```

```php
// EnsureAdmin.php
public function handle(Request $request, Closure $next): Response
{
    $user = $request->user();
    if (! $user || ! in_array($user->role, ['admin', 'super_admin'], true)) {
        return ApiResponse::error(403, '无权访问', null, 403);
    }
    return $next($request);
}
```

- [ ] **Step 3: Register aliases in bootstrap/app.php**

```php
$middleware->alias([
    'admin' => \App\Http\Middleware\EnsureAdmin::class,
    'password.changed' => \App\Http\Middleware\EnsurePasswordChanged::class,
]);
```

- [ ] **Step 4: Run tests — PASS**

- [ ] **Step 5: Commit**

```bash
git commit -m "feat(M03): add EnsureAdmin and EnsurePasswordChanged middleware"
```

---

### Task 7: Employee Application Handlers

**Files:**
- Create: `backend/app/Application/Identity/CreateEmployee/CreateEmployeeHandler.php`
- Create: `backend/app/Application/Identity/UpdateEmployee/UpdateEmployeeHandler.php`
- Create: `backend/app/Application/Identity/DisableEmployee/DisableEmployeeHandler.php`
- Create: `backend/app/Application/Identity/GetEmployee/GetEmployeeHandler.php`
- Create: `backend/app/Application/Identity/ListEmployees/ListEmployeesHandler.php`
- Create: `backend/app/Application/Identity/DTO/CreateEmployeeCommand.php`
- Create: `backend/app/Application/Identity/DTO/UpdateEmployeeCommand.php`
- Test: `backend/tests/Unit/Application/Identity/CreateEmployeeHandlerTest.php`
- Test: `backend/tests/Unit/Application/Identity/UpdateEmployeeHandlerTest.php`

**Interfaces:**
- Consumes: `Role::canAssignRole()`, `config('identity.default_password')`
- Produces:
  - `CreateEmployeeHandler::handle(CreateEmployeeCommand, Role $operatorRole): User`
  - `UpdateEmployeeHandler::handle(UpdateEmployeeCommand, int $operatorId, Role $operatorRole): User`
  - `DisableEmployeeHandler::handle(int $employeeId, int $operatorId): void`

- [ ] **Step 1: Write failing CreateEmployeeHandlerTest**

```php
#[Test]
public function create_employee_sets_default_password_and_must_change_flag(): void
{
    $handler = new CreateEmployeeHandler(app(UserRepositoryInterface::class));
    $user = $handler->handle(
        new CreateEmployeeCommand('张三', '13800000003', 'E003', '技术部', Role::employee()),
        Role::admin(),
    );
    $this->assertTrue($user->mustChangePassword);
    $model = UserModel::find($user->id);
    $this->assertTrue(Hash::check('123456', $model->password));
}

#[Test]
public function admin_cannot_create_admin_role(): void
{
    $this->expectException(ForbiddenRoleAssignmentException::class);
    (new CreateEmployeeHandler(app(UserRepositoryInterface::class)))->handle(
        new CreateEmployeeCommand('管理员', '13800000004', null, null, Role::admin()),
        Role::admin(),
    );
}
```

- [ ] **Step 2: Implement all employee handlers**

UpdateEmployeeHandler rules:
- `reset_password=true` → Hash default password, set must_change_password=true
- Cannot disable self
- Role assignment check via `canAssignRole`

- [ ] **Step 3: Run unit tests — PASS**

- [ ] **Step 4: Commit**

```bash
git commit -m "feat(M03): add employee application handlers"
```

---

### Task 8: EmployeeController + Feature Tests

**Files:**
- Create: `backend/app/Http/Controllers/Admin/EmployeeController.php`
- Create: `backend/app/Http/Requests/Admin/CreateEmployeeRequest.php`
- Create: `backend/app/Http/Requests/Admin/UpdateEmployeeRequest.php`
- Create: `backend/app/Http/Resources/Admin/EmployeeResource.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Admin/EmployeeApiTest.php`

**Interfaces:**
- Produces: CRUD routes under `/api/v1/admin/employees`
- Produces: pagination response `{ items, meta: { total, page, per_page } }`

- [ ] **Step 1: Create test helper trait or base setup**

```php
private function adminToken(Role $role = null): string
{
    $factory = UserModel::factory();
    $user = match ($role?->value ?? 'admin') {
        'super_admin' => $factory->superAdmin()->create(),
        default => $factory->admin()->create(),
    };
    return $user->createToken('test')->plainTextToken;
}
```

- [ ] **Step 2: Write failing EmployeeApiTest**

```php
#[Test]
public function admin_can_create_employee(): void
{
    $response = $this->withToken($this->adminToken())
        ->postJson('/api/v1/admin/employees', [
            'name' => '张三',
            'phone' => '13800000005',
            'employee_no' => 'E005',
            'department' => '技术部',
        ]);
    $response->assertCreated()->assertJsonPath('data.phone', '13800000005');
}

#[Test]
public function employee_token_cannot_access_employees(): void
{
    $user = UserModel::factory()->create(['role' => 'employee']);
    $token = $user->createToken('test')->plainTextToken;
    $this->withToken($token)->getJson('/api/v1/admin/employees')->assertForbidden();
}

#[Test]
public function keyword_search_finds_by_name(): void
{
    UserModel::factory()->create(['name' => '张三', 'phone' => '13800000010']);
    UserModel::factory()->create(['name' => '李四', 'phone' => '13800000011']);
    $this->withToken($this->adminToken())
        ->getJson('/api/v1/admin/employees?keyword=张')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1);
}

#[Test]
public function delete_employee_soft_disables(): void
{
    $employee = UserModel::factory()->create();
    $this->withToken($this->adminToken())
        ->deleteJson("/api/v1/admin/employees/{$employee->id}")
        ->assertOk();
    $this->assertSame('disabled', $employee->fresh()->status);
}

#[Test]
public function admin_cannot_assign_admin_role(): void
{
    $this->withToken($this->adminToken())
        ->postJson('/api/v1/admin/employees', [
            'name' => '管理员',
            'phone' => '13800000006',
            'role' => 'admin',
        ])
        ->assertUnprocessable();
}
```

- [ ] **Step 3: Implement Controller, Requests, Resource, routes**

Update `routes/api.php`:
```php
Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/password', [AuthController::class, 'changePassword']);
    });
});

Route::middleware(['auth:sanctum', 'password.changed', 'admin'])->prefix('admin')->group(function (): void {
    Route::get('/configs', [SystemConfigController::class, 'index']);
    Route::put('/configs', [SystemConfigController::class, 'update']);
    Route::post('/upload', [UploadController::class, 'store']);
    Route::apiResource('employees', EmployeeController::class);
});
```

Also apply `password.changed` + `admin` to auth routes group for `/auth/logout` etc. — **auth routes only use `auth:sanctum`**, not admin middleware.

- [ ] **Step 4: Run EmployeeApiTest — PASS**

- [ ] **Step 5: Commit**

```bash
git commit -m "feat(M03): add employee CRUD API"
```

---

### Task 9: M01/M02 回归 — employee token 403

**Files:**
- Modify: `backend/tests/Feature/Admin/SystemConfigApiTest.php`
- Modify: `backend/tests/Feature/Admin/UploadApiTest.php`

- [ ] **Step 1: Update SystemConfigApiTest setUp to use admin user**

```php
$this->user = UserModel::factory()->admin()->create();
```

- [ ] **Step 2: Add employee forbidden test to both files**

```php
#[Test]
public function employee_token_cannot_access_configs(): void
{
    $user = UserModel::factory()->create(['role' => 'employee']);
    $token = $user->createToken('test')->plainTextToken;
    $this->withToken($token)->getJson('/api/v1/admin/configs')->assertForbidden();
}
```

- [ ] **Step 3: Run full test suite**

Run: `./scripts/docker-test.sh`
Expected: ALL PASS

- [ ] **Step 4: Commit**

```bash
git commit -m "test(M03): enforce EnsureAdmin on M01/M02 admin routes"
```

---

### Task 10: SuperAdminSeeder + 执行记录

**Files:**
- Create: `backend/database/seeders/SuperAdminSeeder.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php`
- Create: `docs/superpowers/records/2026-07-12-M03-auth-employee.md`

- [ ] **Step 1: Create SuperAdminSeeder**

```php
<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        UserModel::updateOrCreate(
            ['phone' => config('identity.super_admin_phone')],
            [
                'name' => '超级管理员',
                'password' => Hash::make(config('identity.super_admin_password')),
                'role' => 'super_admin',
                'status' => 'active',
                'must_change_password' => false,
            ],
        );
    }
}
```

- [ ] **Step 2: Update DatabaseSeeder**

```php
$this->call([
    SuperAdminSeeder::class,
    SystemConfigSeeder::class,
]);
// Remove default User::factory test user or keep for dev only
```

- [ ] **Step 3: Feature test seeder login**

```php
#[Test]
public function super_admin_seeder_can_login(): void
{
    $this->seed(SuperAdminSeeder::class);
    $this->postJson('/api/v1/auth/login', [
        'phone' => '13800000000',
        'password' => 'admin123',
    ])->assertOk();
}
```

- [ ] **Step 4: Write record doc with acceptance checklist**

- [ ] **Step 5: Final test run + commit**

```bash
git commit -m "feat(M03): add SuperAdminSeeder and completion record"
```

---

## Plan Self-Review

| Spec 要求 | 对应 Task |
|-----------|-----------|
| users 表扩展 | Task 1 |
| 手机号登录 | Task 4 |
| 首次改密 + 中间件拦截 | Task 5, 6 |
| EnsureAdmin 保护 /admin/* | Task 6, 8, 9 |
| 员工 CRUD + 软删除 | Task 7, 8 |
| admin/super_admin 角色权限 | Task 2, 7 |
| keyword 搜索 | Task 3, 8 |
| 默认密码 123456 | Task 7, config |
| Seeder 超管 | Task 10 |
| 全量测试 | Task 9, 10 |

无 TBD / 占位符。类型与接口在各 Task Interfaces 块中一致。

---

**Plan 路径：** `docs/superpowers/plans/2026-07-12-M03-auth-employee.md`
