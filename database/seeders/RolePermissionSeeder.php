<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create permissions
        $permissions = [
            ['name' => 'view_chat', 'display_name' => 'View Chat', 'description' => 'Can view chat messages'],
            ['name' => 'send_chat_message', 'display_name' => 'Send Chat Message', 'description' => 'Can send chat messages'],
            ['name' => 'delete_chat_message', 'display_name' => 'Delete Chat Message', 'description' => 'Can delete chat messages'],
            ['name' => 'edit_user', 'display_name' => 'Edit User', 'description' => 'Can edit user information'],
            ['name' => 'delete_user', 'display_name' => 'Delete User', 'description' => 'Can delete users'],
            ['name' => 'edit_chat_configuration', 'display_name' => 'Edit Chat Configuration', 'description' => 'Can edit chat configuration'],
            ['name' => 'edit_system_configuration', 'display_name' => 'Edit System Configuration', 'description' => 'Can edit system configuration'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission['name']], $permission);
        }

        // Create roles
        $superAdmin = Role::firstOrCreate(
            ['name' => 'super_admin'],
            ['display_name' => 'Super Admin', 'description' => 'Has full system access']
        );

        $admin = Role::firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Admin', 'description' => 'Has administrative access']
        );

        $moderator = Role::firstOrCreate(
            ['name' => 'moderator'],
            ['display_name' => 'Moderator', 'description' => 'Can moderate chat and users']
        );

        $user = Role::firstOrCreate(
            ['name' => 'user'],
            ['display_name' => 'User', 'description' => 'Regular user with basic permissions']
        );

        // Assign permissions to roles
        $allPermissions = Permission::all();

        // Super Admin gets all permissions
        $superAdmin->permissions()->sync($allPermissions);

        // Admin gets all permissions except system configuration
        $adminPermissions = Permission::whereNotIn('name', ['edit_system_configuration'])->get();
        $admin->permissions()->sync($adminPermissions);

        // Moderator can view, send, and delete chat messages, and edit users
        $moderatorPermissions = Permission::whereIn('name', [
            'view_chat',
            'send_chat_message',
            'delete_chat_message',
            'edit_user'
        ])->get();
        $moderator->permissions()->sync($moderatorPermissions);

        // User can only view and send chat messages
        $userPermissions = Permission::whereIn('name', [
            'view_chat',
            'send_chat_message'
        ])->get();
        $user->permissions()->sync($userPermissions);
    }
}
