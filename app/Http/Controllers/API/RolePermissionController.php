<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;


class RolePermissionController extends Controller
{
    // Get all roles
    public function getRoles()
    {
        $roles = Role::with('permissions')->get();
        
        return response()->json([
            'status' => true,
            'roles' => $roles
        ], 200);
    }

    // Get all permissions
    public function getPermissions()
    {
        $permissions = Permission::all();
        
        return response()->json([
            'status' => true,
            'permissions' => $permissions
        ], 200);
    }

    // Create role
    public function createRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $role = Role::create(['name' => $request->name]);
        
        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json([
            'status' => true,
            'message' => 'Role created successfully',
            'role' => $role->load('permissions')
        ], 201);
    }

    // Update role
    public function updateRole(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|unique:roles,name,' . $id,
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $role->update(['name' => $request->name]);
        
        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json([
            'status' => true,
            'message' => 'Role updated successfully',
            'role' => $role->load('permissions')
        ], 200);
    }

    // Delete role
    public function deleteRole($id)
    {
        $role = Role::findOrFail($id);
        
        // Prevent deleting super-admin role
        if ($role->name === 'super-admin') {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete super-admin role'
            ], 403);
        }

        $role->delete();

        return response()->json([
            'status' => true,
            'message' => 'Role deleted successfully'
        ], 200);
    }

    // Get all users with roles
    public function getUsers()
    {
        $users = User::with('roles')->get();
        
        return response()->json([
            'status' => true,
            'users' => $users
        ], 200);
    }

    // Create user and assign role
    public function createUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'role' => 'required|exists:roles,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole($request->role);

        return response()->json([
            'status' => true,
            'message' => 'User created successfully',
            'user' => $user->load('roles')
        ], 201);
    }

    // Delete user
    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        
        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return response()->json([
                'status' => false,
                'message' => 'You cannot delete yourself'
            ], 403);
        }

        // Prevent deleting super-admin if you're not super-admin
        if ($user->hasRole('super-admin') && !auth()->user()->hasRole('super-admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete super-admin user'
            ], 403);
        }

        $user->delete();

        return response()->json([
            'status' => true,
            'message' => 'User deleted successfully'
        ], 200);
    }

    // Assign role to user
    public function assignRole(Request $request, $userId)
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|exists:roles,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($userId);
        $user->syncRoles([$request->role]);

        return response()->json([
            'status' => true,
            'message' => 'Role assigned successfully',
            'user' => $user->load('roles')
        ], 200);
    }

    // Remove role from user
    public function removeRole(Request $request, $userId)
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|exists:roles,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($userId);
        $user->removeRole($request->role);

        return response()->json([
            'status' => true,
            'message' => 'Role removed successfully',
            'user' => $user->load('roles')
        ], 200);
    }
}