<?php

namespace App\Application\Catalog\UpdateProduct;

use App\Application\Catalog\DTO\UpdateProductCommand;
use App\Domain\Catalog\Entities\Product;
use App\Domain\Catalog\Exceptions\ProductNotFoundException;
use App\Domain\Catalog\Exceptions\UploadNotFoundException;
use App\Domain\Catalog\Repositories\ProductRepositoryInterface;
use App\Domain\Storage\Repositories\UploadRepositoryInterface;
use App\Infrastructure\Cache\ProductListCache;

class UpdateProductHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly UploadRepositoryInterface $uploadRepository,
        private readonly ProductListCache $productCache,
    ) {}

    public function handle(UpdateProductCommand $command): Product
    {
        $existing = $this->productRepository->findById($command->productId)
            ?? throw new ProductNotFoundException;

        $uploadId = $existing->uploadId;
        $imagePath = $existing->imagePath;

        if ($command->uploadIdProvided) {
            $uploadId = $command->uploadId;

            if ($command->uploadId !== null) {
                $upload = $this->uploadRepository->findById($command->uploadId)
                    ?? throw new UploadNotFoundException;
                $imagePath = $upload->path;
            } elseif (! $command->imagePathProvided) {
                $imagePath = null;
            } else {
                $imagePath = $command->imagePath;
            }
        } elseif ($command->imagePathProvided) {
            $imagePath = $command->imagePath;
        }

        $product = new Product(
            id: $existing->id,
            categoryId: $command->categoryId,
            name: $command->name,
            description: $command->description,
            price: $command->price,
            uploadId: $uploadId,
            imagePath: $imagePath,
            status: $command->status,
            sort: $command->sort,
        );

        $updated = $this->productRepository->save($product);
        $this->productCache->invalidateAll();

        return $updated;
    }
}
