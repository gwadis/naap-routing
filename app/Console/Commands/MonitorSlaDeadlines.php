<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DocumentRouting;
use App\Models\User;
use App\Models\Document;
use App\Notifications\SlaBreachedNotification;
use App\Notifications\SlaNearDueNotification;
use Carbon\Carbon;

class MonitorSlaDeadlines extends Command
{
    protected $signature   = 'sla:monitor';
    protected $description = 'Check all active routing steps for SLA breaches and near-due warnings, then notify responsible users.';

    public function handle(): void
    {
        $this->info('🔎 Checking SLA deadlines for active routing steps...');

        $activeRoutings = DocumentRouting::with(['document.uploader', 'receiverUser', 'toOffice'])
            ->where('status', 'Pending')
            ->whereNotNull('sla_due_at')
            ->get();

        $breachedCount = 0;
        $nearDueCount  = 0;

        foreach ($activeRoutings as $routing) {
            $document = $routing->document;
            if (!$document) continue;

            $now        = Carbon::now();
            $slaStatus  = $routing->computed_sla_status;

            // --- OVERDUE ---
            if ($slaStatus === 'overdue' && $routing->sla_status !== 'overdue') {
                // Update the stored SLA status
                $routing->update(['sla_status' => 'overdue']);

                // Notify receiver
                if ($routing->receiverUser) {
                    try {
                        $routing->receiverUser->notify(new SlaBreachedNotification($document, $routing));
                        $this->line("  ⚠️  SLA Breach notified → {$routing->receiverUser->name} for doc #{$document->id}");
                    } catch (\Exception $e) {
                        $this->warn("  Failed to notify receiver: " . $e->getMessage());
                    }
                }

                // Notify uploader
                if ($document->uploader) {
                    try {
                        $document->uploader->notify(new SlaBreachedNotification($document, $routing));
                    } catch (\Exception $e) {
                        $this->warn("  Failed to notify uploader: " . $e->getMessage());
                    }
                }

                // Notify admins
                $admins = User::where('role', 'ADMIN')->get();
                foreach ($admins as $admin) {
                    try {
                        $admin->notify(new SlaBreachedNotification($document, $routing));
                    } catch (\Exception $e) {
                        // silent
                    }
                }

                $breachedCount++;
            }

            // --- NEAR DUE (within 2 hours, not yet notified for near_due) ---
            elseif ($slaStatus === 'near_due' && $routing->sla_status === 'on_time') {
                $routing->update(['sla_status' => 'near_due']);

                $remaining = $routing->sla_remaining ?? 'Unknown';

                // Notify receiver
                if ($routing->receiverUser) {
                    try {
                        $routing->receiverUser->notify(new SlaNearDueNotification($document, $routing, $remaining));
                        $this->line("  ⏰  Near-Due notified → {$routing->receiverUser->name} for doc #{$document->id} ({$remaining} remaining)");
                    } catch (\Exception $e) {
                        $this->warn("  Failed to notify near-due: " . $e->getMessage());
                    }
                }

                $nearDueCount++;
            }
        }

        $this->info("✅ SLA check complete. Breached: {$breachedCount} | Near Due: {$nearDueCount}");
    }
}
