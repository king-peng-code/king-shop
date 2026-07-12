<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SanctumSetupTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_create_api_token(): void
    {
        $user = UserModel::factory()->create();

        $token = $user->createToken('test-device')->plainTextToken;

        $this->assertNotEmpty($token);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => UserModel::class,
            'name' => 'test-device',
        ]);
    }
}
