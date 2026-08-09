<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormField extends Model
{
    use HasFactory;

    protected $table = 'form_fields';

    protected $fillable = [
        'form_id',
        'field_key',
        'label',
        'type',
        'placeholder',
        'help_text',
        'validation',
        'settings',
        'step',
        'order',
        'is_required',
        'is_visible',
        'default_value',
        'css_class',
        'conditional_logic',
    ];

    protected $casts = [
        'validation' => 'array',
        'settings' => 'array',
        'conditional_logic' => 'array',
        'is_required' => 'boolean',
        'is_visible' => 'boolean',
    ];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function options()
    {
        return $this->hasMany(FieldOption::class)->orderBy('order');
    }

    public function getFieldTypeLabelAttribute()
    {
        $types = [
            'text' => 'Text Input',
            'email' => 'Email Input',
            'number' => 'Number Input',
            'phone' => 'Phone Input',
            'textarea' => 'Text Area',
            'select' => 'Dropdown',
            'radio' => 'Radio Buttons',
            'checkbox' => 'Checkboxes',
            'file' => 'File Upload',
            'date' => 'Date Picker',
            'time' => 'Time Picker',
            'datetime' => 'DateTime Picker',
            'color' => 'Color Picker',
            'range' => 'Range Slider',
            'url' => 'URL Input',
            'password' => 'Password Input',
            'hidden' => 'Hidden Field',
            'heading' => 'Heading',
            'paragraph' => 'Paragraph Text',
            'divider' => 'Divider',
            'section' => 'Section Heading',
            'rating' => 'Rating',
        ];

        return $types[$this->type] ?? $this->type;
    }
}
