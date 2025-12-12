<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuickMessageSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('quick_messages')->insert([
            [
                'text' => '5 dk geliyorum',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'text' => 'Acil, aşağıdan ulaşın',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'text' => 'Aracınız yolu kapatıyor',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'text' => 'Otopark görevlisiyim',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'text' => 'Aracı bulamadım',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
