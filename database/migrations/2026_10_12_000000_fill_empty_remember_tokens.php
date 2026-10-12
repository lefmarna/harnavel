<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $isEmpty = fn (Builder $query) => $query->whereNull('remember_token')->orWhere('remember_token', '');

        // 読んだ後に並行するログインが値を入れていたら、上書きせずに残す。
        DB::table('users')
            ->where($isEmpty)
            ->lazyById()
            ->each(fn (object $user) => DB::table('users')
                ->where('id', $user->id)
                ->where($isEmpty)
                ->update(['remember_token' => Str::random(60)]));
    }

    public function down(): void {}
};
