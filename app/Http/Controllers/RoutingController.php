<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Document;
use App\Models\DocumentRouting;
use App\Models\Office;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RoutingController extends Controller
{
    /**
     * Display the active routing dashboard with pagination.
     */
    public function index()
    {
        $offices = Office::orderBy('name', 'asc')->get();
        $users   = User::with('department')->orderBy('name', 'asc')->get();

        $documents = Document::with(['originOffice', 'currentOffice', 'destinationOffice', 'receiverUser.department'])
            ->latest()
            ->paginate(10);

        return view('routing', compact('documents', 'offices', 'users'));
    }

    /**
     * Update document location/receiver and log the movement.
     */
    public function routeDocument(Request $request, $id)
    {
        $request->validate([
            'office_id'          => 'required|exists:offices,id',
            'receiver_user_ids'  => 'nullable|array',
            'receiver_user_ids.*' => 'exists:users,id',
            'notes'              => 'nullable|string|max:500',
        ]);

        // Custom check: make sure every receiver in receiver_user_ids belongs to office_id
        $officeId = $request->input('office_id');
        $receiverUserIds = $request->input('receiver_user_ids', []);
        foreach ($receiverUserIds as $userId) {
            if ($userId) {
                $user = User::find($userId);
                if (!$user || $user->office_id != $officeId) {
                    $officeName = Office::find($officeId)?->name ?? 'Selected Office';
                    $userName = $user?->name ?? 'Selected User';
                    return back()->withErrors([
                        'receiver_user_ids' => "Routing validation failed: Receiver '{$userName}' does not belong to the office '{$officeName}'."
                    ])->withInput();
                }
            }
        }

        try {
            DB::transaction(function () use ($request, $id) {
                $document      = Document::findOrFail($id);
                $fromOfficeId  = $document->current_office_id ?? $document->origin_office_id;
                $targetOffice  = Office::findOrFail($request->office_id);
                $oldOfficeName = $document->currentOffice->name ?? 'Unknown';

                $newStatus = ($request->office_id == $document->destination_office_id)
                    ? 'Completed'
                    : 'In Transit';

                // Build the receiver list — strip current user to avoid self-routing
                $receiverUserIds = collect($request->input('receiver_user_ids', []))
                    ->filter()
                    ->map(fn($uid) => (int) $uid)
                    ->reject(fn($uid) => $uid === (int) session('user_id'))
                    ->unique()
                    ->values()
                    ->all();

                $updateData = [
                    'current_office_id' => $request->office_id,
                    'status'            => $newStatus,
                ];

                if (!empty($receiverUserIds)) {
                    $updateData['receiver_user_id'] = $receiverUserIds[0];
                }

                // 1. Update document record
                $document->update($updateData);

                // 2. Create DocumentRouting records with sort_order and SLA tracking
                $slaHours = match($document->sla ?? 'Simple Transaction (3 Working Days)') {
                    'Simple Transaction (3 Working Days)'           => 72,   // 3 days
                    'Complex Transaction (7 Working Days)'          => 168,  // 7 days
                    'Highly Technical Transaction (20 Working Days)' => 480,  // 20 days
                    'Critical'                                      => 24,
                    'Expedited'                                     => 72,
                    default                                         => 168,
                };

                if (!empty($receiverUserIds)) {
                    foreach ($receiverUserIds as $index => $receiverId) {
                        DocumentRouting::create([
                            'document_id'      => $document->id,
                            'from_office_id'   => $fromOfficeId,
                            'to_office_id'     => $request->office_id,
                            'receiver_user_id' => $receiverId,
                            'sender_user_id'   => (int) session('user_id'),
                            'status'           => $newStatus,
                            'pending_at'       => ($newStatus === 'Pending') ? now() : null,
                            'sort_order'       => $index + 1,
                            'notes'            => $request->notes ?? null,
                            'action_label'     => 'Routed',
                            'sla_hours'        => $slaHours,
                            'sla_due_at'       => now()->addHours($slaHours),
                            'sla_status'       => 'on_time',
                        ]);
                    }
                } else {
                    DocumentRouting::create([
                        'document_id'    => $document->id,
                        'from_office_id' => $fromOfficeId,
                        'to_office_id'   => $request->office_id,
                        'sender_user_id' => (int) session('user_id'),
                        'status'         => $newStatus,
                        'pending_at'     => ($newStatus === 'Pending') ? now() : null,
                        'sort_order'     => 1,
                        'notes'          => $request->notes ?? null,
                        'action_label'   => 'Routed',
                        'sla_hours'      => $slaHours,
                        'sla_due_at'     => now()->addHours($slaHours),
                        'sla_status'     => 'on_time',
                    ]);
                }

                // 3. Activity log (ARTA-enriched)
                $receiverNames = User::whereIn('id', $receiverUserIds)->pluck('name')->toArray();
                ActivityLog::create([
                    'user'        => session('user_name') ?? 'System',
                    'action'      => 'Document Routed',
                    'document_id' => $document->id,
                    'ip'          => $request->ip(),
                    'meta'        => json_encode([
                        'from_office'     => $oldOfficeName,
                        'to_office'       => $targetOffice->name,
                        'status'          => $newStatus,
                        'timestamp'       => now()->toIso8601String(),
                        'routed_by'       => session('user_name') ?? 'System',
                        'receivers_count' => count($receiverUserIds),
                        'receivers'       => implode(', ', $receiverNames),
                        'sla_due_at'      => now()->addHours($slaHours)->toIso8601String(),
                    ]),
                ]);

                // 4. Notify ALL receivers (not just first, not request-conditional)
                $senderName    = session('user_name') ?? 'System';
                $toOfficeName  = $targetOffice->name;

                if (!empty($receiverUserIds)) {
                    foreach ($receiverUserIds as $receiverId) {
                        try {
                            $receiver = User::find($receiverId);
                            if ($receiver) {
                                $receiver->notify(
                                    new \App\Notifications\DocumentForwardedNotification(
                                        $document->fresh(),
                                        $senderName,
                                        $toOfficeName
                                    )
                                );
                            }
                        } catch (\Exception $e) {
                            \Log::warning('Notification failed for user ' . $receiverId . ': ' . $e->getMessage());
                        }
                    }
                } elseif ($document->receiver_user_id) {
                    try {
                        $document->fresh()->notifyReceiver();
                    } catch (\Exception $e) {
                        \Log::warning('Notification failed: ' . $e->getMessage());
                    }
                }
            });

            return redirect()->route('track.index')
                ->with('success', 'Document routed successfully! The receiver has been notified.');

        } catch (\Exception $e) {
            \Log::error('Routing Error: ' . $e->getMessage());
            return back()->with('error', 'Routing failed: ' . $e->getMessage());
        }
    }
}