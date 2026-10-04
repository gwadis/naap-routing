<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SmsService;

class SendSmsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:send 
                            {phone? : The mobile number (e.g., 09690222557)} 
                            {message? : The SMS message content} 
                            {--driver= : Optional SMS driver (semaphore, twilio, log, none)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch or draft an emergency SMS notification (Zero-Cost / Optional Fallback)';

    /**
     * Execute the console command.
     */
    public function handle(SmsService $smsService): int
    {
        $phone = $this->argument('phone') ?? '09690222557';
        $message = $this->argument('message') 
            ?? "NAAP Routing Alert: Document TRK-TEST requires your attention.";
        $driver = $this->option('driver');

        $this->info("=================================================");
        $this->info("  NAAP Enterprise Notification & SMS Fallback");
        $this->info("=================================================");

        $normalized = SmsService::normalizePhoneNumber($phone);
        $this->line("• Recipient:       <comment>{$phone}</comment> (Normalized: {$normalized})");
        $this->line("• International:   <comment>" . SmsService::toE164($phone) . "</comment>");
        $this->line("• SMS Enabled:     <comment>" . (config('services.sms.enabled') ? 'YES' : 'NO (Zero Cost Default)') . "</comment>");
        $this->line("• Configured:      <comment>" . ($smsService->isConfigured() ? 'YES' : 'NO') . "</comment>");
        $this->line("• Message:         <comment>\"{$message}\"</comment>");
        $this->newLine();

        $result = $smsService->send($phone, $message, $driver);

        if ($result['success']) {
            $this->info("✔ SMS DISPATCH OUTCOME: SMS SENT");
            $this->line("• Provider:   " . ($result['provider'] ?? 'external'));
            $this->line("• Message ID: " . ($result['message_id'] ?? 'N/A'));
            $this->line("• Info:       " . ($result['info'] ?? 'Delivered'));
            return 0;
        }

        // SMS unavailable (Zero-cost default or unconfigured)
        $this->warn("ℹ SMS DISPATCH OUTCOME: SMS UNAVAILABLE");
        $this->line("• Info:           " . ($result['info'] ?? 'SMS unavailable — no SMS provider configured.'));
        $this->line("• Core Fallback:  Email and In-App notifications remain primary and fully functional.");
        
        $deviceUri = $result['device_sms_uri'] ?? SmsService::getDeviceSmsUri($phone, $message);
        if ($deviceUri) {
            $this->newLine();
            $this->line("• Optional Device Draft Link: <comment>{$deviceUri}</comment>");
            $this->line("• Label:                      <comment>Open SMS</comment>");
            $this->line("• Tip:                        Opening this URL opens the native SMS app on mobile or desktop.");
        }

        return 0;
    }
}
