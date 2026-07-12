<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories');
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price');
            $table->foreignId('upload_id')->nullable()->constrained('uploads')->nullOnDelete();
            $table->string('image_path', 500)->nullable();
            $table->string('status', 20)->default('off_sale');
            $table->integer('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
