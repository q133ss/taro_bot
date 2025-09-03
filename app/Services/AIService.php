<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AIService
{
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key'); // добавьте в .env OPENAI_API_KEY
        $this->model = 'gpt-3.5-turbo'; // или gpt-4
    }

    /**
     * Получить ответ от модели OpenAI
     */
    public function getAnswer(string $message): string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type'  => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $message
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => 800, // для нумерологии можно увеличить до 1500 токенов
            ]);

            $data = $response->json();

            if (isset($data['choices'][0]['message']['content'])) {
                return trim($data['choices'][0]['message']['content']);
            }

            return "Извините, не удалось получить ответ от AI.";
        } catch (\Exception $e) {
            // Логируем ошибки
            \Log::error("OpenAI API error: ".$e->getMessage());
            return "Произошла ошибка при обращении к AI.";
        }
    }
}
