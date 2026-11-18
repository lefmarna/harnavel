<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

describe('2038 年 1 月 19 日より後の日時', function () {
    it('日時の列に、2038 年 1 月 19 日より後を保存できない timestamp 型を使わない', function () {
        $timestampColumns = collect(Schema::getTableListing(schemaQualified: false))
            ->flatMap(fn (string $table): array => collect(Schema::getColumns($table))
                ->where('type_name', 'timestamp')
                ->map(fn (array $column): string => "{$table}.{$column['name']}")
                ->all())
            ->all();

        expect($timestampColumns)->toBe([]);
    });

    it('2038 年 1 月 19 日を過ぎても、データベースセッションを保存できる', function () {
        config(['session.driver' => 'database']);
        $this->travelTo(CarbonImmutable::parse('2038-01-19 03:14:08 UTC'));

        $this->get(route('login'))->assertOk();

        expect(DB::table('sessions')->value('last_activity'))->toBe(now()->getTimestamp());
    });
});
