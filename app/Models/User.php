<?php

namespace App\Models;

use App\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasPermissions, HasRoles, Notifiable {
        HasPermissions::hasAnyRole insteadof HasRoles;
        HasPermissions::hasAllRoles insteadof HasRoles;
    }

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function forms()
    {
        return $this->hasMany(Form::class);
    }

    // Helper method to check if user can perform an action on a form
    public function canManageForm(Form $form)
    {
        // Super admin can manage any form
        if ($this->isSuperAdmin()) {
            return true;
        }

        // User can manage their own forms if they have permission
        if ($this->id === $form->user_id && $this->canEditForms()) {
            return true;
        }

        return false;
    }

    // Helper method to check if user can view a form's submissions
    public function canViewFormSubmissions(Form $form)
    {
        // Super admin can view any submissions
        if ($this->isSuperAdmin()) {
            return true;
        }

        // User can view their own form submissions if they have permission
        if ($this->id === $form->user_id && $this->canViewSubmissions()) {
            return true;
        }

        return false;
    }

    // Get count of forms owned by user
    public function getFormsCount()
    {
        return $this->forms()->count();
    }

    // Get count of submissions across all forms
    public function getTotalSubmissionsCount()
    {
        return $this->forms()->sum('submission_count');
    }

    // Get published forms count
    public function getPublishedFormsCount()
    {
        return $this->forms()->where('is_published', true)->count();
    }
}
