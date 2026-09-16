<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DocumentRouting;
use App\Models\User;
use App\Notifications\VpaaAlarmNotification;
use Carbon\Carbon;

class SendVpaaAlarm extends Command
{
    protected $signature = 'documents:vpaa-alarm';
    protected $description = 'Check for high priority documents sitting at a routing step for more than 3 hours and notify VPAA';

    public function handle()
    {
        $this->info('Checking high-priority documents for VPAA alarms...');

        // Find active Pending routings
        $activeRoutings = DocumentRouting::where('status', 'Pending')
            ->whereHas('document', function ($q) {
                $q->where('priority', 'High');
            })
            ->with(['document', 'toOffice'])
            ->get();

        $vpaaUser = User::where('username', 'vpaa')
            ->orWhere('email', 'vpaa@naap.org')
            ->first();

        if (!$vpaaUser) {
            $this->error('VPAA user not found in the database. Seeding is required.');
            return;
        }

        $alarmCount = 0;

        foreach ($activeRoutings as $routing) {
            $document = $routing->document;
            $officeName = $routing->toOffice?->name ?? 'Unknown Office';

            // Calculate hours idle
            $hoursIdle = Carbon::now()->diffInHours($routing->created_at);

            if ($hoursIdle >= 3) {
                $lastAlarm = $routing->last_vpaa_alarm_at;
                
                // Alarm if never alarmed or if 3 hours have passed since last alarm
                if (is_null($lastAlarm) || Carbon::now()->diffInHours($lastAlarm) >= 3) {
                    $vpaaUser->notify(new VpaaAlarmNotification($document, $officeName, $hoursIdle));
                    
                    $routing->update([
                        'last_vpaa_alarm_at' => Carbon::now()
                    ]);

                    $this->info("VPAA alarm sent for Document ID #{$document->id} stuck at {$officeName} for {$hoursIdle} hours.");
                    $alarmCount++;
                }
            }
        }

        $this->info("Completed checks. Sent {$alarmCount} VPAA alarms.");
    }
}
