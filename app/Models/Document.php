<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Document extends Model
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     * These must match your MySQL table columns exactly.
     */
    protected $fillable = [
        'title',
        'description',
        'type',
        'priority',
        'sla',
        'origin_office_id',
        'current_office_id',
        'destination_office_id',
        'destination_offices',
        'receiver_user_id',
        'uploaded_by',
        'file_path',
        'file_size',
        'mime_type',
        'file_hash',
        'version',
        'tracking_number',
        'is_confidential',
        'uuid',
        'category',
        'tags',
        'status',
        'qr_code',
        'qr_id',
        'due_date',
        'receiver_signature',
        'qr_scanned_at',
        'received_at',
        'forwarded_at',
        'approved_at',
        'completed_at',
        'archived_at',
        'rejected_at',
        'uploaded_at',
        'processed_at',
        'routing_notes',
        'routing_history',
        'access_pin',
        'qr_status',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'qr_scanned_at' => 'datetime',
        'received_at' => 'datetime',
        'forwarded_at' => 'datetime',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'archived_at' => 'datetime',
        'rejected_at' => 'datetime',
        'uploaded_at' => 'datetime',
        'processed_at' => 'datetime',
        'destination_offices' => 'array',
        'routing_history' => 'array',
        'is_confidential' => 'boolean',
        'qr_status' => 'string',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($document) {
            if ($document->due_date) {
                $dueDate = $document->due_date instanceof \Carbon\Carbon
                    ? $document->due_date
                    : \Carbon\Carbon::parse($document->due_date);
                $days = now()->startOfDay()->diffInDays($dueDate->startOfDay(), false);
                
                if ($days <= 1 || $document->sla === 'Critical') {
                    $document->priority = 'Urgent';
                } elseif ($days <= 3 || $document->sla === 'Expedited' || $document->sla === 'Simple Transaction (3 Working Days)') {
                    $document->priority = 'High';
                } elseif ($days <= 7 || $document->sla === 'Complex Transaction (7 Working Days)') {
                    $document->priority = 'Normal';
                } else {
                    $document->priority = 'Low';
                }
            }
        });
    }

    /**
     * Relationship: The office where the document is currently located.
     */
    public function currentOffice()
    {
        return $this->belongsTo(Office::class, 'current_office_id');
    }

    /**
     * Relationship: The office that originally created/uploaded the document.
     */
    public function originOffice()
    {
        return $this->belongsTo(Office::class, 'origin_office_id');
    }

    /**
     * Relationship: The intended final destination for the document.
     */
    public function destinationOffice()
    {
        return $this->belongsTo(Office::class, 'destination_office_id');
    }

    /**
     * Relationship: The user who uploaded the document.
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function receiverUser()
    {
        return $this->belongsTo(User::class, 'receiver_user_id');
    }

    public function receiverUsers()
    {
        return $this->belongsToMany(User::class, 'document_routings', 'document_id', 'receiver_user_id')->distinct();
    }

    /**
     * Relationship: History of movements/actions for this document.
     */
    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function routings()
    {
        return $this->hasMany(DocumentRouting::class);
    }

    /**
     * Helper: Determine SLA status based on due date.
     */
    public function getSlaStatusAttribute()
    {
        if (!$this->due_date) {
            return 'on-time';
        }

        if ($this->due_date->isPast()) {
            return 'breached';
        }

        return $this->due_date->lessThanOrEqualTo(now()->addDays(2)) ? 'at-risk' : 'on-time';
    }

    public function getSlaStatusLabelAttribute()
    {
        return match ($this->sla_status) {
            'breached' => 'Breached ❌',
            'at-risk'  => 'At Risk ⚠️',
            default    => 'On Time ✅',
        };
    }

    public function getSlaStatusClassAttribute()
    {
        return match ($this->sla_status) {
            'breached' => 'text-white bg-danger',
            'at-risk'  => 'text-dark bg-warning',
            default    => 'text-white bg-success',
        };
    }

    
    public function getPriorityColorAttribute()
    {
        return match (strtolower($this->priority)) {
            'high'   => 'danger',
            'medium' => 'warning',
            'low'    => 'success',
            default  => 'secondary',
        };
    }

    
    public function isActiveReceiver($user)
    {
        if (!$user) return false;
        return (int) $this->receiver_user_id === (int) $user->id;
    }

    public function isDelivered()
    {
        return $this->status === 'Completed' && $this->receiver_signature !== null;
    }

    
    public function markAsReceived($signatureData = null)
    {
        $this->update([
            'status' => 'Completed',
            'received_at' => now(),
            'receiver_signature' => $signatureData,
            'qr_scanned_at' => now(),
        ]);
    }

        public function getDeliveryProofAttribute()
    {
        if (!$this->receiver_signature || !$this->qr_scanned_at) {
            return null;
        }

        return [
            'signed_at' => $this->qr_scanned_at,
            'has_signature' => true,
            'receiver_id' => $this->receiver_user_id,
        ];
    }

    
    public function notifyReceiver()
    {
        if ($this->receiver_user_id) {
            $receiver = User::find($this->receiver_user_id);
            if ($receiver) {
                $receiver->notify(new \App\Notifications\DocumentRoutedNotification($this));
            }
        }
    }

   
    public function notifyUploader($signerName = null)
    {
        if ($this->uploaded_by) {
            $uploader = User::find($this->uploaded_by);
            if ($uploader) {
                $uploader->notify(new \App\Notifications\DocumentSignedNotification($this, $signerName));
            }
        }
    }

    public function views()
    {
        return $this->hasMany(DocumentView::class);
    }
}