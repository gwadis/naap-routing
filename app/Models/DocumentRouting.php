<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class DocumentRouting extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'from_office_id',
        'to_office_id',
        'receiver_user_id',
        'sender_user_id',
        'forwarded_from_user_id',
        'status',
        'approval_type',
        'signature_required',
        'sort_order',
        'notes',
        'forwarded_reason',
        'signed_by',
        'signature',
        'received_at',
        'released_at',
        'scanned_at',
        'last_vpaa_alarm_at',
        'action_label',
        'sla_hours',
        'sla_due_at',
        'sla_status',
        'pending_at',
    ];

    protected $casts = [
        'received_at'        => 'datetime',
        'released_at'        => 'datetime',
        'scanned_at'         => 'datetime',
        'last_vpaa_alarm_at' => 'datetime',
        'sla_due_at'         => 'datetime',
        'pending_at'         => 'datetime',
        'signature_required' => 'boolean',
    ];

    // -------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------

    public function document()          { return $this->belongsTo(Document::class); }
    public function fromOffice()        { return $this->belongsTo(Office::class, 'from_office_id'); }
    public function toOffice()          { return $this->belongsTo(Office::class, 'to_office_id'); }
    public function receiverUser()      { return $this->belongsTo(User::class, 'receiver_user_id'); }
    public function forwardedFromUser() { return $this->belongsTo(User::class, 'forwarded_from_user_id'); }
    public function senderUser()        { return $this->belongsTo(User::class, 'sender_user_id'); }

    // -------------------------------------------------------------------
    // SLA Accessors
    // -------------------------------------------------------------------

    /**
     * Get the computed SLA status based on sla_due_at and step status.
     * Returns: 'on_time', 'near_due', 'overdue', or 'completed'
     */
    public function getComputedSlaStatusAttribute(): string
    {
        // If step is done, return based on whether it finished on time
        if (in_array($this->status, ['Approved', 'Completed', 'Rejected', 'Returned'])) {
            if ($this->sla_due_at && $this->received_at) {
                return $this->received_at->lte($this->sla_due_at) ? 'completed_on_time' : 'completed_overdue';
            }
            return 'completed_on_time';
        }

        if (!$this->sla_due_at) return 'on_time';

        $now = now();
        if ($now->greaterThan($this->sla_due_at)) {
            return 'overdue';
        }
        if ($now->greaterThan($this->sla_due_at->subHours(2))) {
            return 'near_due';
        }
        return 'on_time';
    }

    /**
     * Human-readable SLA status label.
     */
    public function getSlaStatusLabelAttribute(): string
    {
        return match($this->computed_sla_status) {
            'overdue'            => 'Overdue',
            'near_due'           => 'Near Due',
            'completed_overdue'  => 'Late',
            'completed_on_time'  => 'On Time',
            default              => 'On Time',
        };
    }

    /**
     * Bootstrap CSS class for SLA status badge.
     */
    public function getSlaStatusClassAttribute(): string
    {
        return match($this->computed_sla_status) {
            'overdue'            => 'bg-danger text-white',
            'near_due'           => 'bg-warning text-dark',
            'completed_overdue'  => 'bg-secondary text-white',
            'completed_on_time'  => 'bg-success text-white',
            default              => 'bg-success text-white',
        };
    }

    /**
     * Processing duration in human-readable form.
     * From when the step was created (assigned) to when it was received/acted upon.
     */
    public function getProcessingDurationAttribute(): ?string
    {
        $start = $this->pending_at ?? $this->created_at;
        $end   = $this->released_at ?? $this->received_at;

        if (!$start || !$end) return null;

        $diff = $start->diff($end);

        if ($diff->days > 0) {
            return $diff->days . 'd ' . $diff->h . 'h ' . $diff->i . 'm';
        }
        if ($diff->h > 0) {
            return $diff->h . 'h ' . $diff->i . 'm';
        }
        return $diff->i . 'm';
    }

    /**
     * Remaining SLA time as a human-readable string.
     */
    public function getSlaRemainingAttribute(): ?string
    {
        if (!$this->sla_due_at) return null;
        if (in_array($this->status, ['Approved', 'Completed', 'Rejected', 'Returned'])) {
            return null;
        }

        $now = now();
        if ($now->greaterThan($this->sla_due_at)) {
            $diff = $this->sla_due_at->diff($now);
            return '-' . ($diff->days > 0 ? $diff->days . 'd ' : '') . $diff->h . 'h ' . $diff->i . 'm';
        }

        $diff = $now->diff($this->sla_due_at);
        return ($diff->days > 0 ? $diff->days . 'd ' : '') . $diff->h . 'h ' . $diff->i . 'm';
    }
}

