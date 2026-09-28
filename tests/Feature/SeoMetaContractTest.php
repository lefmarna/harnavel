<?php

declare(strict_types=1);

use App\Models\User;
use App\ViewModels\StaticPageViewModel;
use App\ViewModels\WelcomePageViewModel;
use Inertia\Testing\AssertableInertia;

mutates(StaticPageViewModel::class, WelcomePageViewModel::class);

function extractHeadAttribute(string $html, string $tag, array $requiredAttributes, string $targetAttribute): string
{
    $lookaheads = '';

    foreach ($requiredAttributes as $name => $value) {
        $lookaheads .= sprintf(
            '(?=[^>]*\b%s="%s")',
            preg_quote($name, '/'),
            preg_quote($value, '/'),
        );
    }

    $pattern = sprintf(
        '/<%s\b%s(?=[^>]*\b%s="([^"]*)")[^>]*>/',
        preg_quote($tag, '/'),
        $lookaheads,
        preg_quote($targetAttribute, '/'),
    );

    preg_match($pattern, $html, $matches);

    return $matches[1] ?? '';
}

/**
 * JSON-LD をデコードせずに取り出す。エスケープそのものを確かめる用途で使う。
 *
 * nonce 付きの CSP を敷くと script 要素に type 以外の属性が増える。属性の並びに
 * 依存しないよう、開始タグの中を先読みで突き合わせる。
 *
 * @return list<string>
 */
function extractStructuredDataPayloads(string $html): array
{
    preg_match_all(
        '/<script\b(?=[^>]*\btype="application\/ld\+json")[^>]*>\s*(.*?)\s*<\/script>/s',
        $html,
        $matches,
    );

    return array_values($matches[1] ?? []);
}

/**
 * @return list<array<string, mixed>>
 */
function extractStructuredDataItems(string $html): array
{
    return collect(extractStructuredDataPayloads($html))
        ->map(fn (string $json): mixed => json_decode($json, true))
        ->filter(fn (mixed $schema): bool => is_array($schema))
        ->values()
        ->all();
}

/**
 * @param  list<array<string, mixed>>  $schemas
 * @return array<string, mixed>
 */
function findStructuredDataByType(array $schemas, string $type): array
{
    return collect($schemas)
        ->first(fn (array $schema): bool => ($schema['@type'] ?? null) === $type, []);
}

/**
 * 構造化データへ載るサイト説明を差し替える。
 *
 * テンプレートは config から引くだけで足りるが、説明文を翻訳ファイルへ移したアプリでは
 * ロケールを添えて訳文を差し替えることになる。注入をこの関数へ閉じ込めておけば、
 * アプリ側は中身だけを置き換えればよく、テスト本体はそのまま使える。
 */
function replaceSiteDescription(string $description): void
{
    config(['app.description' => $description]);
}

