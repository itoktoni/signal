<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $botToken;
    private string $chatId;
    private string $apiUrl;

    public function __construct()
    {
        $this->botToken = config('telegram.bot_token', env('TELEGRAM_BOT_TOKEN', ''));
        $this->chatId = config('telegram.chat_id', env('TELEGRAM_CHAT_ID', ''));
        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}";
    }

    /**
     * Send message to Telegram
     */
    public function sendMessage(string $message, array $options = []): bool
    {
        if (empty($this->botToken) || empty($this->chatId)) {
            Log::warning('Telegram credentials not configured');
            return false;
        }

        try {
            $payload = array_merge([
                'chat_id' => $this->chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ], $options);

            Log::info('Sending Telegram message', [
                'url' => "{$this->apiUrl}/sendMessage",
                'payload' => array_merge($payload, [
                    'text' => substr($payload['text'], 0, 50) . '...' // Truncate for logging
                ])
            ]);

            $response = Http::timeout(10)->post("{$this->apiUrl}/sendMessage", $payload);

            if ($response->successful()) {
                Log::info('Telegram message sent successfully', [
                    'message_length' => strlen($message),
                    'response' => $response->json(),
                ]);
                return true;
            } else {
                Log::error('Failed to send Telegram message', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                    'bot_token_prefix' => substr($this->botToken, 0, 10) . '...',
                    'chat_id' => $this->chatId,
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Telegram service error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Send formatted analysis result to Telegram
     */
    public function sendAnalysisResult(string $coinCode, object $analysis, float $currentPrice, $apiProvider): bool
    {
        $signal = strtoupper(isset($analysis->signal) ? $analysis->signal : 'NEUTRAL');
        $confidence = isset($analysis->confidence) ? $analysis->confidence : 0;
        $signalEmoji = $this->getSignalEmoji($signal);

        $rupiah = getUsdToIdrRate();

        $message = "🚨 <b>HIGH CONFIDENCE SIGNAL</b> 🚨\n\n";
        $message .= "📈 <b>Coin:</b> {$coinCode}\n";
        $message .= "🎯 <b>Signal:</b> {$signalEmoji} {$signal}\n";
        $message .= "📊 <b>Confidence:</b> {$confidence}%\n\n";
        $message .= "💰 <b>Current Price:</b> $" . number_format($currentPrice, 4) . "\n";
        $message .= "💰 <b>Rupiah Price:</b> Rp" . number_format($currentPrice * $rupiah, 4) . "\n\n";

        // USD Values
        $message .= "💵 <b>USD VALUES:</b>\n";
        $message .= "🎯 <b>Entry:</b> $" . number_format(isset($analysis->entry) ? $analysis->entry : 0, 4) . "\n";
        $message .= "🛑 <b>Stop Loss:</b> $" . number_format(isset($analysis->stop_loss) ? $analysis->stop_loss : 0, 4) . "\n";
        $message .= "✅ <b>Take Profit:</b> $" . number_format(isset($analysis->take_profit) ? $analysis->take_profit : 0, 4) . "\n";

        // IDR Values (if available)
        if (isset($analysis->entry_idr) && isset($analysis->stop_loss_idr) && isset($analysis->take_profit_idr)) {
            $message .= "\n💶 <b>IDR VALUES:</b>\n";
            $message .= "🎯 <b>Entry:</b> Rp " . number_format($analysis->entry_idr, 0, ',', '.') . "\n";
            $message .= "🛑 <b>Stop Loss:</b> Rp " . number_format($analysis->stop_loss_idr, 0, ',', '.') . "\n";
            $message .= "✅ <b>Take Profit:</b> Rp " . number_format($analysis->take_profit_idr, 0, ',', '.') . "\n";
        }

        $riskReward = isset($analysis->risk_reward) ? $analysis->risk_reward : 'N/A';
        $message .= "\n📈 <b>Risk:Reward:</b> {$riskReward}\n";

        // Additional metrics if available
        if (isset($analysis->qty)) {
            $message .= "📊 <b>Quantity:</b> " . number_format($analysis->qty, 8) . "\n";
        }

        if (isset($analysis->fee)) {
            $message .= "💸 <b>Fee:</b> $" . number_format($analysis->fee, 4) . "\n";
            if (isset($analysis->fee_idr)) {
                $message .= "💸 <b>Fee IDR:</b> Rp " . number_format($analysis->fee_idr, 0, ',', '.') . "\n";
            }
        }

        if (isset($analysis->potential_profit)) {
            $message .= "\n📈 <b>Potential Profit:</b> $" . number_format($analysis->potential_profit, 4) . "\n";
            if (isset($analysis->potential_profit_idr)) {
                $message .= "📈 <b>Potential Profit IDR:</b> Rp " . number_format($analysis->potential_profit_idr, 0, ',', '.') . "\n";
            }
        }

        if (isset($analysis->potential_loss)) {
            $message .= "📉 <b>Potential Loss:</b> $" . number_format($analysis->potential_loss, 4) . "\n";
            if (isset($analysis->potential_loss_idr)) {
                $message .= "📉 <b>Potential Loss IDR:</b> Rp " . number_format($analysis->potential_loss_idr, 0, ',', '.') . "\n";
            }
        }

        if (isset($analysis->notes) && !empty($analysis->notes)) {
            $message .= "\n📝 <b>Notes:</b>\n{$analysis->notes}\n";
        }

        $message .= "\n⚡ <i>Generated by Crypto Analysis System</i>";

        return $this->sendMessage($message);
    }

    /**
     * Get emoji for signal type
     */
    private function getSignalEmoji(string $signal): string
    {
        switch (strtoupper($signal)) {
            case 'BUY':
                return '📈';
            case 'SELL':
                return '📉';
            case 'NEUTRAL':
                return '➖';
            default:
                return '❓';
        }
    }

    /**
     * Check if Telegram is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->botToken) && !empty($this->chatId);
    }

    /**
     * Get configuration status for debugging
     */
    public function getConfigurationStatus(): array
    {
        return [
            'bot_token_configured' => !empty($this->botToken),
            'chat_id_configured' => !empty($this->chatId),
            'bot_token_length' => strlen($this->botToken),
            'chat_id_length' => strlen($this->chatId),
        ];
    }
}