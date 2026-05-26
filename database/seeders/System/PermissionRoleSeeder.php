<?php

namespace Database\Seeders\System;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\{Role, Permission};
use Spatie\Permission\PermissionRegistrar;

class PermissionRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionMap = config('app_core_permissions');
        $rolesConfig = config('app_core_roles');

        $allPermissions = [];

        // ---------------- CREATE PERMISSIONS ----------------
        foreach ($permissionMap as $group => $perms) {
            foreach ($perms as $perm) {
                $permissionName = "{$group}_{$perm['permission_name']}";
                $allPermissions[] = $permissionName;

                Permission::firstOrCreate(
                    ['name' => $permissionName, 'guard_name' => 'web'],
                    ['group' => $group, 'note' => $perm['note'] ?? null]
                );
            }
        }

        // ---------------- HELPER: MAP PERMISSIONS FOR ROLES ----------------
        $mapPermissions = function (string $group, array $groupPerms, array $selectedNames): array {
            $mapped = [];
            foreach ($groupPerms as $perm) {
                if (in_array($perm['permission_name'], $selectedNames)) {
                    $mapped[] = $group . '_' . $perm['permission_name'];
                }
            }
            return $mapped;
        };

        // ---------------- ROLE PERMISSION MAP ----------------
        $rolePermissions = [
            $rolesConfig['SUPER_ADMIN'] => $allPermissions,

            $rolesConfig['ADMIN'] => array_merge(
                $mapPermissions('role', $permissionMap['role'], ['index', 'show', 'create', 'update', 'delete']),
                $mapPermissions('admin', $permissionMap['admin'], ['email']),
                $mapPermissions('general', $permissionMap['general'], ['index', 'show', 'create', 'update', 'delete']),
                $mapPermissions('project', $permissionMap['project'], ['index', 'show', 'create', 'update', 'delete']),
                $mapPermissions('bar', $permissionMap['bar'], ['index', 'show', 'create', 'update', 'delete']),
                $mapPermissions('deals', $permissionMap['deals'], ['index', 'show', 'create', 'update', 'delete']),
                $mapPermissions('event', $permissionMap['event'], ['index', 'show', 'create', 'update', 'delete']),
                $mapPermissions('user', $permissionMap['user'], ['index', 'show', 'create', 'update', 'delete', 'admin_view']),
                $mapPermissions('profile', $permissionMap['profile'], ['index', 'show', 'create', 'update', 'delete']),
                $mapPermissions('subscription', $permissionMap['subscription'], ['index', 'show', 'create', 'delete', 'cancel']),
                $mapPermissions('payment', $permissionMap['payment'], ['index', 'show', 'create', 'delete', 'cancel']),
                $mapPermissions('points', $permissionMap['points'], ['index', 'show', 'add', 'deduct', 'cancel', 'delete']),
                $mapPermissions('course', $permissionMap['course'], ['index', 'show', 'create', 'update', 'delete'])
            ),

            $rolesConfig['BAR_ADMIN'] => array_merge(
                $mapPermissions('general', $permissionMap['general'], ['index', 'show', 'create', 'update', 'delete']),
                $mapPermissions('bar', $permissionMap['bar'], ['index', 'show', 'create', 'update']),
                $mapPermissions('event', $permissionMap['event'], ['index', 'show', 'create', 'update']),
                $mapPermissions('deals', $permissionMap['deals'], ['index', 'show', 'create', 'update']),
                $mapPermissions('profile', $permissionMap['profile'], ['index', 'show', 'create', 'update', 'delete']),
            ),

            $rolesConfig['STUDENT'] => array_merge(
                $mapPermissions('general', $permissionMap['general'], ['index', 'show', 'create', 'update', 'delete']),
                $mapPermissions('project', $permissionMap['project'], ['index', 'show']),
                $mapPermissions('bar', $permissionMap['bar'], ['index', 'show']),
                $mapPermissions('event', $permissionMap['event'], ['index', 'show']),
                $mapPermissions('deals', $permissionMap['deals'], ['index', 'show']),
                $mapPermissions('course', $permissionMap['course'], ['index', 'show']),
                $mapPermissions('points', $permissionMap['points'], ['show']),
                $mapPermissions('subscription', $permissionMap['subscription'], ['show']),
                $mapPermissions('profile', $permissionMap['profile'], ['index', 'show', 'create', 'update', 'delete']),
            ),

            // $rolesConfig['GUEST'] => $mapPermissions('general', $permissionMap['general'], ['index','show']),

            $rolesConfig['GUEST'] => array_merge(
                $mapPermissions('general', $permissionMap['general'], ['index', 'show', 'create', 'update', 'delete']),
                $mapPermissions('profile', $permissionMap['profile'], ['index', 'show', 'create', 'update', 'delete']),
            ),
        ];

        // ---------------- CREATE ROLES + SYNC PERMISSIONS ----------------
        $coreRoleKeys = ['SUPER_ADMIN', 'ADMIN'];

        foreach ($rolePermissions as $roleName => $permissions) {
            $roleKey = array_search($roleName, $rolesConfig, true);
            $roleType = in_array($roleKey, $coreRoleKeys, true) ? 'core' : 'app';

            $role = Role::firstOrNew([
                'role_key' => strtolower($roleKey),
                'guard_name' => 'web',
            ]);

            if (!$role->exists) {
                $role->name = ucwords(str_replace('_', ' ', $roleName));
            }

            $role->role_type = $roleType;
            $role->guard_name = 'web';
            $role->save();

            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}