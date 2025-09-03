<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// Команда для локальной разработки: ngrok http 8000
// https://558ecec64c12.ngrok-free.app/
// POST https://api.telegram.org/bot8496317015:AAFL3kQDIUrj7jVroE2-9DUk55YzQTpelGI/setWebhook?url=https://558ecec64c12.ngrok-free.app/api/webhook

// CHAT ID: 461612832

Route::post('/webhook', [App\Http\Controllers\TelegramController::class, 'webhook']);
