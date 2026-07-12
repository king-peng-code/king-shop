<?php

namespace Tests\Unit\Domain\Identity\ValueObjects;

use App\Domain\Identity\ValueObjects\Role;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RoleTest extends TestCase
{
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
        $this->assertFalse($admin->canAssignRole(Role::superAdmin()));
    }

    #[Test]
    public function admin_roles_are_detected(): void
    {
        $this->assertTrue(Role::admin()->isAdmin());
        $this->assertTrue(Role::superAdmin()->isAdmin());
        $this->assertFalse(Role::employee()->isAdmin());
    }
}
