<?php

namespace Tests;

use App\Infrastructure\Persistence\Eloquent\Models\SystemConfigModel;
use Database\Seeders\SystemConfigSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'mysql') {
            $this->fail(
                'Tests must not run against the dev MySQL database. '
                .'Run ./scripts/docker-test.sh (clears config cache and uses sqlite).'
            );
        }
    }

    protected function seedLocalStoragePublicBaseUrl(string $url = 'http://localhost:8000'): void
    {
        $this->seed(SystemConfigSeeder::class);
        SystemConfigModel::query()
            ->where('group', 'storage')
            ->where('key', 'local.public_base_url')
            ->update(['value' => $url]);
    }
}
