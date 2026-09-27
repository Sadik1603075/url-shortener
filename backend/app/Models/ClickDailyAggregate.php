<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClickDailyAggregate extends Model
{
    protected $fillable = [
        'date',
        'clicks',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'clicks' => 'integer',
        ];
    }
}
