<?php

// database/seeders/AdminSeeder.php
namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run()
    {
        Admin::create([
            'username' => 'admin123',
            'password' => Hash::make('admin123'),
        ]);
    }
}
