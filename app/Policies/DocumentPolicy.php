<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Document;
use App\Models\DocumentRouting;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DocumentPolicy
{
    use HandlesAuthorization;

    /**
     * Check if a given user has administrative privileges.
     */
    public function isUserAdmin(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view the document and its workflow.
     *
     * Users allowed to VIEW:
     * - Document creator
     * - Current receiver
     * - Current office members
     * - Admin
     * - Participants in routing history
     */
    public function viewWorkflow(?User $user, Document $document): bool
    {
        if (!$user) {
            Log::warning("Workflow view authorization failed: Unauthenticated access attempt on document ID {$document->id}");
            return false;
        }

        // 1. Administrators have global view access
        if ($this->isUserAdmin($user)) {
            return true;
        }

        // 2. Document creator (uploader)
        if ($document->uploaded_by && $document->uploaded_by == $user->id) {
            return true;
        }

        // 3. Current receiver on the document itself
        if ($document->receiver_user_id && $document->receiver_user_id == $user->id) {
            return true;
        }

        // 4. Assigned receiver on any pending or historical routing step
        $isReceiverInRouting = DocumentRouting::where('document_id', $document->id)
            ->where('receiver_user_id', $user->id)
            ->exists();
        if ($isReceiverInRouting) {
            return true;
        }

        // 5. Participants in routing history (sender or forwarder)
        $isSenderInRouting = DocumentRouting::where('document_id', $document->id)
            ->where(function ($q) use ($user) {
                $q->where('sender_user_id', $user->id)
                  ->orWhere('forwarded_from_user_id', $user->id);
            })
            ->exists();
        if ($isSenderInRouting) {
            return true;
        }

        // 6. Authorized recipient of Confidential PIN or notification
        $isPinRecipient = \App\Models\DocumentPin::where('document_id', $document->id)
            ->where(function($q) use ($user) {
                $q->where('recipient_id', $user->id)
                  ->orWhere('user_id', $user->id)
                  ->orWhere('email', $user->email);
            })
            ->exists();
        if ($isPinRecipient) {
            return true;
        }

        // 6. Current office members
        if ($user->office_id) {
            $associatedOffices = array_filter([
                $document->origin_office_id,
                $document->current_office_id,
                $document->destination_office_id,
            ]);

            if (in_array($user->office_id, $associatedOffices)) {
                return true;
            }

            // User's office appears anywhere in document routing hops
            $officeInRouting = DocumentRouting::where('document_id', $document->id)
                ->where(function ($q) use ($user) {
                    $q->where('to_office_id', $user->office_id)
                      ->orWhere('from_office_id', $user->office_id);
                })
                ->exists();

            if ($officeInRouting) {
                return true;
            }
        }

        // 7. QR Code verified in current session allows viewing document details
        if (session('qr_verified_' . $document->id)) {
            return true;
        }

        Log::warning("Workflow view authorization denied: User ID {$user->id} (office: {$user->office_id}, role: {$user->role}) not permitted to view document ID {$document->id}");
        return false;
    }

    /**
     * Alias for view permission on the document model.
     */
    public function view(?User $user, Document $document): bool
    {
        return $this->viewWorkflow($user, $document);
    }

    /**
     * Determine whether the user can PERFORM workflow actions on the document.
     *
     * Users allowed to PERFORM actions:
     * - Current receiver
     * - Authorized approver
     * - Admin
     */
    public function performWorkflow(?User $user, Document $document, ?DocumentRouting $activeRouting = null): bool
    {
        if (!$user) {
            Log::warning("Workflow action authorization denied: Unauthenticated user for document ID {$document->id}");
            return false;
        }

        // 1. Admin can always perform workflow actions
        if ($this->isUserAdmin($user)) {
            return true;
        }

        // Non-admins cannot perform actions on terminal statuses (case-insensitive)
        $docStatus = strtolower(trim((string) $document->status));
        if (in_array($docStatus, ['completed', 'archived', 'cancelled'])) {
            Log::info("Workflow action blocked: Document ID {$document->id} is in terminal status '{$document->status}'");
            return false;
        }

        // 2. Document creator / owner is authorized to perform workflow actions (e.g. forward, recall, cancel)
        if ($document->uploaded_by && $document->uploaded_by == $user->id) {
            return true;
        }

        // 3. Direct document receiver is always authorized
        if ($document->receiver_user_id && $document->receiver_user_id == $user->id) {
            return true;
        }

        $activeStatuses = ['pending', 'in transit', 'in_transit', 'received', 'under review', 'under_review', 'processing', 'on process', 'on_process', 'for approval', 'for_approval', 'waiting'];

        // 4. Assigned receiver on any active, pending, or sequential routing step for this document
        $isAssignedInRouting = DocumentRouting::where('document_id', $document->id)
            ->where('receiver_user_id', $user->id)
            ->whereIn(DB::raw('LOWER(status)'), $activeStatuses)
            ->exists();
        if ($isAssignedInRouting) {
            return true;
        }

        // 5. Authorized recipient who was issued or verified a Confidential PIN / Notification
        $isPinRecipient = \App\Models\DocumentPin::where('document_id', $document->id)
            ->where(function($q) use ($user) {
                $q->where('recipient_id', $user->id)
                  ->orWhere('user_id', $user->id)
                  ->orWhere('email', $user->email);
            })
            ->exists();
        if ($isPinRecipient) {
            return true;
        }

        // 6. Resolve active routing if not provided
        if (!$activeRouting) {
            $activeRouting = $this->resolveActiveRouting($user, $document);
        }

        // 7. Check active routing step if present
        if ($activeRouting) {
            $routingStatus = strtolower(trim((string) $activeRouting->status));
            if (in_array($routingStatus, $activeStatuses)) {
                // Case A: Step is explicitly assigned to this user
                if ($activeRouting->receiver_user_id && $activeRouting->receiver_user_id == $user->id) {
                    return true;
                }

                // Case B: Destination office matches user's office
                if ($user->office_id && $activeRouting->to_office_id == $user->office_id) {
                    return true;
                }

                // Case C: Office Head or department match for destination office
                if ($user->department_id && $activeRouting->toOffice && $activeRouting->toOffice->department_id == $user->department_id) {
                    return true;
                }

                // Case D: Parallel approval steps at the same sort order
                $isParallelApprover = DocumentRouting::where('document_id', $document->id)
                    ->where('sort_order', $activeRouting->sort_order)
                    ->whereIn(DB::raw('LOWER(status)'), $activeStatuses)
                    ->where(function ($q) use ($user) {
                        $q->where('receiver_user_id', $user->id)
                          ->orWhere(function ($sub) use ($user) {
                              $sub->whereNull('receiver_user_id')
                                  ->where('to_office_id', $user->office_id);
                          });
                    })
                    ->exists();

                if ($isParallelApprover) {
                    return true;
                }
            }
        }

        // 8. Office-level matching: document's current or destination office matches user's office
        if ($user->office_id) {
            if ($document->current_office_id == $user->office_id || $document->destination_office_id == $user->office_id) {
                return true;
            }
        }

        // 9. Department-level / Office Head matching
        if ($user->department_id) {
            $currentOffice = $document->currentOffice;
            $destOffice = $document->destinationOffice;
            if (($currentOffice && $currentOffice->department_id == $user->department_id) || ($destOffice && $destOffice->department_id == $user->department_id)) {
                $roleNorm = strtoupper(trim((string)$user->role));
                if (str_contains($roleNorm, 'HEAD') || $roleNorm === 'OFFICE HEAD') {
                    return true;
                }
            }
        }

        // 10. Legitimate QR code verification in current session for associated office staff
        if (session('qr_verified_' . $document->id)) {
            $associatedOffices = array_filter([
                $document->origin_office_id,
                $document->current_office_id,
                $document->destination_office_id,
            ]);
            $routingOffices = DocumentRouting::where('document_id', $document->id)->pluck('to_office_id')->toArray();
            $allAssociated = array_unique(array_merge($associatedOffices, $routingOffices));
            if ($user->office_id && in_array($user->office_id, $allAssociated)) {
                return true;
            }
        }

        Log::info("Workflow action authorization denied: User ID {$user->id} (office: {$user->office_id}) is not active receiver or approver for document ID {$document->id} (status: {$document->status})");
        return false;
    }

    /**
     * Resolve the most relevant routing record for the user and document.
     */
    public function resolveActiveRouting(?User $user, Document $document): ?DocumentRouting
    {
        $activeStatuses = ['pending', 'in transit', 'in_transit', 'received', 'under review', 'under_review', 'processing', 'on process', 'on_process', 'for approval', 'for_approval'];

        if ($user) {
            // 1. Active routing step explicitly assigned to this user
            $userPending = DocumentRouting::where('document_id', $document->id)
                ->where('receiver_user_id', $user->id)
                ->whereIn(DB::raw('LOWER(status)'), $activeStatuses)
                ->orderBy('sort_order', 'asc')
                ->first();

            if ($userPending) {
                return $userPending;
            }

            // 2. Active routing step for user's office (office-based routing)
            if ($user->office_id) {
                $officePending = DocumentRouting::where('document_id', $document->id)
                    ->where('to_office_id', $user->office_id)
                    ->whereIn(DB::raw('LOWER(status)'), $activeStatuses)
                    ->where(function ($q) use ($user) {
                        $q->whereNull('receiver_user_id')
                          ->orWhere('receiver_user_id', $user->id);
                    })
                    ->orderBy('sort_order', 'asc')
                    ->first();

                if ($officePending) {
                    return $officePending;
                }
            }
        }

        // 3. Any earliest active routing step on the document
        $anyPending = DocumentRouting::where('document_id', $document->id)
            ->whereIn(DB::raw('LOWER(status)'), $activeStatuses)
            ->orderBy('sort_order', 'asc')
            ->first();

        if ($anyPending) {
            return $anyPending;
        }

        // 4. Latest completed/reverted routing step if no active step exists
        return DocumentRouting::where('document_id', $document->id)
            ->orderBy('sort_order', 'desc')
            ->orderBy('id', 'desc')
            ->first();
    }
}
