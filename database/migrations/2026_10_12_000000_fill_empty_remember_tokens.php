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
        DB::table('users')
            ->where(fn (Builder $query) => $query->whereNull('remember_token')->orWhere('remember_token', ''))
            ->lazyById()
            ->each(fn (object $user) => DB::table('users')
                ->where('id', $user->id)
                ->update(['remember_token' => Str::random(60)]));
    }

    public function down(): void {}
};
