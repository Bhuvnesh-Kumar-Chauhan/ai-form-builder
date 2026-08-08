<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\Form;

class FormSubmission extends Model
{
    use HasFactory;
    protected $table = 'form_submissions';

    protected $fillable = [
        'form_id', 'submission_uuid', 'data',
        'ip_address', 'user_agent', 'meta_data',
        'is_spam', 'submitted_at'
    ];

    protected $casts = [
        'data' => 'array',
        'meta_data' => 'array',
        'is_spam' => 'boolean',
        'submitted_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($submission) {
            $submission->submission_uuid = (string) Str::uuid();
            if (empty($submission->submitted_at)) {
                $submission->submitted_at = now();
            }
        });
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    // Helper to get value by field key
    public function getValue($key)
    {
        return $this->data[$key] ?? null;
    }
    public function getFormattedDataAttribute()
    {
        $formatted = [];
        foreach ($this->data as $key => $value) {
            if (is_array($value)) {
                $formatted[$key] = implode(', ', $value);
            } else {
                $formatted[$key] = $value;
            }
        }
        return $formatted;
    }
}