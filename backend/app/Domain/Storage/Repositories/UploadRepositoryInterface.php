<?php

namespace App\Domain\Storage\Repositories;

use App\Domain\Storage\Entities\Upload;

interface UploadRepositoryInterface
{
    public function findById(int $id): ?Upload;

    public function save(Upload $upload): Upload;
}
