<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

describe('例外の詳細', function () {
    it('DB の例外メッセージにクエリへ渡した値を含めない', function () {
        try {
            DB::select('select * from users where email = ? and missing_column = 1', ['bound-secret@example.test']);
        } catch (QueryException $exception) {
            expect($exception->getMessage())
                ->not->toContain('bound-secret@example.test')
                ->toContain('where email = ?');

            return;
        }

        $this->fail('QueryException was not thrown.');
    });

    it('例外のスタックトレースに関数の引数を残さない', function () {
        $exception = (fn (string $password): RuntimeException => new RuntimeException('failure'))('argument-secret');

        expect($exception->getTraceAsString())->not->toContain('argument-secr');
    });
});
