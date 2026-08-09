<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionManager extends Component
{
    public $roles = [];

    public $permissions = [];

    public $selectedRole = null;

    public $rolePermissions = [];

    public $newRoleName = '';

    public $newPermissionName = '';

    public $editingRole = null;

    public $editingPermission = null;

    public function mount()
    {
        $this->loadData();
    }

    public function loadData()
    {
        $this->roles = Role::with('permissions')->get();
        $this->permissions = Permission::all();

        if ($this->selectedRole) {
            $role = Role::find($this->selectedRole);
            if ($role) {
                $this->rolePermissions = $role->permissions->pluck('id')->toArray();
            }
        }
    }

    public function selectRole($roleId)
    {
        $this->selectedRole = $roleId;
        $this->loadData();
    }

    public function createRole()
    {
        $this->validate([
            'newRoleName' => 'required|unique:roles,name',
        ]);

        Role::create(['name' => $this->newRoleName]);
        $this->newRoleName = '';
        $this->loadData();
        session()->flash('message', 'Role created successfully.');
    }

    public function createPermission()
    {
        $this->validate([
            'newPermissionName' => 'required|unique:permissions,name',
        ]);

        Permission::create(['name' => $this->newPermissionName]);
        $this->newPermissionName = '';
        $this->loadData();
        session()->flash('message', 'Permission created successfully.');
    }

    public function deleteRole($roleId)
    {
        $role = Role::find($roleId);
        if ($role && $role->name !== 'super-admin') {
            $role->delete();
            $this->loadData();
            session()->flash('message', 'Role deleted successfully.');
        }
    }

    public function deletePermission($permissionId)
    {
        $permission = Permission::find($permissionId);
        if ($permission) {
            $permission->delete();
            $this->loadData();
            session()->flash('message', 'Permission deleted successfully.');
        }
    }

    public function saveRolePermissions()
    {
        $role = Role::find($this->selectedRole);
        if ($role) {
            $role->syncPermissions($this->rolePermissions);
            session()->flash('message', 'Permissions updated successfully.');
        }
        $this->loadData();
    }

    public function render()
    {
        return view('livewire.admin.permission-manager')
            ->layout('layouts.app');
    }
}
