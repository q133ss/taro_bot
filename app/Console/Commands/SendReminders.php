<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Attributes\AsCommand;
use App\Models\Reminder;
use App\Services\TgService;

#[AsCommand(name: 'send:reminders', description: 'Send scheduled reminder messages')]
class SendReminders extends Command
{
    protected $signature = 'send:reminders';
    protected $description = 'Send scheduled reminder messages';

    public function __construct(protected TgService $tg)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        Reminder::whereNull('sent_at')
            ->where('send_at', '<=', now())
            ->chunkById(100, function ($reminders) {
                foreach ($reminders as $reminder) {
                    $this->tg->sendMessage($reminder->chat_id, $reminder->message);
                    $reminder->sent_at = now();
                    $reminder->save();
                }
            });
    }
}
