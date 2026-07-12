<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Catalog\Entities\Product;
use App\Domain\Catalog\Repositories\ProductRepositoryInterface;
use App\Domain\Catalog\ValueObjects\CategoryStatus;
use App\Domain\Catalog\ValueObjects\ProductStatus;
use App\Infrastructure\Persistence\Eloquent\Models\ProductModel;
use Illuminate\Database\Eloquent\Builder;

class EloquentProductRepository implements ProductRepositoryInterface
{
    public function findById(int $id): ?Product
    {
        $model = ProductModel::query()->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function save(Product $product): Product
    {
        $attributes = [
            'category_id' => $product->categoryId,
            'name' => $product->name,
            'description' => $product->description,
            'price' => $product->price,
            'upload_id' => $product->uploadId,
            'image_path' => $product->imagePath,
            'status' => $product->status->value,
            'sort' => $product->sort,
        ];

        if ($product->id === null) {
            $model = ProductModel::query()->create($attributes);
        } else {
            $model = ProductModel::query()->findOrFail($product->id);
            $model->fill($attributes);
            $model->save();
        }

        return $this->toDomain($model->fresh());
    }

    public function searchAdmin(?int $categoryId, ?string $status, string $keyword, int $page, int $perPage): array
    {
        $query = ProductModel::query()
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->select('products.*', 'categories.name as category_name')
            ->orderBy('products.sort')
            ->orderBy('products.id');

        $this->applyAdminFilters($query, $categoryId, $status, $keyword);

        $paginator = $query->paginate($perPage, ['products.*', 'categories.name as category_name'], 'page', $page);

        return [
            'items' => $paginator->getCollection()
                ->map(fn (ProductModel $model) => $this->toDomain($model, $model->category_name))
                ->all(),
            'total' => $paginator->total(),
        ];
    }

    public function searchVisible(?int $categoryId, int $page, int $perPage): array
    {
        $query = $this->visibleQuery();

        if ($categoryId !== null) {
            $query->where('products.category_id', $categoryId);
        }

        $paginator = $query
            ->orderBy('products.sort')
            ->orderBy('products.id')
            ->paginate($perPage, ['products.*', 'categories.name as category_name'], 'page', $page);

        return [
            'items' => $paginator->getCollection()
                ->map(fn (ProductModel $model) => $this->toDomain($model, $model->category_name))
                ->all(),
            'total' => $paginator->total(),
        ];
    }

    public function findVisibleById(int $id): ?Product
    {
        $model = $this->visibleQuery()
            ->where('products.id', $id)
            ->first();

        return $model ? $this->toDomain($model, $model->category_name) : null;
    }

    /**
     * @return Builder<ProductModel>
     */
    private function visibleQuery(): Builder
    {
        return ProductModel::query()
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('products.status', ProductStatus::ON_SALE)
            ->where('categories.status', CategoryStatus::ACTIVE)
            ->select('products.*', 'categories.name as category_name');
    }

    /**
     * @param  Builder<ProductModel>  $query
     */
    private function applyAdminFilters(Builder $query, ?int $categoryId, ?string $status, string $keyword): void
    {
        if ($categoryId !== null) {
            $query->where('products.category_id', $categoryId);
        }

        if ($status !== null && $status !== '') {
            $query->where('products.status', $status);
        }

        if ($keyword !== '') {
            $query->where('products.name', 'like', "%{$keyword}%");
        }
    }

    private function toDomain(ProductModel $model, ?string $categoryName = null): Product
    {
        return new Product(
            id: $model->id,
            categoryId: $model->category_id,
            name: $model->name,
            description: $model->description,
            price: $model->price,
            uploadId: $model->upload_id,
            imagePath: $model->image_path,
            status: ProductStatus::fromString($model->status),
            sort: $model->sort,
            categoryName: $categoryName,
        );
    }
}
