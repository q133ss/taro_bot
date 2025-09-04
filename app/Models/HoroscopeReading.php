<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HoroscopeReading extends Model
{
    protected $fillable = [
        'chat_id', 'user_name', 'surname', 'birth_date', 'birth_time', 'sign', 'type', 'result', 'meta'
    ];

    protected $casts = [
        'meta' => 'array',
        'birth_date' => 'date',
        'birth_time' => 'datetime:H:i',
    ];
}
