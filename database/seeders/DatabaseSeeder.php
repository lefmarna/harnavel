<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 開発者用の Test User はローカル/テストでだけ作る。本番は composer --no-dev で
        // faker（require-dev）が無く fake() が未定義になり、そもそも本番へ固定ユーザーを
        // 置く必要も無いため。これで `db:seed` を production-safe に保つ。
        if (app()->environment('local', 'testing')) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }
    }
}
