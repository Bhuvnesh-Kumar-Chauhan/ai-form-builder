<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;

class PermissionHelper
{
    public static function can($permission)
    {
        if (!Auth::check()) {
            return false;
        }
        
        return Auth::user()->hasPermissionTo($permission);
    }

    public static function canAny($permissions)
    {
        if (!Auth::check()) {
            return false;
        }
        
        return Auth::user()->userHasAnyPermission($permissions);
    }

    public static function canAll($permissions)
    {
        if (!Auth::check()) {
            return false;
        }
        
        return Auth::user()->userHasAllPermissions($permissions);
    }

    public static function hasRole($role)
    {
        if (!Auth::check()) {
            return false;
        }
        
        return Auth::user()->hasRole($role);
    }

    public static function hasAnyRole($roles)
    {
        if (!Auth::check()) {
            return false;
        }
        
        return Auth::user()->hasAnyRole($roles);
    }

    public static function isSuperAdmin()
    {
        if (!Auth::check()) {
            return false;
        }
        
        return Auth::user()->isSuperAdmin();
    }

    public static function isAdmin()
    {
        if (!Auth::check()) {
            return false;
        }
        
        return Auth::user()->isAdmin();
    }

    public static function canManageForm($form)
    {
        if (!Auth::check()) {
            return false;
        }
        
        return Auth::user()->canManageForm($form);
    }

    public static function canViewFormSubmissions($form)
    {
        if (!Auth::check()) {
            return false;
        }
        
        return Auth::user()->canViewFormSubmissions($form);
    }
}