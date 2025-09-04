<?php

namespace App\Services;

use App\Models\User;
use App\Models\TgSession;
use App\Models\TaroReading;
use App\Models\NumerologyReading;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ChatService
{
    public function __construct(
        protected TgService $tg,
        protected AIService $ai
    ) {}

    /**
     * Главный обработчик апдейта (вебхук)
     */
    public function handleUpdate(array $update)
    {
        // Ожидаем текстовые сообщения (для простоты)
        $message = $update['message'] ?? null;
        if (!$message) return;

        $chatId = $message['chat']['id'];
        $text = trim($message['text'] ?? '');

        // Получаем или создаём сессию и пользователя
        $session = TgSession::firstOrCreate(['chat_id' => $chatId], ['state' => 'start', 'data' => []]);
        $data = $session->data ?? [];

        $user = User::firstOrCreate(['chat_id' => $chatId]);

        // Простая маршрутизация по состояниям
        switch ($session->state) {
            case 'start':
                $this->showWelcome($chatId);
                $session->state = 'ask_consent';
                break;

            case 'ask_consent':
                if ($this->isPositive($text)) {
                    $this->tg->sendMessage($chatId,
                        "Спасибо ❤️\nПожалуйста, укажи своё имя (Напиши имя, чтобы я могла к тебе обращаться):");
                    $session->state = 'ask_name';
                } else {
                    $this->tg->sendMessage($chatId, "Нажми «Старт», когда будешь готова начать.", [['Старт']]);
                }
                break;

            case 'ask_name':
                if (empty($text)) {
                    $this->tg->sendMessage($chatId, "Пожалуйста, напиши имя (например: Анна).");
                    break;
                }
                $user->name = mb_substr($text, 0, 100);
                $user->save();

                $this->tg->sendMessage($chatId, "Приятно познакомиться, {$user->name}! Теперь, пожалуйста, введи дату рождения в формате ДД.MM.ГГГГ");
                $session->state = 'ask_birth_date';
                break;

            case 'ask_birth_date':
                if (!$this->validateDate($text)) {
                    $this->tg->sendMessage($chatId, "Неверный формат даты. Введите, пожалуйста, в формате ДД.MM.ГГГГ (например: 08.09.1990).");
                    break;
                }
                $user->birth_date = Carbon::createFromFormat('d.m.Y', $text)->toDateString();
                $user->save();

                $this->showMainMenu($chatId, $user);
                $session->state = 'main_menu';
                break;

            case 'main_menu':
                $this->routeMainMenu($session, $user, $chatId, $text);
                break;

            case 'taro_menu':
                // Ожидаем выбор типа расклада или назад
                $this->routeTaroMenu($session, $user, $chatId, $text);
                break;

            case 'taro_ask_question':
                // Ожидаем вопрос пользователя для расклада
                $this->handleTaroQuestion($session, $user, $chatId, $text);
                break;

            case 'numerology_ask_surname':
                if (empty($text)) {
                    $this->tg->sendMessage($chatId, 'Пожалуйста, напиши фамилию.');
                    break;
                }
                $user->surname = mb_substr($text, 0, 100);
                $user->save();

                $this->showNumerologyMenu($chatId, $user);
                $session->state = 'numerology_menu';
                break;

            case 'numerology_menu':
                $this->routeNumerologyMenu($session, $user, $chatId, $text);
                break;

            default:
                // На всякий случай — возвращаем в главное меню
                $this->showMainMenu($chatId, $user);
                $session->state = 'main_menu';
                break;
        }

        // Сохраняем данные сессии
        $session->data = $data;
        $session->save();
    }

    /* ---------- Helper methods ---------- */

    protected function showWelcome(int $chatId)
    {
        $text = "Привет, я Эльза — твоя подружка 🌸\n".
            "Рада, что ты заглянула ко мне. Здесь можно быть настоящей — я рядом, чтобы слушать, поддерживать и помогать.\n".
            "Без осуждений, без масок — только тёплый диалог.\n".
            "Хочешь познакомиться поближе? Жми «Старт» 💌\n\n".
            "Перед тем как продолжить, нужно согласие на обработку персональных данных (Имя, дата рождения).";
        $this->tg->sendMessage($chatId, $text, [['Старт']]);
    }

    protected function showMainMenu(int $chatId, User $user)
    {
        $name = $user->name ? $user->name : 'Подруга';
        $text = "{$name}, теперь давай выберем, с чего начнём 💫\nЯ рядом, чтобы помочь — просто выбери раздел, который тебе сейчас ближе.";
        $keyboard = [
            ['🃏 Расклад Таро', '🔢 Нумерология'],
            ['♒ Гороскоп', '💬 Подружка'],
            ['Подписка', 'Помощь']
        ];
        $this->tg->sendMessage($chatId, $text, $keyboard);
    }

    protected function routeMainMenu($session, User $user, int $chatId, string $text)
    {
        switch ($text) {
            case '🃏 Расклад Таро':
                // Переходим в меню таро
                $this->tg->sendMessage($chatId, "Выбери тип расклада:", [
                    ['Таро на день', 'Таро на любовь'],
                    ['Другой вопрос', 'Назад в меню']
                ]);
                $session->state = 'taro_menu';
                break;

            case '🔢 Нумерология':
                if (!$user->surname) {
                    $this->tg->sendMessage($chatId, 'Пожалуйста, укажи свою фамилию:');
                    $session->state = 'numerology_ask_surname';
                } else {
                    $this->showNumerologyMenu($chatId, $user);
                    $session->state = 'numerology_menu';
                }
                break;
            case '♒ Гороскоп':
            case '💬 Подружка':
            case 'Подписка':
                // Для остальных — заглушки (реализованы отдельно)
                $this->tg->sendMessage($chatId, "Этот раздел пока в разработке. Выбери, пожалуйста, другой раздел или вернись позже.", [['Назад в меню']]);
                break;

            case 'Помощь':
                $this->tg->sendMessage($chatId,
                    "Я помогу:\n• Сформулировать вопрос к Таро\n• Сделать базовый расклад (3 карты бесплатно) или глубокий расклад (7 карт для подписчиков)\n\n".
                    "Просто выбери «🃏 Расклад Таро» и следуй подсказкам.");
                break;

            default:
                $this->showMainMenu($chatId, $user);
                break;
        }
    }

    protected function routeTaroMenu($session, User $user, int $chatId, string $text)
    {
        if ($text === 'Назад в меню') {
            $this->showMainMenu($chatId, $user);
            $session->state = 'main_menu';
            return;
        }

        // Сохраняем тип расклада
        $type = $text; // Таро на день / Таро на любовь / Другой вопрос
        $session->data = array_merge($session->data ?? [], ['taro_type' => $type]);

        // Предложим подсказки по формулировке вопроса и кнопки
        $suggest = "Отлично — мы выбрали: <b>{$type}</b>.\n\n".
            "Чтобы получить точный ответ, сформулируй конкретный вопрос. Примеры:\n".
            "✅ «Какие чувства у Никиты ко мне?»\n".
            "✅ «Будем ли мы вместе с Никитой?»\n".
            "❌ Не: «Что меня ждет с ним?» — слишком общее.\n\n".
            "Напиши свой вопрос или нажми «Другой вопрос» для свободного ввода.";
        $this->tg->sendMessage($chatId, $suggest, [
            ['Задать вопрос'],
            ['Назад в меню']
        ]);

        // Ожидаем вопрос
        $session->state = 'taro_ask_question';
    }

    protected function handleTaroQuestion($session, User $user, int $chatId, string $text)
    {
        if ($text === 'Назад в меню') {
            $this->showMainMenu($chatId, $user);
            $session->state = 'main_menu';
            return;
        }

        // Определяем количество карт по подписке
        $cards = ($user->subscription === 'paid') ? 7 : 3;

        // Проверка лимита бесплатных раскладов
        if ($user->subscription !== 'paid') {
            $freeUsedToday = TaroReading::where('chat_id', $user->chat_id)
                ->whereDate('created_at', now()->toDateString())
                ->where('cards_count', 3)
                ->count();

            if ($freeUsedToday >= 3) {
                $this->tg->sendMessage($chatId,
                    "Ты использовала все 3 бесплатных расклада на сегодня 🌸\n\n" .
                    "Если хочешь продолжить, можно оформить платную подписку (7 карт и персональные рекомендации).",
                    [['Подписка', 'Назад в меню']]
                );
                $session->state = 'main_menu';
                return;
            }
        }

        // Проверка лимита для платных пользователей (по желанию можно ограничить, например, 7 раскладов в день)
        if ($user->subscription === 'paid') {
            $paidUsedToday = TaroReading::where('chat_id', $user->chat_id)
                ->whereDate('created_at', now()->toDateString())
                ->where('cards_count', 3)
                ->count();

            // TODO выносим в БД
            // Пример: ограничиваем до 10 платных раскладов в день
            if ($paidUsedToday >= 10) {
                $this->tg->sendMessage($chatId,
                    "Ты использовала все 10 платных раскладов на сегодня 🌸\n\n" .
                    "Завтра сможешь продолжить или обратись к поддержке, если нужна расширенная сессия.",
                    [['Назад в меню']]
                );
                $session->state = 'main_menu';
                return;
            }
        }

        // Получаем тип расклада из сессии
        $type = $session->data['taro_type'] ?? 'Расклад';

        // Подготавливаем промпт и вызываем AI
        $prompt = $this->buildTaroPrompt($user->name ?? 'Подруга', $type, $text, $cards);

        // Мягкое сообщение ожидания
        $this->tg->sendMessage($chatId, "Сейчас я посоветуюсь с картами и соберу расклад — это займёт пару секунд ✨");

        $result = $this->ai->getAnswer($prompt);

        if (!$result) {
            $result = "К сожалению, сейчас я не могу подготовить расклад. Но не переживай — мы вернёмся к этому чуть позже.";
        }

        if (mb_strlen($result) > 4000) {
            $result = mb_substr($result, 0, 4000) . '...';
        }

        // Формируем финальный текст
        $final = "Спасибо, {$user->name}, что поделилась своим вопросом 🌸\n\n" .
            "<b>Вопрос:</b> {$text}\n\n" .
            "<b>Расклад ({$cards} карты):</b>\n" .
            "{$result}\n\n" .
            "Спасибо, что открываешься — если хочешь ещё углубиться, рассмотрим платную версию (7 карт и персональные рекомендации).";

        // Сохраняем в БД
        TaroReading::create([
            'chat_id' => $user->chat_id,
            'user_name' => $user->name,
            'birth_date' => $user->birth_date,
            'type' => $type,
            'question' => $text,
            'cards_count' => $cards,
            'result' => $result,
            'meta' => [
                'generated_at' => now()->toDateTimeString(),
                'prompt' => $this->shorten($prompt, 800),
            ],
        ]);

        // Отправляем результат и клавиатуру
        $keyboard = $user->subscription === 'paid'
            ? [['Задать ещё вопрос', 'Назад в меню']]
            : [['Подписка', 'Задать ещё вопрос'], ['Назад в меню']];

        $this->tg->sendMessage($chatId, $final, $keyboard);

        // Сохраняем состояние
        $session->state = 'taro_menu';
    }

    protected function showNumerologyMenu(int $chatId, User $user)
    {
        $text = 'Выбери формат нумерологического разбора:';
        $keyboard = [
            ['Бесплатно', 'Полный анализ'],
            ['Назад в меню']
        ];
        $this->tg->sendMessage($chatId, $text, $keyboard);
    }

    protected function routeNumerologyMenu($session, User $user, int $chatId, string $text)
    {
        switch ($text) {
            case 'Бесплатно':
                $this->handleNumerologyFree($session, $user, $chatId);
                break;

            case 'Полный анализ':
                $this->handleNumerologyPaid($session, $user, $chatId);
                break;

            case 'Подписка':
                $this->tg->sendMessage($chatId, 'Выбор платных подписок пока недоступен.', [['Назад в меню']]);
                break;

            case 'Задать вопрос':
                $this->tg->sendMessage($chatId, 'Функция дополнительных вопросов пока недоступна.', [['Назад в меню']]);
                break;

            case 'Назад в меню':
                $this->showMainMenu($chatId, $user);
                $session->state = 'main_menu';
                break;

            default:
                $this->showNumerologyMenu($chatId, $user);
                break;
        }
    }

    protected function handleNumerologyFree($session, User $user, int $chatId)
    {
        $prompt = $this->buildMoneyCodePrompt($user->name ?? '', $user->birth_date);
        $this->tg->sendMessage($chatId, 'Считаю твой денежный код, подожди пару секунд ✨');
        $result = $this->ai->getAnswer($prompt);

        if (!$result) {
            $result = 'Сейчас не получается рассчитать код. Попробуй ещё раз позже.';
        }

        if (mb_strlen($result) > 4000) {
            $result = mb_substr($result, 0, 4000) . '...';
        }

        $final = $result . "\n\n" .
            'Это твой денежный код. Он помогает понять, как ты взаимодействуешь с финансовыми потоками. 💸\n' .
            'В платной версии я сделаю для тебя подробный разбор: твои сильные стороны, зоны роста, кармические задачи и код активации изобилия. ✨\n' .
            '👉 Подпишись, чтобы получить расширенный нумерологический портрет.';

        $this->tg->sendMessage($chatId, $final, [['Подписка', 'Назад в меню']]);

        NumerologyReading::create([
            'chat_id' => $user->chat_id,
            'user_name' => $user->name,
            'surname' => $user->surname,
            'birth_date' => $user->birth_date,
            'type' => 'money_code',
            'result' => $result,
            'meta' => [
                'generated_at' => now()->toDateTimeString(),
                'prompt' => $this->shorten($prompt, 800),
            ],
        ]);

        $session->state = 'numerology_menu';
    }

    protected function handleNumerologyPaid($session, User $user, int $chatId)
    {
        if ($user->subscription !== 'paid') {
            $this->tg->sendMessage($chatId,
                'Подробный нумерологический анализ доступен по подписке.',
                [['Подписка', 'Назад в меню']]
            );
            $session->state = 'numerology_menu';
            return;
        }

        $birth = $user->birth_date ? Carbon::parse($user->birth_date)->format('d.m.Y') : '';
        $prompt = $this->buildNumerologyPrompt($user->name ?? '', $user->surname ?? '', $birth);
        $this->tg->sendMessage($chatId, 'Собираю твою нумерологическую карту, подожди чуть-чуть ✨');
        $result = $this->ai->getAnswer($prompt);

        if (!$result) {
            $result = 'Сейчас не получается подготовить анализ. Попробуй позже.';
        }

        if (mb_strlen($result) > 4000) {
            $result = mb_substr($result, 0, 4000) . '...';
        }

        $this->tg->sendMessage($chatId, $result, [['Задать вопрос', 'Назад в меню']]);

        NumerologyReading::create([
            'chat_id' => $user->chat_id,
            'user_name' => $user->name,
            'surname' => $user->surname,
            'birth_date' => $user->birth_date,
            'type' => 'full',
            'result' => $result,
            'meta' => [
                'generated_at' => now()->toDateTimeString(),
                'prompt' => $this->shorten($prompt, 800),
            ],
        ]);

        $session->state = 'numerology_menu';
    }

    /* ---------- Вспомогательные утилиты ---------- */

    protected function isPositive(string $text): bool
    {
        $t = mb_strtolower($text);
        return in_array($t, ['старт', 'да', 'ok', 'okey', 'начать', 'start', 'давай', 'готово']);
    }

    protected function validateDate(string $text): bool
    {
        if (!preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $text)) return false;
        try {
            $d = Carbon::createFromFormat('d.m.Y', $text);
            return checkdate($d->month, $d->day, $d->year);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function buildMoneyCodePrompt(string $name, ?string $birthDate): string
    {
        $birth = $birthDate ? Carbon::parse($birthDate)->format('d.m.Y') : '';
        return "На основе имени {$name} и даты рождения {$birth} вычисли денежный (финансовый) код. " .
            "Верни одну цифру и краткое пояснение (1-2 предложения). Отвечай по-русски.";
    }

    protected function buildNumerologyPrompt(string $name, string $surname, string $birthDate): string
    {
        $system = "Ты — дружелюбный и заботливый нумеролог. Отвечай по-русски.";
        $instruction = "Рассчитай и расшифруй ключевые числа нумерологии по имени {$name}, фамилии {$surname} и дате рождения {$birthDate}. " .
            "Укажи число жизненного пути, число судьбы, число души, число личности, кармические долги и задачи, матрицу Пифагора. " .
            "Сформируй структурированный отчёт: основные числа с кратким описанием и влиянием, текстовый прогноз 700-1500 символов по сферам (личность и потенциал, карьера и деньги, отношения и семья, сильные и слабые стороны, подсказки для настоящего периода жизни).";

        return $system . "\n\n" . $instruction;
    }

    /**
     * Построение промпта для OpenAI для таро-расклада (чёткий, дружелюбный, ограничение длины).
     */
    protected function buildTaroPrompt(string $name, string $type, string $question, int $cards): string
    {
        $system = "Ты — нежный и заботливый таролог, говоришь мягко и поддерживающе. Отвечай по-русски.";
        $instruction = "Для пользователя {$name} сделай расклад \"{$type}\" на {$cards} карт(ы). " .
            "Дай название каждой карты (если возможно), краткую интерпретацию до 400 символов для каждой карты и общий вывод по раскладу (до 400 символов). " .
            "Стиль: мягкий, поддерживающий, без категоричных предсказаний. В конце предложи 2-3 уточняющих вопроса, которые пользователь может задать для более точного ответа. " .
            "Вопрос пользователя: «{$question}».";
        // Собираем один текстовый prompt, который отправим в user role (можно расширить на system/user messages)
        return $system . "\n\n" . $instruction;
    }

    protected function shorten(string $text, int $limit = 200)
    {
        return mb_strlen($text) <= $limit ? $text : mb_substr($text, 0, $limit) . '...';
    }
}
