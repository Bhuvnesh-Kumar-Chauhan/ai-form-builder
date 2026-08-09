<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Form extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'forms';

    protected $fillable = [
        'uuid',
        'user_id',
        'title',
        'slug',
        'description',
        'settings',
        'validation_rules',
        'is_published',
        'is_multi_step',
        'submission_count',
        'published_at',
        'expires_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'validation_rules' => 'array',
        'is_published' => 'boolean',
        'is_multi_step' => 'boolean',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($form) {
            $form->uuid = (string) Str::uuid();
            if (empty($form->slug)) {
                $form->slug = Str::slug($form->title).'-'.Str::random(6);
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fields()
    {
        return $this->hasMany(FormField::class)->orderBy('order');
    }

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function analytics()
    {
        return $this->hasMany(FormAnalytic::class);
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function getFillUrlAttribute()
    {
        return route('forms.show', $this->slug);
    }

    public function getValidationRulesArray()
    {
        $rules = [];
        foreach ($this->fields as $field) {
            $fieldRules = [];

            if ($field->is_required) {
                $fieldRules[] = 'required';
            }

            if ($field->validation) {
                foreach ($field->validation as $rule => $value) {
                    if ($value !== null && $value !== '') {
                        if (in_array($rule, ['min', 'max', 'minlength', 'maxlength'])) {
                            $fieldRules[] = $rule.':'.$value;
                        } elseif ($rule === 'email') {
                            $fieldRules[] = 'email';
                        } elseif ($rule === 'numeric') {
                            $fieldRules[] = 'numeric';
                        } elseif ($rule === 'url') {
                            $fieldRules[] = 'url';
                        } elseif ($rule === 'regex') {
                            $fieldRules[] = 'regex:'.$value;
                        } elseif ($rule === 'mimes') {
                            $fieldRules[] = 'mimes:'.$value;
                        } elseif ($rule === 'unique') {
                            $fieldRules[] = 'unique:'.$value;
                        } elseif ($rule === 'in') {
                            $fieldRules[] = 'in:'.$value;
                        } elseif ($rule === 'date') {
                            $fieldRules[] = 'date';
                        } elseif ($rule === 'array') {
                            $fieldRules[] = 'array';
                        } elseif ($rule === 'file') {
                            $fieldRules[] = 'file';
                        }
                    }
                }
            }

            if (! empty($fieldRules)) {
                $rules[$field->field_key] = $fieldRules;
            }
        }

        return $rules;
    }

    public function getSchemaAttribute()
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'settings' => $this->settings,
            'fields' => $this->fields->map(function ($field) {
                return [
                    'field_key' => $field->field_key,
                    'label' => $field->label,
                    'type' => $field->type,
                    'placeholder' => $field->placeholder,
                    'help_text' => $field->help_text,
                    'validation' => $field->validation,
                    'settings' => $field->settings,
                    'is_required' => $field->is_required,
                    'is_visible' => $field->is_visible,
                    'default_value' => $field->default_value,
                    'options' => $field->options->map(function ($option) {
                        return [
                            'label' => $option->label,
                            'value' => $option->value,
                            'is_default' => $option->is_default,
                        ];
                    }),
                ];
            }),
        ];
    }
}
