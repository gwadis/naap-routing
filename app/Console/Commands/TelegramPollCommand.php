<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramPollCommand extends Command
{
    protected $signature = 'telegram:poll {--once : Run once instead of continuously}';
    protected $description = 'Poll incoming Telegram Bot updates for local testing';

    public function handle(TelegramService $telegramService): int
    {
        if (!$telegramService->isConfigured()) {
            $this->error('Telegram Bot is not configured. Set TELEGRAM_BOT_TOKEN in .env.');
            return 1;
        }

        $this->info("Polling Telegram bot @{$telegramService->getBotUsername()}...");

        if ($this->option('once')) {
            $count = $telegramService->pollUpdates();
            $this->info("Processed {$count} update(s).");
            return 0;
        }

        $this->info("Press Ctrl+C to stop polling.");

        while (true) {
            $count = $telegramService->pollUpdates();
            if ($count > 0) {
                $this->info("[" . now()->toTimeString() . "] Processed {$count} update(s).");
            }
            sleep(2);
        }

        return 0;
    }
}
