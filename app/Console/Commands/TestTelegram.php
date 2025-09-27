<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class TestTelegram extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:test {message? : Test message to send}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Telegram service configuration and connectivity';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $telegram = new TelegramService();
        $message = $this->argument('message') ?? '🧪 Test message from Crypto Analysis System';

        $this->info('🧪 Testing Telegram Service...');

        // Check configuration
        if (!$telegram->isConfigured()) {
            $this->error('❌ Telegram not configured!');
            $this->line('Please add these to your .env file:');
            $this->line('TELEGRAM_BOT_TOKEN=your_bot_token_here');
            $this->line('TELEGRAM_CHAT_ID=your_chat_id_here');
            return 1;
        }

        $this->info('✅ Telegram configured');

        // Test message
        $this->info('📤 Sending test message...');

        $success = $telegram->sendMessage($message);

        if ($success) {
            $this->info('✅ Test message sent successfully!');
            $this->info('Check your Telegram app for the message.');
        } else {
            $this->error('❌ Failed to send test message!');
            $this->line('Possible issues:');
            $this->line('• Invalid bot token');
            $this->line('• Invalid chat ID');
            $this->line('• Network connectivity issues');
            $this->line('• Bot not started or not added to chat');
            return 1;
        }

        return 0;
    }
}