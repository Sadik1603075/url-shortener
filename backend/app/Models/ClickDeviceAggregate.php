<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClickDeviceAggregate extends Model
{
    protected $fillable = [
        'browser',
        'os',
        'device_type',
        'clicks',
    ];

    protected function casts(): array
    {
        return [
            'clicks' => 'integer',
        ];
    }
}
