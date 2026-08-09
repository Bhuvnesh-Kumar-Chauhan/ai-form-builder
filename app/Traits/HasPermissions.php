<?php

namespace App\Traits;

trait HasPermissions
{
    // Rename to avoid collision with Spatie's methods
    public function userHasPermission($permission)
    {
        return $this->hasPermissionTo($permission);
    }

    public function userHasAnyPermission($permissions)
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    public function userHasAllPermissions($permissions)
    {
        foreach ($permissions as $permission) {
            if (! $this->hasPermissionTo($permission)) {
                return false;
            }
        }

        return true;
    }

    // Check if user can manage forms
    public function canManageForms()
    {
        return $this->userHasAnyPermission(['manage forms', 'create forms', 'edit forms']);
    }

    // Check if user can view forms
    public function canViewForms()
    {
        return $this->hasPermissionTo('view forms');
    }

    // Check if user can create forms
    public function canCreateForms()
    {
        return $this->hasPermissionTo('create forms');
    }

    // Check if user can edit forms
    public function canEditForms()
    {
        return $this->hasPermissionTo('edit forms');
    }

    // Check if user can delete forms
    public function canDeleteForms()
    {
        return $this->hasPermissionTo('delete forms');
    }

    // Check if user can publish forms
    public function canPublishForms()
    {
        return $this->hasPermissionTo('publish forms');
    }

    // Check if user can view submissions
    public function canViewSubmissions()
    {
        return $this->hasPermissionTo('view submissions');
    }

    // Check if user can export submissions
    public function canExportSubmissions()
    {
        return $this->hasPermissionTo('export submissions');
    }

    // Check if user can delete submissions
    public function canDeleteSubmissions()
    {
        return $this->hasPermissionTo('delete submissions');
    }

    // Check if user is super admin
    public function isSuperAdmin()
    {
        return $this->hasRole('super-admin');
    }

    // Check if user is admin or super admin
    public function isAdmin()
    {
        return $this->hasRole(['super-admin', 'admin']);
    }

    // Get user's role names
    public function getRoleNamesList()
    {
        return $this->getRoleNames()->toArray();
    }

    // Get user's permission names
    public function getPermissionNamesList()
    {
        return $this->getAllPermissions()->pluck('name')->toArray();
    }

    // Check if user has any of the given roles
    public function hasAnyRole($roles)
    {
        if (is_string($roles)) {
            $roles = explode('|', $roles);
        }

        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    // Check if user has all of the given roles
    public function hasAllRoles($roles)
    {
        if (is_string($roles)) {
            $roles = explode('|', $roles);
        }

        foreach ($roles as $role) {
            if (! $this->hasRole($role)) {
                return false;
            }
        }

        return true;
    }
}
