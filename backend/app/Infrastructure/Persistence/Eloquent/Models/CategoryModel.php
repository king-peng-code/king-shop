<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryModel extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected $table = 'categories';

    protected $fillable = ['name', 'sort', 'status'];

    public function products(): HasMany
    {
        return $this->hasMany(ProductModel::class, 'category_id');
    }

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }
}
