<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('third_party_callback_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20)->comment('alipay / wechat');
            $table->string('request_method', 10);
            $table->text('request_headers')->nullable();
            $table->longText('request_body');
            $table->unsignedSmallInteger('response_status');
            $table->text('response_body')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('channel');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('third_party_callback_logs');
    }
};
