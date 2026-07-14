<?php

declare(strict_types=1);

const RETRY_AFTER_SECONDS = 60;

$deployDirectory = getcwd();

if ($deployDirectory === false) {
    fwrite(STDERR, "Unable to determine the deployment directory.\n");

    exit(1);
}

$frameworkDirectory = $deployDirectory.'/storage/framework';

try {
    if (! is_dir($frameworkDirectory)
        && ! mkdir($frameworkDirectory, 0775, true)
        && ! is_dir($frameworkDirectory)) {
        throw new RuntimeException("Unable to create {$frameworkDirectory}.");
    }

    $html = <<<'HTML'
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>メンテナンス中</title>
    <style>
        :root { color-scheme: light dark; font-family: system-ui, sans-serif; }
        body { min-height: 100vh; margin: 0; display: grid; place-items: center; background: #f8fafc; color: #0f172a; }
        main { width: min(36rem, calc(100% - 3rem)); padding: 2rem; border: 1px solid #cbd5e1; border-radius: 0.75rem; background: #fff; box-shadow: 0 1rem 2rem rgb(15 23 42 / 8%); }
        h1 { margin-top: 0; font-size: 1.5rem; }
        p { margin-bottom: 0; line-height: 1.75; }
        @media (prefers-color-scheme: dark) {
            body { background: #020617; color: #e2e8f0; }
            main { border-color: #334155; background: #0f172a; box-shadow: none; }
        }
    </style>
</head>
<body>
    <main>
        <h1>現在利用できません</h1>
        <p>現在メンテナンス中です。作業が完了するまで、しばらくお待ちください。</p>
    </main>
</body>
</html>
HTML;

    $payload = json_encode([
        'except' => [],
        'redirect' => null,
        'retry' => RETRY_AFTER_SECONDS,
        'refresh' => null,
        'secret' => null,
        'status' => 503,
        'template' => $html,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    $responder = sprintf(<<<'PHP'
<?php

declare(strict_types=1);

http_response_code(503);
header('Retry-After: %d');
header('Cache-Control: no-store');

$acceptable = array_map('trim', explode(',', $_SERVER['HTTP_ACCEPT'] ?? ''));
$firstAcceptable = strtolower((string) strtok($acceptable[0] ?? '', ';'));
$expectsJson = str_contains($firstAcceptable, '/json')
    || str_contains($firstAcceptable, '+json')
    || (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'XMLHttpRequest') === 0);

if ($expectsJson) {
    header('Content-Type: application/json; charset=UTF-8');
    echo '{"message":"Service Unavailable"}';

    exit;
}

header('Content-Type: text/html; charset=UTF-8');
echo %s;

exit;
PHP, RETRY_AFTER_SECONDS, var_export($html, true));

    writeAtomically($frameworkDirectory.'/down', $payload);
    writeAtomically($frameworkDirectory.'/maintenance.php', $responder);
} catch (Throwable $exception) {
    fwrite(STDERR, "Unable to enable deployment maintenance: {$exception->getMessage()}\n");

    exit(1);
}

/**
 * Replace a deployment control file only after its complete contents have been written.
 */
function writeAtomically(string $path, string $contents): void
{
    $temporaryPath = $path.'.tmp.'.bin2hex(random_bytes(8));

    try {
        if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
            throw new RuntimeException("Unable to write {$temporaryPath}.");
        }

        if (! rename($temporaryPath, $path)) {
            throw new RuntimeException("Unable to replace {$path}.");
        }
    } finally {
        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }
}
