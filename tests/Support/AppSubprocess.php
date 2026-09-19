<?php

declare(strict_types=1);

namespace Tests\Support;

use Symfony\Component\Process\Process;

final class AppSubprocess
{
    /** 本番の boot が要求する env。起動時検証を増やしたらここにも足す。 */
    private const PRODUCTION_ENVIRONMENT = [
        'APP_ENV' => 'production',
        'PASSKEYS_USER_HANDLE_SECRET' => 'testing-passkey-user-handle-secret',
    ];

    /** 既定の 60 秒は並列検証中の高負荷で偶発的に届く。起動の可否しか見ないので遅れた分は待つ。 */
    private const TIMEOUT_SECONDS = 300;

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    public static function productionEnvironment(array $overrides = []): array
    {
        return [...self::PRODUCTION_ENVIRONMENT, ...$overrides];
    }

    /**
     * @param  array<string, string>  $environment
     */
    public static function php(string $script, array $environment): Process
    {
        return self::start([PHP_BINARY, '-r', $script], $environment);
    }

    /**
     * @param  list<string>  $arguments
     * @param  array<string, string>  $environment
     */
    public static function artisan(array $arguments, array $environment): Process
    {
        return self::start([PHP_BINARY, 'artisan', ...$arguments], $environment);
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $environment
     */
    private static function start(array $command, array $environment): Process
    {
        $process = new Process(
            $command,
            base_path(),
            $environment,
            timeout: self::TIMEOUT_SECONDS,
        );

        $process->run();

        return $process;
    }
}
