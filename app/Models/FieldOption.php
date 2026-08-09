<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FieldOption extends Model
{
    use HasFactory;

    protected $table = 'field_options';

    protected $fillable = [
        'form_field_id',
        'label',
        'value',
        'order',
        'is_default',
        'extra_data',
    ];

    protected $casts = [
        'extra_data' => 'array',
        'is_default' => 'boolean',
    ];

    public function field()
    {
        return $this->belongsTo(FormField::class, 'form_field_id');
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