describe('SEO メタ契約', function () {
    it('ホームページはtitleとdescriptionを含むseo propsを返す', function () {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('welcome')
            ->has('seo')
            ->where('seo.title', 'ホーム')
            ->where('seo.description', config('app.description'))
        );
    });

    it('ホームページの初期HTMLにはInertia管理のSEOタグが含まれる', function () {
        $response = $this->get(route('home'));
        $content = $response->getContent();

        expect($content)->toContain('<link rel="canonical" data-inertia="canonical"')
            ->and($content)->toContain('<title data-inertia>ホーム - ')
            ->and($content)->toContain('<meta data-inertia="description" name="description"')
            ->and($content)->toContain('<meta data-inertia="robots" name="robots"')
            ->and($content)->toContain('<meta data-inertia="og:type" property="og:type" content="website">')
            ->and($content)->toContain('<meta data-inertia="og:url" property="og:url"')
            ->and($content)->toContain('<meta data-inertia="og:site_name" property="og:site_name"')
            ->and($content)->toContain('<meta data-inertia="og:title" property="og:title"')
            ->and($content)->toContain('<meta data-inertia="og:description" property="og:description"')
            ->and($content)->toContain('<meta data-inertia="og:image" property="og:image"')
            ->and($content)->toContain('<meta data-inertia="twitter:card" name="twitter:card" content="summary_large_image">')
            ->and($content)->toContain('<meta data-inertia="twitter:url" name="twitter:url"')
            ->and($content)->toContain('<meta name="twitter:site" content="@lefmarna">')
            ->and($content)->toContain('<meta name="twitter:creator" content="@lefmarna">')
            ->and($content)->toContain('<meta data-inertia="twitter:title" name="twitter:title"')
            ->and($content)->toContain('<meta data-inertia="twitter:description" name="twitter:description"')
            ->and($content)->toContain('<meta data-inertia="twitter:image" name="twitter:image"');
    });

    it('既定のOGP画像はJPEGを参照しMIMEタイプとaltを出力する', function () {
        $response = $this->get(route('home'));
        $content = $response->getContent();

        $ogImage = extractHeadAttribute($content, 'meta', ['property' => 'og:image'], 'content');
        $twitterImage = extractHeadAttribute($content, 'meta', ['name' => 'twitter:image'], 'content');
        $displayName = config('app.display_name');

        expect($ogImage)->toEndWith('/opengraph-image.jpg')
            ->and($twitterImage)->toEndWith('/opengraph-image.jpg')
            ->and($content)->toContain('<meta data-inertia="og:image:type" property="og:image:type" content="image/jpeg">')
            ->and($content)->toContain('<meta data-inertia="og:image:alt" property="og:image:alt" content="'.$displayName.'">')
            ->and($content)->toContain('<meta data-inertia="twitter:image:alt" name="twitter:image:alt" content="'.$displayName.'">');
    });

    it('ホームページの初期HTMLにはサイト用とページ用の構造化データが含まれる', function () {
        $response = $this->get(route('home'));
        $schemas = extractStructuredDataItems($response->getContent());

        $websiteSchema = findStructuredDataByType($schemas, 'WebSite');
        $webApplicationSchema = findStructuredDataByType($schemas, 'WebApplication');
        $webPageSchema = findStructuredDataByType($schemas, 'WebPage');

        expect($websiteSchema)
            ->toMatchArray([
                '@type' => 'WebSite',
                '@id' => rtrim(config('app.url', url('/')), '/').'/#website',
                'name' => config('app.display_name'),
                'description' => config('app.description'),
                'url' => rtrim(config('app.url', url('/')), '/'),
            ])
            ->and($webApplicationSchema)
            ->toMatchArray([
                '@type' => 'WebApplication',
                '@id' => rtrim(config('app.url', url('/')), '/').'/#webapplication',
                'name' => config('app.display_name'),
                'description' => config('app.description'),
                'url' => rtrim(config('app.url', url('/')), '/'),
            ])
            ->and($webPageSchema)
            ->toMatchArray([
                '@type' => 'WebPage',
                '@id' => route('home').'#webpage',
                'name' => 'ホーム - '.config('app.display_name'),
                'description' => config('app.description'),
                'url' => route('home'),
            ])
            ->and($webPageSchema['isPartOf']['@id'] ?? null)->toBe($websiteSchema['@id'])
            ->and($webPageSchema['about']['@id'] ?? null)->toBe($webApplicationSchema['@id']);
    });

    it('初期HTMLのtitle canonical og:url robotsは期待値を満たす', function () {
        $response = $this->get(route('home'));
        $content = $response->getContent();

        preg_match('/<title data-inertia>([^<]*)<\/title>/', $content, $titleMatches);
        $canonicalUrl = extractHeadAttribute($content, 'link', ['rel' => 'canonical'], 'href');
        $ogUrl = extractHeadAttribute($content, 'meta', ['property' => 'og:url'], 'content');
        $robots = extractHeadAttribute($content, 'meta', ['name' => 'robots'], 'content');

        expect($titleMatches[1] ?? '')->toBe('ホーム - '.config('app.display_name'))
            ->and($canonicalUrl)->toBe(route('home'))
            ->and($ogUrl)->toBe(route('home'))
            ->and($robots)->toBe('noindex, nofollow');
    });

    it('機微なルートのog:urlはクエリ文字列を含まない', function () {
        $url = route('password.reset', ['token' => 'test-token', 'email' => 'test@example.com']);
        $response = $this->get($url);
        $content = $response->getContent();

        preg_match('/property="og:url"\s+content="([^"]*)"/', $content, $matches);
        $ogUrl = $matches[1] ?? '';

        expect($ogUrl)->not->toBeEmpty()
            ->and($ogUrl)->not->toContain('email=')
            ->and($ogUrl)->not->toContain('?')
            ->and($ogUrl)->toContain('reset-password');
    });

    it('機微なルートのcanonicalはクエリ文字列を含まない', function () {
        $url = route('password.reset', ['token' => 'test-token', 'email' => 'test@example.com']);
        $response = $this->get($url);
        $content = $response->getContent();

        $canonicalUrl = extractHeadAttribute($content, 'link', ['rel' => 'canonical'], 'href');

        expect($canonicalUrl)->not->toBeEmpty()
            ->and($canonicalUrl)->not->toContain('email=')
            ->and($canonicalUrl)->not->toContain('?')
            ->and($canonicalUrl)->toContain('reset-password');
    });

    it('shared propsにはappDisplayName・appDescription・seo・urls・flashが含まれる', function () {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('url', rtrim(url('/'), '/'))
            ->where('currentUrl', route('home'))
            ->has('appDisplayName')
            ->has('appDescription')
            ->has('seo')
            ->has('flash')
            ->where('flash.status', null)
            ->where('flash.error', null)
        );
    });

    it('shared auth userは安定した任意フィールドを公開する', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('auth.user')
            ->where('auth.user.avatar', null)
            ->where('auth.hasPassword', true)
        );
    });

    it('guest authページはnoindex robots metaとseoを使う', function (string $routeName, array $routeParams = []) {
        $response = $this->get(route($routeName, $routeParams));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('seo')
            ->where('seo.robots', 'noindex, nofollow')
        );

        expect($response->getContent())->toContain('<meta data-inertia="robots" name="robots" content="noindex, nofollow">');
    })->with([
        'login' => ['login'],
        'register' => ['register'],
        'forgot-password' => ['password.request'],
        'reset-password' => ['password.reset', ['token' => 'test-token']],
    ]);

    // JSON-LD は <script> の内側へ文字列として埋まる。タイトルや説明はアプリが自由に
    // 流し込む枠で、ユーザー入力や生成物が届く経路を持つアプリもある。エスケープが
    // 無いと `</script>` で script 要素を抜け、後続が HTML として解釈される。
    it('構造化データに script を閉じる文字列が入っても script 要素を抜けない', function () {
        $payload = '</script><script>alert(1)</script>';
        replaceSiteDescription($payload);

        $content = $this->get(route('home'))->getContent();

        // 生の HTML に現れないこと。現れた時点で script 要素は閉じている。
        expect($content)->not->toContain($payload);

        // `<` そのものを退避していること。`/` の退避だけでも生の `</script>` は出ないため、
        // 上の assert では `<` を塞ぐ側（JSON_HEX_TAG）が落ちても気づけない。
        $payloads = extractStructuredDataPayloads($content);

        expect($payloads)->not->toBeEmpty()
            ->and($payloads[0])->toContain('\u003C\/script\u003E');

        // 退避しても JSON としては元の値のまま読めること。
        $schemas = extractStructuredDataItems($content);

        expect(findStructuredDataByType($schemas, 'WebSite')['description'] ?? null)->toBe($payload);
    });

    // `<` を退避していれば `</script>` は作れないが、`/` の退避（json_encode の既定）も
    // 残して二重に塞ぐ。片方を落としたときに穴が開かないことを、構成として固定する。
    //
    // 見るのは JSON-LD の生の中身だけ。ページ全体には Inertia の data-page など別経路の
    // JSON も載っていて、そちらのスラッシュを拾うと何も確かめられない。
    it('構造化データはスラッシュも退避したまま出力する', function () {
        $payloads = extractStructuredDataPayloads($this->get(route('home'))->getContent());
        $siteUrl = rtrim((string) config('app.url'), '/');

        expect($payloads)->not->toBeEmpty()
            ->and($payloads[0])->toContain(str_replace('/', '\\/', $siteUrl))
            ->and($payloads[0])->not->toContain($siteUrl);
    });

    it('ページ固有のdescriptionはWebPage構造化データにだけ反映される', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('profile.edit'));
        $schemas = extractStructuredDataItems($response->getContent());

        $websiteSchema = findStructuredDataByType($schemas, 'WebSite');
        $webApplicationSchema = findStructuredDataByType($schemas, 'WebApplication');
        $webPageSchema = findStructuredDataByType($schemas, 'WebPage');

        expect($websiteSchema['description'] ?? null)->toBe(config('app.description'))
            ->and($webApplicationSchema['description'] ?? null)->toBe(config('app.description'))
            ->and($webPageSchema)
            ->toMatchArray([
                '@type' => 'WebPage',
                '@id' => route('profile.edit').'#webpage',
                'name' => 'プロフィール設定 - '.config('app.display_name'),
                'description' => '名前とメールアドレスを管理します。',
                'url' => route('profile.edit'),
            ]);
    });

    it('各ページはseo.titleを公開する', function (string $routeName, string $expectedTitle, array $routeParams = []) {
        $response = $this->get(route($routeName, $routeParams));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('seo')
            ->where('seo.title', $expectedTitle)
        );
    })->with([
        'home' => ['home', 'ホーム'],
        'login' => ['login', 'ログイン'],
        'register' => ['register', '新規登録'],
        'forgot-password' => ['password.request', 'パスワードをお忘れの方'],
        'reset-password' => ['password.reset', 'パスワード再設定', ['token' => 'test-token']],
    ]);

    it('ゲスト向け認証ページはseo.descriptionを公開する', function (string $routeName, string $expectedDescription, array $routeParams = []) {
        $response = $this->get(route($routeName, $routeParams));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('seo')
            ->where('seo.description', $expectedDescription)
        );
    })->with([
        'login' => ['login', 'メールアドレスとパスワードを入力してログインします。'],
        'register' => ['register', '必要事項を入力して新しいアカウントを作成します。'],
        'forgot-password' => ['password.request', 'メールアドレスを入力してパスワード再設定リンクを受け取ります。'],
        'reset-password' => ['password.reset', 'アカウントの新しいパスワードを設定します。', ['token' => 'test-token']],
    ]);

    it('認証済みページはseo.titleとdescriptionを持つ', function (string $routeName, string $expectedTitle, string $expectedDescription) {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route($routeName));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('seo')
            ->where('seo.title', $expectedTitle)
            ->where('seo.description', $expectedDescription)
        );
    })->with([
        'dashboard' => ['dashboard', 'ダッシュボード', 'アプリケーションの利用状況や設定への導線を確認できます。'],
        'profile' => ['profile.edit', 'プロフィール設定', '名前とメールアドレスを管理します。'],
        'security' => ['security.edit', 'セキュリティ設定', 'パスワードとパスキーを管理します。'],
        'appearance' => ['appearance.edit', '表示設定', 'アカウントの表示設定を変更します。'],
    ]);

    it('メール認証ページはseo.titleとdescriptionを持つ', function () {
        $user = User::factory()->create(['email_verified_at' => null]);

        $response = $this->actingAs($user)->get(route('verification.notice'));
        $content = $response->getContent();

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('seo')
            ->where('seo.title', 'メール認証')
            ->where('seo.description', 'メールアドレスの認証を完了してください。')
            ->where('seo.robots', 'noindex, nofollow')
        );

        expect($content)->toContain('<meta data-inertia="robots" name="robots" content="noindex, nofollow">');
    });

    it('本人確認ページはseo.titleとdescriptionを持つ', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('password.confirm'));
        $content = $response->getContent();

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('seo')
            ->where('seo.title', '本人確認')
            ->where('seo.description', '続行するには現在のパスワードを入力してください。')
            ->where('seo.robots', 'noindex, nofollow')
        );

        expect($content)->toContain('<meta data-inertia="robots" name="robots" content="noindex, nofollow">');
    });

});
