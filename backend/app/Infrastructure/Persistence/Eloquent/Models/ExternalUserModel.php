<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\ExternalUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExternalUserModel extends Model
{
    /** @use HasFactory<ExternalUserFactory> */
    use HasFactory;

    protected $table = 'external_users';

    protected $fillable = [
        'provider', 'external_id', 'name', 'phone',
    ];

    protected static function newFactory(): ExternalUserFactory
    {
        return ExternalUserFactory::new();
    }
}
