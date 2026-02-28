<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Google認証などのOAuth専用ユーザー（password IS NULL）が存在する場合、
        // そのまま NOT NULL 制約に戻すとデータベース側のエラー（ALTER失敗）が発生します。
        // そのため、ロールバック前にログイン不可能なランダムパスワードを補完します。
        //
        // Hash::make() を使用することで、アプリ設定のハッシュアルゴリズム（Argon2等）に従い
        // 仮に認証処理が走っても「ハッシュ形式不正」による 500エラー を防ぎます。
        DB::table('users')
            ->whereNull('password')
            ->update([
                'password' => Hash::make(Str::random(32)),
            ]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
