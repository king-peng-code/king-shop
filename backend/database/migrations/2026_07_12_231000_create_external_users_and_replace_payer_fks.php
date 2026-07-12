<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_users', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('external_id', 128);
            $table->string('name', 100)->nullable();
            $table->string('phone', 11)->nullable();
            $table->timestamps();
            $table->unique(['provider', 'external_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paid_by_user_id');
            $table->foreignId('paid_by_external_user_id')->nullable()->constrained('external_users')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payer_user_id');
            $table->foreignId('payer_external_user_id')->nullable()->constrained('external_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payer_external_user_id');
            $table->foreignId('payer_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paid_by_external_user_id');
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::dropIfExists('external_users');
    }
};
