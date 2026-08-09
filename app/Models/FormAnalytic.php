<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormAnalytic extends Model
{
    use HasFactory;

    protected $table = 'form_analytics';

    protected $fillable = [
        'form_id',
        'session_id',
        'ip_address',
        'event_type',
        'event_data',
        'occurred_at',
    ];

    protected $casts = [
        'event_data' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function scopeViews($query)
    {
        return $query->where('event_type', 'view');
    }

    public function scopeStarts($query)
    {
        return $query->where('event_type', 'start');
    }

    public function scopeCompleted($query)
    {
        return $query->where('event_type', 'complete');
    }

    public function scopeAbandoned($query)
    {
        return $query->where('event_type', 'abandon');
    }
}
