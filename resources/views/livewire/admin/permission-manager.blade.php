<div>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-shield-alt"></i> Role & Permission Manager
            </h5>
        </div>
        <div class="card-body">
            @if (session('message'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('message') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="row">
                <!-- Roles Column -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Roles</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="input-group">
                                    <input type="text" wire:model="newRoleName" class="form-control" placeholder="New role name">
                                    <button wire:click="createRole" class="btn btn-primary">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="list-group">
                                @foreach($roles as $role)
                                    <button wire:click="selectRole({{ $role->id }})" 
                                            class="list-group-item list-group-item-action d-flex justify-content-between align-items-center
                                                   {{ $selectedRole == $role->id ? 'active' : '' }}">
                                        {{ $role->name }}
                                        <span class="badge bg-secondary rounded-pill">{{ $role->permissions->count() }}</span>
                                        @if($role->name !== 'super-admin')
                                            <button wire:click="deleteRole({{ $role->id }})" 
                                                    class="btn btn-sm btn-danger" 
                                                    onclick="return confirm('Delete this role?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Permissions Column -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Permissions</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="input-group">
                                    <input type="text" wire:model="newPermissionName" class="form-control" placeholder="New permission name">
                                    <button wire:click="createPermission" class="btn btn-success">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="permissions-list" style="max-height: 400px; overflow-y: auto;">
                                @foreach($permissions as $permission)
                                    <div class="d-flex justify-content-between align-items-center p-2 border-bottom">
                                        <span>{{ $permission->name }}</span>
                                        <button wire:click="deletePermission({{ $permission->id }})" 
                                                class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Delete this permission?')">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Role Permissions Column -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                @if($selectedRole)
                                    Permissions for: {{ Role::find($selectedRole)->name ?? '' }}
                                @else
                                    Select a role
                                @endif
                            </h6>
                        </div>
                        <div class="card-body">
                            @if($selectedRole)
                                <div class="permissions-checkboxes" style="max-height: 400px; overflow-y: auto;">
                                    @foreach($permissions as $permission)
                                        <div class="form-check">
                                            <input type="checkbox" 
                                                   class="form-check-input" 
                                                   id="perm_{{ $permission->id }}"
                                                   wire:model="rolePermissions"
                                                   value="{{ $permission->id }}">
                                            <label class="form-check-label" for="perm_{{ $permission->id }}">
                                                {{ $permission->name }}
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                                
                                <div class="mt-3">
                                    <button wire:click="saveRolePermissions" class="btn btn-primary w-100">
                                        <i class="fas fa-save"></i> Save Permissions
                                    </button>
                                </div>
                            @else
                                <p class="text-muted text-center">Please select a role to manage permissions.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>