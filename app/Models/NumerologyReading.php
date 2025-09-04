<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NumerologyReading extends Model
{
    protected $fillable = [
        'chat_id', 'user_name', 'surname', 'birth_date', 'type', 'result', 'meta'
    ];

    protected $casts = [
        'meta' => 'array',
        'birth_date' => 'date',
    ];
}
