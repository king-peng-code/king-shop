<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductModel extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'category_id', 'name', 'description', 'price',
        'upload_id', 'image_path', 'status', 'sort',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(CategoryModel::class, 'category_id');
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }
}
