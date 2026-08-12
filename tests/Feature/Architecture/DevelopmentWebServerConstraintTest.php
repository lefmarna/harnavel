<?php

declare(strict_types=1);

describe('開発用Webサーバーの制約', function () {
    it('build成果物の大きなLinkレスポンスヘッダーを処理できる', function () {
        expect(settingLine('.docker/nginx/nginx.conf', 'fastcgi_buffer_size'))
            ->toBe('fastcgi_buffer_size 8k;');
    });

    it('Laravelの5xxレスポンスを静的エラーページへ置き換えない', function () {
        $config = file_get_contents(base_path('.docker/nginx/nginx.conf'));

        expect($config)
            ->not->toBeFalse()
            ->not->toMatch('/\berror_page\s+500\s+502\s+503\s+504\s+\/error\.html\s*;/');
    });

    it('reverse proxyのschemeを渡し既存のhostとclient addressの転送を維持する', function () {
        $config = file_get_contents(base_path('.docker/nginx/nginx.conf'));

        expect($config)
            ->not->toBeFalse()
            ->toContain('fastcgi_param HTTP_X_FORWARDED_PROTO $http_x_forwarded_proto;')
            ->toContain('fastcgi_param HTTP_X_FORWARDED_HOST $http_host;')
            ->toContain('fastcgi_param HTTP_X_FORWARDED_FOR $proxy_add_x_forwarded_for;');
    });
});
