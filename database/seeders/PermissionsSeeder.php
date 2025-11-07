<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $permissions = [
            ['id' => 1, 'name' => 'view_clients', 'key' => 'view_clients', 'description' => 'Can view client details'],
            ['id' => 2, 'name' => 'create_client', 'key' => 'create_client', 'description' => 'Can create new clients'],
            ['id' => 3, 'name' => 'edit_client', 'key' => 'edit_client', 'description' => 'Can edit client information'],
            ['id' => 4, 'name' => 'delete_client', 'key' => 'delete_client', 'description' => 'Can delete clients'],
            ['id' => 5, 'name' => 'view_users', 'key' => 'view_users', 'description' => 'Can view user details'],
            ['id' => 6, 'name' => 'create_user', 'key' => 'create_user', 'description' => 'Can create new users'],
            ['id' => 7, 'name' => 'edit_user', 'key' => 'edit_user', 'description' => 'Can edit user information'],
            ['id' => 8, 'name' => 'delete_user', 'key' => 'delete_user', 'description' => 'Can delete users'],
            ['id' => 9, 'name' => 'view_projects', 'key' => 'view_projects', 'description' => 'Can view project details'],
            ['id' => 10, 'name' => 'create_project', 'key' => 'create_project', 'description' => 'Can create new projects'],
            ['id' => 11, 'name' => 'edit_project', 'key' => 'edit_project', 'description' => 'Can edit projects'],
            ['id' => 12, 'name' => 'delete_project', 'key' => 'delete_project', 'description' => 'Can delete projects'],
            ['id' => 13, 'name' => 'assign_task', 'key' => 'assign_task', 'description' => 'Can assign tasks to users'],
            ['id' => 14, 'name' => 'update_task_status', 'key' => 'update_task_status', 'description' => 'Can update task status'],
            ['id' => 15, 'name' => 'view_task_progress', 'key' => 'view_task_progress', 'description' => 'Can view task progress'],
            ['id' => 16, 'name' => 'view_invoices', 'key' => 'view_invoices', 'description' => 'Can view invoices'],
            ['id' => 17, 'name' => 'create_invoice', 'key' => 'create_invoice', 'description' => 'Can create invoices'],
            ['id' => 18, 'name' => 'edit_invoice', 'key' => 'edit_invoice', 'description' => 'Can edit invoices'],
            ['id' => 19, 'name' => 'delete_invoice', 'key' => 'delete_invoice', 'description' => 'Can delete invoices'],
            ['id' => 20, 'name' => 'process_payment', 'key' => 'process_payment', 'description' => 'Can process payments'],
            ['id' => 21, 'name' => 'view_reports', 'key' => 'view_reports', 'description' => 'Can view reports'],
            ['id' => 22, 'name' => 'export_reports', 'key' => 'export_reports', 'description' => 'Can export reports'],
            ['id' => 23, 'name' => 'manage_roles', 'key' => 'manage_roles', 'description' => 'Can manage user roles'],
            ['id' => 24, 'name' => 'manage_permissions', 'key' => 'manage_permissions', 'description' => 'Can manage permissions'],
            ['id' => 25, 'name' => 'manage_settings', 'key' => 'manage_settings', 'description' => 'Can manage system settings'],
        ];

        foreach ($permissions as $perm) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $perm['key']], // unique key
                [
                    'name' => $perm['name'],
                    'description' => $perm['description'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
