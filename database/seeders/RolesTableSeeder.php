<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesTableSeeder extends Seeder
{
    public function run()
    {
        $roles = ['admin','professional','individual'];

        foreach($roles as $role){
            Role::firstOrCreate(['name'=>$role]);
        }
    }
}
