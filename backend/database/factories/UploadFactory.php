<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\UploadModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UploadModel>
 */
class UploadFactory extends Factory
{
    protected $model = UploadModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'original_name' => 'photo.jpg',
            'path' => 'uploads/2026/07/'.fake()->uuid().'.jpg',
            'disk' => 'local',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'md5' => md5(fake()->uuid()),
            'uploaded_by' => UserModel::factory(),
        ];
    }
}
