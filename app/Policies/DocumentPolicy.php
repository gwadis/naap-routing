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

        $userId = (int) $user->id;

        // 2. Document creator (uploader)
        if ($document->uploaded_by && (int) $document->uploaded_by === $userId) {
            return true;
        }

        // 3. Current receiver on the document itself
        if ($document->receiver_user_id && (int) $document->receiver_user_id === $userId) {
            return true;
        }

        // 4. Assigned receiver on any pending or historical routing step
        $isReceiverInRouting = DocumentRouting::where('document_id', $document->id)
            ->where('receiver_user_id', $userId)
            ->exists();
        if ($isReceiverInRouting) {
            return true;
        }

        // 5. Participants in routing history (sender or forwarder)
        $isSenderInRouting = DocumentRouting::where('document_id', $document->id)
            ->where(function ($q) use ($userId) {
                $q->where('sender_user_id', $userId)
                  ->orWhere('forwarded_from_user_id', $userId);
            })
            ->exists();
        if ($isSenderInRouting) {
            return true;
        }

        // 5b. Legitimate unassigned office-level pending routing dispatched to the user's office
        if ($user->office_id) {
            $isOfficePending = DocumentRouting::where('document_id', $document->id)
                ->where('to_office_id', $user->office_id)
                ->whereNull('receiver_user_id')
                ->whereIn(DB::raw('LOWER(status)'), ['pending', 'in transit', 'in_transit', 'received', 'under review', 'under_review', 'waiting', 'processing', 'on process', 'on_process'])
                ->exists();
            if ($isOfficePending) {
                return true;
            }
        }

        // 6. Authorized recipient of Confidential PIN or notification
        $isPinRecipient = \App\Models\DocumentPin::where('document_id', $document->id)
            ->where(function($q) use ($user, $userId) {
                $q->where('recipient_id', $userId)
                  ->orWhere('user_id', $userId);
                if (!empty($user->email)) {
                    $q->orWhere('email', $user->email);
                }
            })
            ->exists();
        if ($isPinRecipient) {
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

        // 1. Admin can perform workflow actions according to system-level access
        if ($this->isUserAdmin($user)) {
            return true;
        }

        $userId = (int) $user->id;

        // Non-admins cannot perform actions on terminal statuses (case-insensitive)
        $docStatus = strtolower(trim((string) $document->status));
        if (in_array($docStatus, ['completed', 'archived', 'cancelled'])) {
            Log::info("Workflow action blocked: Document ID {$document->id} is in terminal status '{$document->status}'");
            return false;
        }

        // 2. Document creator / owner is authorized to perform workflow actions unless currently assigned to someone else
        if ($document->uploaded_by && (int) $document->uploaded_by === $userId) {
            $isAssignedToOther = false;
            if ($document->receiver_user_id && (int) $document->receiver_user_id !== $userId) {
                $isAssignedToOther = true;
            }
            if ($activeRouting && $activeRouting->receiver_user_id && (int) $activeRouting->receiver_user_id !== $userId) {
                $isAssignedToOther = true;
            }
            if (!$isAssignedToOther) {
                return true;
            }
        }

        // 3. Direct document receiver is always authorized
        if ($document->receiver_user_id && (int) $document->receiver_user_id === $userId) {
            return true;
        }

        $activeStatuses = ['pending', 'in transit', 'in_transit', 'received', 'under review', 'under_review', 'processing', 'on process', 'on_process', 'for approval', 'for_approval', 'waiting'];

        // 4. Assigned receiver on any active, pending, or sequential routing step for this document
        $isAssignedInRouting = DocumentRouting::where('document_id', $document->id)
            ->where('receiver_user_id', $userId)
            ->whereIn(DB::raw('LOWER(status)'), $activeStatuses)
            ->exists();
        if ($isAssignedInRouting) {
            return true;
        }

        // 5. Authorized recipient who was issued or verified a Confidential PIN / Notification
        $isPinRecipient = \App\Models\DocumentPin::where('document_id', $document->id)
            ->where(function($q) use ($user, $userId) {
                $q->where('recipient_id', $userId)
                  ->orWhere('user_id', $userId);
                if (!empty($user->email)) {
                    $q->orWhere('email', $user->email);
                }
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
                if ($activeRouting->receiver_user_id && (int) $activeRouting->receiver_user_id === $userId) {
                    return true;
                }

                // Case B: Destination office matches user's office
                if ($user->office_id && (int) $activeRouting->to_office_id === (int) $user->office_id) {
                    if ($user->isOfficeHead()) {
                        return true;
                    }
                    // For Staff: authorized if receiver is unassigned or assigned to staff
                    if (is_null($activeRouting->receiver_user_id) || (int) $activeRouting->receiver_user_id === $userId) {
                        return true;
                    }
                }



                // Case D: Parallel approval steps at the same sort order
                $isParallelApprover = DocumentRouting::where('document_id', $document->id)
                    ->where('sort_order', $activeRouting->sort_order)
                    ->whereIn(DB::raw('LOWER(status)'), $activeStatuses)
                    ->where(function ($q) use ($user, $userId) {
                        $q->where('receiver_user_id', $userId)
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

        // 8. Office-level matching for Office Head
        if ($user->office_id && $user->isOfficeHead()) {
            if ((int) $document->current_office_id === (int) $user->office_id || (int) $document->destination_office_id === (int) $user->office_id) {
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
