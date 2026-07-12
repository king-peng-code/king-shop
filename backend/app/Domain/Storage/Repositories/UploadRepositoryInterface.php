<?php

namespace App\Domain\Storage\Repositories;

use App\Domain\Storage\Entities\Upload;

interface UploadRepositoryInterface
{
    public function save(Upload $upload): Upload;
}
