<?php

namespace Tests\Unit\Domain\Identity\Entities;

use App\Domain\Identity\Entities\User;
use App\Domain\Identity\ValueObjects\Role;
use App\Domain\Identity\ValueObjects\UserStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserTest extends TestCase
{
    #[Test]
    public function active_user_can_login(): void
    {
        $user = $this->makeUser(UserStatus::active());

        $this->assertTrue($user->canLogin());
    }

    #[Test]
    public function disabled_user_cannot_login(): void
    {
        $user = $this->makeUser(UserStatus::disabled());

        $this->assertFalse($user->canLogin());
    }

    private function makeUser(UserStatus $status): User
    {
        return new User(
            id: 1,
            name: 'Test',
            email: null,
            phone: '13800000001',
            employeeNo: 'E001',
            department: '技术部',
            role: Role::employee(),
            status: $status,
            avatar: null,
            mustChangePassword: false,
            passwordHash: 'hash',
        );
    }
}
