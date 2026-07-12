<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Catalog\Entities\Category;
use App\Domain\Catalog\Repositories\CategoryRepositoryInterface;
use App\Domain\Catalog\ValueObjects\CategoryStatus;
use App\Infrastructure\Persistence\Eloquent\Models\CategoryModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProductModel;

class EloquentCategoryRepository implements CategoryRepositoryInterface
{
    public function findById(int $id): ?Category
    {
        $model = CategoryModel::query()->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function save(Category $category): Category
    {
        $attributes = [
            'name' => $category->name,
            'sort' => $category->sort,
            'status' => $category->status->value,
        ];

        if ($category->id === null) {
            $model = CategoryModel::query()->create($attributes);
        } else {
            $model = CategoryModel::query()->findOrFail($category->id);
            $model->fill($attributes);
            $model->save();
        }

        return $this->toDomain($model->fresh());
    }

    public function delete(int $id): void
    {
        CategoryModel::query()->whereKey($id)->delete();
    }

    public function listAll(): array
    {
        $items = CategoryModel::query()
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->map(fn (CategoryModel $model) => $this->toDomain($model))
            ->all();

        return ['items' => $items];
    }

    public function listActive(): array
    {
        $items = CategoryModel::query()
            ->where('status', CategoryStatus::ACTIVE)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->map(fn (CategoryModel $model) => $this->toDomain($model))
            ->all();

        return ['items' => $items];
    }

    public function countProducts(int $categoryId): int
    {
        return ProductModel::query()
            ->where('category_id', $categoryId)
            ->count();
    }

    private function toDomain(CategoryModel $model): Category
    {
        return new Category(
            id: $model->id,
            name: $model->name,
            sort: $model->sort,
            status: CategoryStatus::fromString($model->status),
        );
    }
}
