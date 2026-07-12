<?php

namespace Tests\Unit\Support;

use App\Support\PreventDestructiveDatabaseCommands;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PreventDestructiveDatabaseCommandsTest extends TestCase
{
    #[Test]
    public function migrate_fresh_is_blocked_for_local_mysql(): void
    {
        $this->assertTrue(PreventDestructiveDatabaseCommands::shouldBlockFor(
            'mysql',
            'local',
            'migrate:fresh',
        ));
    }

    #[Test]
    public function migrate_fresh_is_allowed_for_sqlite(): void
    {
        $this->assertFalse(PreventDestructiveDatabaseCommands::shouldBlockFor(
            'sqlite',
            'local',
            'migrate:fresh',
        ));
    }

    #[Test]
    public function unrelated_commands_are_not_blocked(): void
    {
        $this->assertFalse(PreventDestructiveDatabaseCommands::shouldBlockFor('mysql', 'local', 'migrate'));
        $this->assertFalse(PreventDestructiveDatabaseCommands::shouldBlockFor('mysql', 'local', null));
    }
}
