<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Visit extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'property_id',
        'assigned_to',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'requested_services',
        'chlorine_reading',
        'ph_reading',
        'services_performed',
        'photos',
        'technician_notes',
        'report_path',
        'report_sent_at',
        'schedule_notification_sent_at',
        'reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'requested_services' => 'array',
            'report_sent_at' => 'datetime',
            'schedule_notification_sent_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'chlorine_reading' => 'decimal:2',
            'ph_reading' => 'decimal:2',
            'services_performed' => 'array',
            'photos' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class)->withTrashed();
    }
}
