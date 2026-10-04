<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Document;
use App\Models\User;
use App\Notifications\DocumentDueNotification;
use Carbon\Carbon;

class CheckDocumentDueDates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:check-due-dates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for documents approaching due date and send notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking document due dates...');

        // Get documents due in the next 3 days
        $upcomingDue = Document::where('due_date', '>=', Carbon::now())
                               ->where('due_date', '<=', Carbon::now()->addDays(3))
                               ->get();

        foreach ($upcomingDue as $document) {
            // Notify the uploader or admin
            $user = User::find($document->uploaded_by);
            if ($user) {
                $user->notify(new DocumentDueNotification($document));
                $this->info("Notification sent for document: {$document->title}");
            }
        }

        // Audit overdue active documents without overwriting workflow status
        $overdueActiveDocs = Document::where('due_date', '<', Carbon::now())
            ->whereNotIn('status', ['Completed', 'Archived', 'Cancelled'])
            ->get();

        // Update routing SLA status where applicable
        \App\Models\DocumentRouting::where('sla_due_at', '<', Carbon::now())
            ->whereIn('status', ['Pending', 'In Transit', 'Under Review', 'Processing'])
            ->where('sla_status', '!=', 'delayed')
            ->update(['sla_status' => 'delayed']);

        $this->info("Identified {$overdueActiveDocs->count()} active documents past SLA due date.");
    }
}