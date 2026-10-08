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

    public function pins()
    {
        return $this->hasMany(DocumentPin::class);
    }

    /**
     * Scope query to documents accessible by the specified user based on RBAC.
     * Evaluates: ROLE + OFFICE/SCOPE + DOCUMENT RELATIONSHIP
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \App\Models\User|null $user
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAccessibleBy($query, ?User $user)
    {
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        // 1. ADMIN: System-level access according to existing RBAC design
        if ($user->isAdmin()) {
            return $query;
        }

        $userId = (int) $user->id;
        $officeId = $user->office_id ? (int) $user->office_id : null;

        // 2. OFFICE HEAD:
        // Visible:
        // - Documents submitted by the Office Head
        // - Documents routed to the Office Head's office (origin, current, destination)
        // - Documents assigned directly to the Office Head (receiver on doc or in routing)
        // - Documents requiring the Office Head's approval/action
        // - Documents that previously passed through the Office Head's office (in routing hops)
        // - Documents explicitly shared with the Office Head or their office
        if ($user->isOfficeHead()) {
            return $query->where(function ($q) use ($userId, $officeId, $user) {
                // Documents submitted by the Office Head
                $q->where('uploaded_by', $userId)
                  // Documents assigned directly to the Office Head
                  ->orWhere('receiver_user_id', $userId)
                  // Documents in routing steps assigned to or involving the Office Head
                  ->orWhereHas('routings', function ($rq) use ($userId, $officeId) {
                      $rq->where('receiver_user_id', $userId)
                        ->orWhere('sender_user_id', $userId)
                        ->orWhere('forwarded_from_user_id', $userId);
                      if ($officeId) {
                          $rq->orWhere('to_office_id', $officeId)
                             ->orWhere('from_office_id', $officeId);
                      }
                  });

                // Documents routed to / from / located at Office Head's office
                if ($officeId) {
                    $q->orWhere('current_office_id', $officeId)
                      ->orWhere('origin_office_id', $officeId)
                      ->orWhere('destination_office_id', $officeId);
                }

                // Documents explicitly shared with Office Head (via DocumentPin)
                $q->orWhereExists(function ($sub) use ($userId, $user) {
                    $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                        ->from('document_pins')
                        ->whereColumn('document_pins.document_id', 'documents.id')
                        ->where(function ($pinQuery) use ($userId, $user) {
                            $pinQuery->where('document_pins.recipient_id', $userId)
                                     ->orWhere('document_pins.user_id', $userId);
                            if (!empty($user->email)) {
                                $pinQuery->orWhere('document_pins.email', $user->email);
                            }
                        });
                });
            });
        }

        // 3. STAFF / EMPLOYEE / REGULAR USERS (Normal Users):
        // Visible ONLY if:
        // 1. The user created the document (uploaded_by)
        // 2. The document was explicitly sent/assigned to the user (receiver_user_id on doc or in routing, or sender/forwarder in routing)
        // 3. The document was explicitly shared with the user (via DocumentPin)
        // Otherwise: DENY ACCESS.
        $officeId = $user->office_id ? (int) $user->office_id : null;
        return $query->where(function ($q) use ($userId, $user, $officeId) {
            // Documents they created/submitted
            $q->where('uploaded_by', $userId)
              // Documents assigned directly to them as receiver
              ->orWhere('receiver_user_id', $userId)
              // Documents where user is an assigned participant in the workflow (receiver, sender, forwarder)
              // OR legitimate office-level pending routing dispatched to their office with no specific individual assigned
              ->orWhereHas('routings', function ($rq) use ($userId, $officeId) {
                  $rq->where(function ($sub) use ($userId, $officeId) {
                      $sub->where('receiver_user_id', $userId)
                          ->orWhere('sender_user_id', $userId)
                          ->orWhere('forwarded_from_user_id', $userId);
                      if ($officeId) {
                          $sub->orWhere(function ($officeSub) use ($officeId) {
                              $officeSub->where('to_office_id', $officeId)
                                        ->whereNull('receiver_user_id')
                                        ->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(status)'), ['pending', 'in transit', 'in_transit', 'received', 'under review', 'under_review', 'waiting', 'processing', 'on process', 'on_process']);
                          });
                      }
                  });
              })
              // Documents explicitly shared with them (via DocumentPin)
              ->orWhereExists(function ($sub) use ($userId, $user) {
                  $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                      ->from('document_pins')
                      ->whereColumn('document_pins.document_id', 'documents.id')
                      ->where(function ($pinQuery) use ($userId, $user) {
                          $pinQuery->where('document_pins.recipient_id', $userId)
                                   ->orWhere('document_pins.user_id', $userId);
                          if (!empty($user->email)) {
                              $pinQuery->orWhere('document_pins.email', $user->email);
                          }
                      });
              });
        });
    }

    /**
     * Get the standardized external-compatible HTTPS URL to encode into the QR code.
     */
    public function getQrPayloadUrl(): string
    {
        $code = $this->tracking_number ?: ($this->qr_id ?: (string) $this->id);
        $url = route('qr.external', ['code' => $code]);

        // Standardize to HTTPS scheme for external QR scanners
        if (str_starts_with($url, 'http://')) {
            $url = 'https://' . substr($url, 7);
        }

        return $url;
    }
}