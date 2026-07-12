<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['employee_no']);
            $table->dropColumn(['employee_no', 'department']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('employee_no', 50)->nullable()->unique()->after('phone');
            $table->string('department', 100)->nullable()->after('employee_no');
        });
    }
};
