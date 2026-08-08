<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiFormGenerationJob extends Model
{
    use HasFactory;

    protected $table = 'ai_form_generation_jobs';

    protected $fillable = [
        'user_id',
        'form_id',
        'mode',
        'prompt',
        'status',
        'input_schema',
        'output_schema',
        'raw_response',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'latency_ms',
        'attempts',
        'error',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'input_schema' => 'array',
        'output_schema' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function isRunning(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_PROCESSING], true);
    }
}
