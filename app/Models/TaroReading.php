<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaroReading extends Model
{
    protected $fillable = [
        'chat_id', 'user_name', 'birth_date', 'type',
        'question', 'cards_count', 'result', 'meta'
    ];

    protected $casts = [
        'meta' => 'array',
        'birth_date' => 'date',
    ];
}
