<?php

declare(strict_types=1);

// どのコンテナも mutates() と covers() を宣言したテストファイルをすべて読み込むため、ミュータントを撃破できる
// テストは分けないときと変わらない。宣言を読み違えるとクラスが黙って変異から漏れるので、読めない書き方は落とす。

require __DIR__.'/../../vendor/autoload.php';

$arguments = is_array($_SERVER['argv'] ?? null) ? $_SERVER['argv'] : [];
$index = (int) ($arguments[1] ?? 0);
$total = max(1, (int) ($arguments[2] ?? 1));

$paths = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator('tests', FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
        $paths[] = $file->getPathname();
    }
}
sort($paths);

$files = [];
$sizes = [];
foreach ($paths as $path) {
    $declared = declaredClasses($path);

    if ($declared === null) {
        continue;
    }

    $files[] = $path;
    foreach ($declared as $class) {
        $source = (new ReflectionClass($class))->getFileName();
        $sizes[$class] = $source === false ? 0 : (int) filesize($source);
    }
}

if ($sizes === []) {
    fail('mutates() で宣言したクラスがありません。');
}

// pest は名前空間とクラス名の前方一致でファイルを選ぶため、同じファイルに当たりうるクラスを別々のコンテナへ
// 分けると同じ変異を二度試す。
ksort($sizes);
$groups = [];
foreach (array_keys($sizes) as $class) {
    $related = array_keys(array_filter($groups, fn (array $group): bool => array_filter(
        $group,
        fn (string $member): bool => overlaps($class, $member),
    ) !== []));
    $groups[] = array_merge([$class], ...array_map(fn (int $key): array => $groups[$key], $related));
    $groups = array_values(array_diff_key($groups, array_flip($related)));
}

$weights = array_map(fn (array $group): int => array_sum(array_map(fn (string $class): int => $sizes[$class], $group)), $groups);
uksort($groups, fn (int $a, int $b): int => [$weights[$b], min($groups[$a])] <=> [$weights[$a], min($groups[$b])]);
$loads = array_fill(0, $total, 0);
$assigned = [];
foreach ($groups as $key => $group) {
    $lightest = array_search(min($loads), $loads, true);
    $loads[$lightest] += $weights[$key];
    if ($lightest === $index) {
        $assigned = [...$assigned, ...$group];
    }
}

if ($assigned !== []) {
    echo '--class='.implode(',', $assigned)."\n".implode("\n", $files)."\n";
}

/**
 * @return list<class-string>|null
 */
function declaredClasses(string $path): ?array
{
    $tokens = array_values(array_filter(
        PhpToken::tokenize((string) file_get_contents($path)),
        fn (PhpToken $token): bool => ! $token->isIgnorable(),
    ));

    $namespace = '';
    $imports = [];
    $classes = null;
    $braces = 0;
    $parentheses = 0;

    foreach ($tokens as $position => $token) {
        $braces += match ($token->text) {
            '{' => 1, '}' => -1, default => 0
        };
        $parentheses += match ($token->text) {
            '(' => 1, ')' => -1, default => 0
        };
        $previous = $tokens[$position - 1] ?? null;
        $next = $tokens[$position + 1] ?? null;
        $name = strtolower(ltrim($token->text, '\\'));

        if ($token->is(T_NAMESPACE) && $next?->is([T_STRING, T_NAME_QUALIFIED])) {
            $namespace = $next->text;
        } elseif ($token->is(T_USE) && $braces === 0 && $parentheses === 0) {
            $imports = [...$imports, ...imports($tokens, $position + 1, $path)];
        } elseif ($previous?->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR])
            && in_array($name, ['covers', 'coversclass', 'coverstrait'], true)) {
            fail("{$path}:{$token->line}: テストごとの covers は振り分けられません。ファイルの mutates() にしてください。");
        } elseif (in_array($name, ['mutates', 'covers'], true)
            && $token->is([T_STRING, T_NAME_FULLY_QUALIFIED])
            && $next?->text === '('
            && ! $previous?->is([T_FUNCTION, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW])) {
            $classes = [...($classes ?? []), ...targets($tokens, $position + 2, $namespace, $imports, $path)];
        }
    }

    return $classes;
}

/**
 * @param  list<PhpToken>  $tokens
 * @return array<string, string>
 */
function imports(array $tokens, int $position, string $path): array
{
    if ($tokens[$position]->is([T_FUNCTION, T_CONST]) || $tokens[$position]->text === '(') {
        return [];
    }

    $imports = [];

    for (; $tokens[$position]->text !== ';'; $position++) {
        $token = $tokens[$position];

        if ($token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
            $name = ltrim($token->text, '\\');
            $alias = $tokens[$position + 1]->is(T_AS) ? $tokens[$position += 2]->text : substr(strrchr('\\'.$name, '\\'), 1);
            $imports[strtolower($alias)] = $name;
        } elseif ($token->text !== ',') {
            fail("{$path}:{$token->line}: この use 文は読めません。");
        }
    }

    return $imports;
}

/**
 * @param  list<PhpToken>  $tokens
 * @param  array<string, string>  $imports
 * @return list<class-string>
 */
function targets(array $tokens, int $position, string $namespace, array $imports, string $path): array
{
    $targets = [];

    for ($token = $tokens[$position]; $token->text !== ')'; $token = $tokens[++$position]) {
        if ($token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])
            && $tokens[$position + 1]->is(T_DOUBLE_COLON) && $tokens[$position + 2]->is(T_CLASS)) {
            $targets[] = resolve($token, $namespace, $imports);
            $position += 2;
        } elseif ($token->is(T_CONSTANT_ENCAPSED_STRING)) {
            $targets[] = ltrim(str_replace(['\\\\', "\\'"], ['\\', "'"], substr($token->text, 1, -1)), '\\');
        } elseif (! in_array($token->text, [',', '[', ']'], true)) {
            fail("{$path}:{$token->line}: 宣言の引数は ::class か文字列で書いてください。");
        }
    }

    foreach ($targets as $target) {
        if (! class_exists($target) && ! interface_exists($target) && ! trait_exists($target) && ! enum_exists($target)) {
            fail("{$path}: {$target} を読み込めません。");
        }
    }

    /** @var list<class-string> $targets */
    return $targets;
}

/**
 * @param  array<string, string>  $imports
 */
function resolve(PhpToken $name, string $namespace, array $imports): string
{
    if ($name->is(T_NAME_FULLY_QUALIFIED)) {
        return ltrim($name->text, '\\');
    }

    [$first, $rest] = array_pad(explode('\\', $name->text, 2), 2, null);
    $imported = $imports[strtolower($first)] ?? null;

    if ($imported === null) {
        return ltrim($namespace.'\\'.$name->text, '\\');
    }

    return $rest === null ? $imported : $imported.'\\'.$rest;
}

function overlaps(string $class, string $other): bool
{
    $prefixed = fn (string $a, string $b): bool => str_starts_with($a, $b) || str_starts_with($b, $a);
    $split = fn (string $name): array => [(string) substr($name, 0, (int) strrpos($name, '\\')), substr((string) strrchr('\\'.$name, '\\'), 1)];
    [$namespace, $name] = $split($class);
    [$otherNamespace, $otherName] = $split($other);

    return $prefixed($namespace, $otherNamespace) && $prefixed($name, $otherName);
}

// CLI の例外は標準出力に出て pest の引数として読まれるため、理由は標準エラーへ書く。
function fail(string $message): never
{
    fwrite(STDERR, $message."\n");
    exit(1);
}
