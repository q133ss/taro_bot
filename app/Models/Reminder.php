<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    protected $fillable = ['chat_id', 'message', 'send_at', 'sent_at'];

    protected $casts = [
        'send_at' => 'datetime',
        'sent_at' => 'datetime',
    ];
}
