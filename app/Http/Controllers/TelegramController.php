<?php

namespace App\Http\Controllers;

use App\Services\ChatService;
use Illuminate\Http\Request;

class TelegramController extends Controller
{
    protected ChatService $chat;

    public function __construct(ChatService $chat)
    {
        $this->chat = $chat;
    }

    public function webhook(Request $request)
    {
        $update = $request->all(); // Получаем апдейт от Telegram

        // Отправляем его на обработку
        $this->chat->handleUpdate($update);

        // Telegram ожидает 200 OK
        return response()->json(['ok' => true]);
    }
}
