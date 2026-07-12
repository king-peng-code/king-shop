<?php

namespace Tests\Unit\Application\Identity;

use App\Application\Identity\CreateEmployee\CreateEmployeeHandler;
use App\Application\Identity\DTO\CreateEmployeeCommand;
use App\Application\Identity\Login\LoginHandler;
use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Exceptions\AccountDisabledException;
use App\Domain\Identity\Exceptions\ForbiddenRoleAssignmentException;
use App\Domain\Identity\Exceptions\InvalidCredentialsException;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Identity\ValueObjects\Role;
use App\Domain\Identity\ValueObjects\UserStatus;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IdentityHandlerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function login_with_valid_credentials_returns_token(): void
    {
        UserModel::factory()->create([
            'phone' => '13800000001',
            'password' => Hash::make('123456'),
            'must_change_password' => true,
        ]);

        $result = app(LoginHandler::class)->handle('13800000001', '123456');

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
        app(LoginHandler::class)->handle('13800000001', '123456');
    }

    #[Test]
    public function login_with_wrong_password_throws(): void
    {
        UserModel::factory()->create([
            'phone' => '13800000001',
            'password' => Hash::make('123456'),
        ]);

        $this->expectException(InvalidCredentialsException::class);
        app(LoginHandler::class)->handle('13800000001', 'wrong');
    }

    #[Test]
    public function create_employee_sets_default_password_and_must_change_flag(): void
    {
        $user = app(CreateEmployeeHandler::class)->handle(
            new CreateEmployeeCommand('张三', '13890000100', Role::employee()),
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
        app(CreateEmployeeHandler::class)->handle(
            new CreateEmployeeCommand('管理员', '13890000101', Role::admin()),
            Role::admin(),
        );
    }
}
