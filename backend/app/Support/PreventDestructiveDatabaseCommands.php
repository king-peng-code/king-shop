<?php

namespace App\Support;

use Illuminate\Console\Events\CommandStarting;
use RuntimeException;

final class PreventDestructiveDatabaseCommands
{
    /** @var list<string> */
    private const BLOCKED_COMMANDS = [
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'db:wipe',
    ];

    public static function handle(CommandStarting $event): void
    {
        if (! self::shouldBlock($event->command)) {
            return;
        }

        throw new RuntimeException(
            'Destructive database command blocked on local MySQL (king_shop). '
            .'Run ./scripts/docker-test.sh for tests (sqlite). '
            .'If you need a clean schema, use a separate database — never migrate:fresh the dev DB.'
        );
    }

    public static function shouldBlock(?string $command): bool
    {
        return self::shouldBlockFor(
            config('database.default'),
            app()->environment(),
            $command,
        );
    }

    public static function shouldBlockFor(
        string $databaseConnection,
        string $environment,
        ?string $command,
    ): bool {
        if ($command === null || ! in_array($command, self::BLOCKED_COMMANDS, true)) {
            return false;
        }

        if ($environment !== 'local') {
            return false;
        }

        return $databaseConnection === 'mysql';
    }
}
