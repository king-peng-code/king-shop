<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Storage\Entities\Upload;
use App\Domain\Storage\Repositories\UploadRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UploadModel;

class EloquentUploadRepository implements UploadRepositoryInterface
{
    public function findById(int $id): ?Upload
    {
        $model = UploadModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByMd5(string $md5): ?Upload
    {
        $model = UploadModel::query()->where('md5', $md5)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function save(Upload $upload): Upload
    {
        $model = UploadModel::query()->create([
            'original_name' => $upload->originalName,
            'path' => $upload->path,
            'disk' => $upload->disk,
            'mime_type' => $upload->mimeType,
            'size' => $upload->size,
            'md5' => $upload->md5,
            'uploaded_by' => $upload->uploadedBy,
        ]);

        return $this->toEntity($model);
    }

    private function toEntity(UploadModel $model): Upload
    {
        return new Upload(
            id: $model->id,
            originalName: $model->original_name,
            path: $model->path,
            disk: $model->disk,
            mimeType: $model->mime_type,
            size: $model->size,
            md5: $model->md5,
            uploadedBy: $model->uploaded_by,
        );
    }
}
