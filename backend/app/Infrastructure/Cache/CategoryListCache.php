<?php

namespace App\Infrastructure\Cache;

use App\Domain\Catalog\Entities\Category;
use App\Domain\Catalog\ValueObjects\CategoryStatus;
use Illuminate\Support\Facades\Cache;

class CategoryListCache
{
    private const string KEY = 'catalog:admin:categories:list';

    /**
     * @return array{items: Category[]}|null
     */
    public function get(): ?array
    {
        $cached = Cache::get(self::KEY);

        if (! is_array($cached)) {
            return null;
        }

        return [
            'items' => array_map(
                fn (array $row) => new Category(
                    id: $row['id'],
                    name: $row['name'],
                    sort: $row['sort'],
                    status: CategoryStatus::fromString($row['status']),
                ),
                $cached,
            ),
        ];
    }

    /**
     * @param array{items: Category[]} $data
     */
    public function put(array $data): void
    {
        $serialized = array_map(
            fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'sort' => $category->sort,
                'status' => $category->status->value,
            ],
            $data['items'],
        );

        Cache::forever(self::KEY, $serialized);
    }

    public function forget(): void
    {
        Cache::forget(self::KEY);
    }
}
