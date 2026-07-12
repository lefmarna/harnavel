<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cache/cache_locks.expiration を Laravel の現行既定に合わせて bigInteger 化する。
 *
 * expiration は Unix タイムスタンプを格納する列で、integer のままだと 2038 年に上限へ達する。
 * 既存の create マイグレーションは出荷済みのため直接書き換えず、新規 alter で吸収する。
 * queue 系テーブル（jobs/job_batches/failed_jobs）は本テンプレが drop 済みのため対象外。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cache', function (Blueprint $table) {
            $table->bigInteger('expiration')->change();
        });

        Schema::table('cache_locks', function (Blueprint $table) {
            $table->bigInteger('expiration')->change();
        });
    }

    public function down(): void
    {
        Schema::table('cache_locks', function (Blueprint $table) {
            $table->integer('expiration')->change();
        });

        Schema::table('cache', function (Blueprint $table) {
            $table->integer('expiration')->change();
        });
    }
};
