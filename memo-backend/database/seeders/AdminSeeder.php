<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;


class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
            // Check if admin user already exists
            $adminEmail = 'admin@memo.com';
            if (DB::table('users')->where('email', $adminEmail)->doesntExist()) {
                DB::table('users')->insert([
                    'firstName' => 'Admin',
                    'lastName' => 'User',
                    'branch_code' => '0000',
                    'username' => 'admin',
                    'contact' => '0000000000',
                    'branch'=> '0000',
                    'position' => 'Admin',
                    'employee_id' => '0000',
                    'email' => $adminEmail,
                    'password' => Hash::make('123456'),
                    'role' => 'Admin',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
    }
}
