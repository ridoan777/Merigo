<?php

namespace Database\Seeders\System;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission; // add this

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'admin_override',
            'index',
            'show',
            'store',
            'delete',
        ];

        foreach ($permissions as $key => $value) {
            Permission::create([
                'name' => $value
            ]);
        }
    }
}