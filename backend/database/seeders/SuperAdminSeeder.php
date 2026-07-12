<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        UserModel::query()->updateOrCreate(
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
