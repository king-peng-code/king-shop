<?php

namespace App\Application\Catalog\CreateProduct;

use App\Application\Catalog\DTO\CreateProductCommand;
use App\Domain\Catalog\Entities\Product;
use App\Domain\Catalog\Exceptions\UploadNotFoundException;
use App\Domain\Catalog\Repositories\ProductRepositoryInterface;
use App\Domain\Storage\Repositories\UploadRepositoryInterface;
use App\Infrastructure\Cache\ProductListCache;

class CreateProductHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly UploadRepositoryInterface $uploadRepository,
        private readonly ProductListCache $productCache,
    ) {}

    public function handle(CreateProductCommand $command): Product
    {
        $imagePath = $command->imagePath;

        if ($command->uploadId !== null) {
            $upload = $this->uploadRepository->findById($command->uploadId)
                ?? throw new UploadNotFoundException;
            $imagePath = $upload->path;
        }

        $product = new Product(
            id: null,
            categoryId: $command->categoryId,
            name: $command->name,
            description: $command->description,
            price: $command->price,
            uploadId: $command->uploadId,
            imagePath: $imagePath,
            status: $command->status,
            sort: $command->sort,
        );

        $created = $this->productRepository->save($product);
        $this->productCache->invalidateAll();

        return $created;
    }
}
