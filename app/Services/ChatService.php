<?php

namespace App\Services;

use App\Models\User;
use App\Models\TgSession;

class ChatService
{
    public function __construct(
        protected TgService $tg,
        protected AIService $ai
    ) {}

    public function handleUpdate(array $update)
    {
        if (!isset($update['message']['text'])) return;

        $chatId = $update['message']['chat']['id'];
        $text = trim($update['message']['text']);

        // Получаем или создаем сессию
        $session = TgSession::firstOrCreate(
            ['chat_id' => $chatId],
            ['state' => 'start', 'data' => []]
        );

        $user = User::firstOrCreate(['chat_id' => $chatId]);

        switch ($session->state) {
            case 'start':
                $this->tg->sendMessage($chatId,
                    "Привет, я Эльза — твоя подружка 🌸\n".
                    "Рада, что ты заглянула ко мне. Здесь можно быть настоящей — я рядом, чтобы слушать, поддерживать и помогать.\n".
                    "Без осуждений, без масок — только тёплый диалог.\n".
                    "Хочешь познакомиться поближе? 💌",
                    [['Старт']]
                );
                $session->state = 'main_menu';
                break;

            case 'main_menu':
                $this->handleMainMenu($user, $chatId, $text);
                break;

            default:
                // Для всех других состояний пока возвращаем в меню
                $this->showMainMenu($chatId, $user);
                $session->state = 'main_menu';
                break;
        }

        $session->save();
    }

    // --- Основное меню ---
    protected function handleMainMenu(User $user, int $chatId, string $text)
    {
        switch ($text) {
            case '🃏 Раздел Таро':
                $this->handleTaro($user, $chatId);
                break;

            case '🔢 Раздел Нумерология':
                $this->handleNumerology($user, $chatId);
                break;

            case '♒ Раздел Гороскоп':
                $this->handleHoroscope($user, $chatId);
                break;

            case '💬 Раздел Подружка':
                $this->handleFriend($user, $chatId);
                break;

            case 'Подписка':
                $this->handleSubscription($user, $chatId);
                break;

            case 'Помощь':
                $this->handleHelp($user, $chatId);
                break;

            default:
                $this->showMainMenu($chatId, $user);
                break;
        }
    }

    // --- Заглушки для разделов ---
    protected function handleTaro(User $user, int $chatId)
    {
        // Отправляем сообщение о загрузке
        $this->tg->sendMessage($chatId, "🃏 Запускаю расклад Таро...");

        // Формируем промпт для AI
        $prompt = "Сделай расклад Таро для пользователя.
    Ответ должен быть:
    - Эмпатичным и поддерживающим
    - На русском языке
    - Объемом 300-500 символов
    - Включать описание 3 карт: Ситуация, Вызов, Совет
    - Закончить на позитивной ноте";

        // Получаем ответ от AI
        $aiResponse = $this->ai->getAnswer($prompt);

        // Отправляем ответ пользователю
        $this->tg->sendMessage($chatId, $aiResponse);
    }

    protected function handleNumerology(User $user, int $chatId)
    {
        $this->tg->sendMessage($chatId, "Раздел Нумерология пока в разработке 🔢");
    }

    protected function handleHoroscope(User $user, int $chatId)
    {
        $this->tg->sendMessage($chatId, "Раздел Гороскоп пока в разработке ♒");
    }

    protected function handleFriend(User $user, int $chatId)
    {
        $this->tg->sendMessage($chatId, "Раздел Подружка пока в разработке 💬");
    }

    protected function handleSubscription(User $user, int $chatId)
    {
        $this->tg->sendMessage($chatId, "Раздел Подписка пока в разработке 💌");
    }

    protected function handleHelp(User $user, int $chatId)
    {
        $this->tg->sendMessage($chatId,
            "Я могу помочь тебе с:\n".
            "🃏 Таро\n".
            "🔢 Нумерология\n".
            "♒ Гороскоп\n".
            "💬 Эмоциональная поддержка\n\n".
            "Также доступны разделы Подписка и Помощь."
        );
    }

    // --- Отображение главного меню ---
    protected function showMainMenu(int $chatId, User $user)
    {
        $this->tg->sendMessage($chatId,
            "Привет, {$user->name}! Выберите раздел:",
            [
                ['🃏 Раздел Таро', '🔢 Раздел Нумерология'],
                ['♒ Раздел Гороскоп', '💬 Раздел Подружка'],
                ['Подписка', 'Помощь']
            ]
        );
    }
}
