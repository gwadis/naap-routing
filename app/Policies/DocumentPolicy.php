<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Document;
use App\Models\DocumentRouting;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Log;

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

        $role = strtoupper($user->role ?? session('user_role') ?? '');
        return in_array($role, ['ADMIN', 'ADMINISTRATOR', 'SUPER ADMINISTRATOR'])
            || in_array(strtolower($user->username ?? ''), ['admin', 'vpaa'])
            || ($user->email ?? '') === 'vpaa@naap.org';
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

        // Non-admins cannot perform actions on terminal statuses
        if (in_array($document->status, ['Completed', 'Archived', 'Cancelled'])) {
            Log::info("Workflow action blocked: Document ID {$document->id} is in terminal status '{$document->status}'");
            return false;
        }

        // 2. Resolve active routing if not provided
        if (!$activeRouting) {
            $activeRouting = $this->resolveActiveRouting($user, $document);
        }

        // Check if there is an active pending routing step
        if ($activeRouting && $activeRouting->status === 'Pending') {
            // Case A: Step is explicitly assigned to this user
            if ($activeRouting->receiver_user_id && $activeRouting->receiver_user_id == $user->id) {
                return true;
            }

            // Case B: Office-based routing without specific receiver assigned (any member of target office can act)
            if (!$activeRouting->receiver_user_id && $user->office_id && $activeRouting->to_office_id == $user->office_id) {
                return true;
            }

            // Case C: Parallel approval steps at the same sort order where user is assigned
            $isParallelApprover = DocumentRouting::where('document_id', $document->id)
                ->where('sort_order', $activeRouting->sort_order)
                ->where('status', 'Pending')
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

        // 3. Fallback when routing records are missing or document is directly assigned
        if (!$activeRouting || $activeRouting->status !== 'Pending') {
            // Direct document receiver
            if ($document->receiver_user_id && $document->receiver_user_id == $user->id) {
                return true;
            }

            // Document current office matches user's office with no individual receiver specified
            if (!$document->receiver_user_id && $user->office_id && $document->current_office_id == $user->office_id) {
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
        if ($user) {
            // 1. Pending routing step explicitly assigned to this user
            $userPending = DocumentRouting::where('document_id', $document->id)
                ->where('receiver_user_id', $user->id)
                ->where('status', 'Pending')
                ->orderBy('sort_order', 'asc')
                ->first();

            if ($userPending) {
                return $userPending;
            }

            // 2. Pending routing step for user's office (office-based routing)
            if ($user->office_id) {
                $officePending = DocumentRouting::where('document_id', $document->id)
                    ->where('to_office_id', $user->office_id)
                    ->where('status', 'Pending')
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

        // 3. Any earliest pending routing step on the document
        $anyPending = DocumentRouting::where('document_id', $document->id)
            ->where('status', 'Pending')
            ->orderBy('sort_order', 'asc')
            ->first();

        if ($anyPending) {
            return $anyPending;
        }

        // 4. Latest completed/reverted routing step if no pending step exists
        return DocumentRouting::where('document_id', $document->id)
            ->orderBy('sort_order', 'desc')
            ->orderBy('id', 'desc')
            ->first();
    }
}
