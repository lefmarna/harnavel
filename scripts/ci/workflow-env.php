<?php

declare(strict_types=1);

// CircleCI の手順に、GitHub Actions の同名ステップと同じ環境変数を渡す。アプリが足した値も二重管理しない。

use Symfony\Component\Yaml\Yaml;

require __DIR__.'/../../vendor/autoload.php';

$arguments = is_array($_SERVER['argv'] ?? null) ? $_SERVER['argv'] : [];
$file = is_string($arguments[1] ?? null) ? $arguments[1] : '';
$step = is_string($arguments[2] ?? null) ? $arguments[2] : '';

$workflow = Yaml::parseFile($file);
$jobs = is_array($workflow) && is_array($workflow['jobs'] ?? null) ? $workflow['jobs'] : [];

$env = null;
foreach ($jobs as $job) {
    $steps = is_array($job) && is_array($job['steps'] ?? null) ? $job['steps'] : [];

    foreach ($steps as $candidate) {
        if (is_array($candidate) && ($candidate['name'] ?? null) === $step) {
            $env = $candidate['env'] ?? [];
        }
    }
}

if (! is_array($env)) {
    fwrite(STDERR, "{$file} に \"{$step}\" ステップがありません。\n");
    exit(1);
}

foreach ($env as $key => $value) {
    // GitHub Actions の式は CircleCI で評価できないため、同じ条件を再現できないものとして止める。
    if (! preg_match('/^[A-Z_][A-Z0-9_]*$/', (string) $key) || ! is_scalar($value) || str_contains((string) $value, '${{')) {
        fwrite(STDERR, "{$step} の {$key} は GitHub Actions の外で再現できません。\n");
        exit(1);
    }

    echo 'export '.$key.'='.escapeshellarg((string) $value)."\n";
}
