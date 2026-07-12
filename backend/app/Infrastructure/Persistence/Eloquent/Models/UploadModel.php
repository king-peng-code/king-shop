<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\UploadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UploadModel extends Model
{
    /** @use HasFactory<UploadFactory> */
    use HasFactory;

    protected $table = 'uploads';

    protected $fillable = [
        'original_name',
        'path',
        'disk',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'uploaded_by');
    }

    protected static function newFactory(): UploadFactory
    {
        return UploadFactory::new();
    }
}
