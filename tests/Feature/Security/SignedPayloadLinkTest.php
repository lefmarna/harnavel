<?php

declare(strict_types=1);

use App\Support\SignedPayloadLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

mutates(SignedPayloadLink::class);

function signedPayloadRequest(string $url): Request
{
    $request = Request::create($url);
    $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));

    return $request;
}

describe('署名付きの確認リンク', function () {
    it('作ったリンクから値を読める', function () {
        $url = SignedPayloadLink::make('email-change.verify', now()->addHour(), 1, ['from' => 'a@example.com', 'to' => 'b@example.com']);

        expect(SignedPayloadLink::read(signedPayloadRequest($url), ['from', 'to']))
            ->toBe(['from' => 'a@example.com', 'to' => 'b@example.com']);
    });

    it('値はリンクの URL から読めない', function () {
        $url = SignedPayloadLink::make('email-change.verify', now()->addHour(), 1, ['to' => 'secret@example.com']);

        expect($url)->not->toContain('secret');
    });

    // メールの計測で付くクエリのせいで、本人がリンクを使えなくならないようにする。
    it('署名の後に付いたクエリがあっても読める', function () {
        $url = SignedPayloadLink::make('email-change.verify', now()->addHour(), 1, ['to' => 'b@example.com']);

        expect(SignedPayloadLink::read(signedPayloadRequest($url.'&utm_source=mail'), ['to']))
            ->toBe(['to' => 'b@example.com']);
    });

    it('期限切れや改ざんされたリンクは読まずに記録する', function (Closure $tamper) {
        Log::spy();
        $url = $tamper(SignedPayloadLink::make('email-change.verify', now()->addHour(), 1, ['to' => 'b@example.com']));

        expect(SignedPayloadLink::read(signedPayloadRequest($url), ['to']))->toBeNull();
        Log::shouldHaveReceived('warning')->with('Signed link is invalid or expired.', ['path' => 'settings/email/verify/1'])->once();
    })->with([
        '期限切れ' => [function (string $url): string {
            test()->travel(61)->minutes();

            return $url;
        }],
        '署名の改ざん' => [fn (string $url): string => $url.'0'],
        '値の改ざん' => [fn (string $url): string => str_replace('payload=', 'payload=x', $url)],
        '期限の延長' => [fn (string $url): string => preg_replace('/expires=\d+/', 'expires='.now()->addYear()->getTimestamp(), $url) ?? $url],
        // 署名はキーの表記のまま計算するが、PHP は変換したキーで期限を上書きする。
        'エンコードした期限の追加' => [fn (string $url): string => $url.'&%65xpires='.now()->addYear()->getTimestamp()],
        '期限の重複' => [fn (string $url): string => $url.'&expires='.now()->addYear()->getTimestamp()],
    ]);

    // POST 本文の値は署名されていないので、別のリンクの値に差し替えさせない。
    it('署名したクエリの値だけを読む', function () {
        $url = SignedPayloadLink::make('email-change.verify', now()->addHour(), 1, ['to' => 'signed@example.com']);
        $other = SignedPayloadLink::make('email-change.verify', now()->addHour(), 1, ['to' => 'other@example.com']);
        parse_str((string) parse_url($other, PHP_URL_QUERY), $otherQuery);

        $request = Request::create($url, 'POST', ['payload' => $otherQuery['payload']]);
        $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));

        expect(SignedPayloadLink::read($request, ['to']))->toBe(['to' => 'signed@example.com']);
    });

    it('復号できない値は読まずに例外の種類だけを記録する', function () {
        Log::spy();
        $url = URL::temporarySignedRoute('email-change.verify', now()->addHour(), ['userId' => 1, 'payload' => 'not-encrypted']);

        expect(SignedPayloadLink::read(signedPayloadRequest($url), ['to']))->toBeNull();
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'Signed link payload is invalid.'
            && $context['path'] === 'settings/email/verify/1'
            && is_string($context['exception_class']))->once();
    });

    it('値が欠けている・文字列でない・空のリンクは読まない', function (mixed $payload) {
        $url = URL::temporarySignedRoute('email-change.verify', now()->addHour(), [
            'userId' => 1,
            'payload' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);

        expect(SignedPayloadLink::read(signedPayloadRequest($url), ['from', 'to']))->toBeNull();
    })->with([
        '欠けている' => [['from' => 'a@example.com']],
        '文字列でない' => [['from' => 'a@example.com', 'to' => 1]],
        '空' => [['from' => 'a@example.com', 'to' => '']],
        '配列でない' => ['a@example.com'],
    ]);
});
