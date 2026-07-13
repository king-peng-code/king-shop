<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class ThirdPartyCallbackLogModel extends Model
{
    protected $table = 'third_party_callback_logs';

    protected $fillable = [
        'channel',
        'request_method',
        'request_headers',
        'request_body',
        'response_status',
        'response_body',
        'ip_address',
    ];
}
