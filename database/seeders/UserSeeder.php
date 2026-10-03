<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'id' => 1,
            'name' => 'ユーザー１',
            'email' => 'user1@example.com',
            'admin_status' => false,
        ]);

        User::factory()->create([
            'id' => 2,
            'name' => 'ユーザー２',
            'email' => 'user2@example.com',
            'admin_status' => false,
        ]);

        User::factory()->create([
            'id' => 3,
            'name' => 'ユーザー３',
            'email' => 'user3@example.com',
            'admin_status' => true,
        ]);
    }
}
