<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::drop('users');
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
                $table->string('phone', 11)->unique();
                $table->string('employee_no', 50)->nullable()->unique();
                $table->string('department', 100)->nullable();
                $table->string('role', 20)->default('employee');
                $table->string('status', 20)->default('active');
                $table->string('avatar')->nullable();
                $table->boolean('must_change_password')->default(true);
            });

            return;
        }

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
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::drop('users');
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'employee_no', 'department', 'role',
                'status', 'avatar', 'must_change_password',
            ]);
            $table->string('email')->nullable(false)->change();
        });
    }
};
