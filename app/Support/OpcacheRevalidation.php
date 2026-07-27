<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 実行中 PHP ランタイムの OPcache 再検証設定のスナップショット。
 *
 * 収集（fromRuntime）と正規化（fromIniValues）を分離し、判定は ReleaseVerification が行う。
 * CLI と Web SAPI は別プロセス・別設定なので、リリース検証で意味を持つのはリクエストを
 * 処理した Web プロセス自身がこの値を報告したときだけ。
 *
 * null は「ini 値を解釈できなかった」ことを表し、安全側（fail closed）に倒す材料になる。
 */
final readonly class OpcacheRevalidation
{
    public function __construct(
        public bool $extensionLoaded,
        public ?bool $enabled,
        public ?bool $validateTimestamps,
        public ?int $revalidateFrequency,
        public ?bool $preloadConfigured,
    ) {}

    /**
     * opcache_get_configuration() は opcache.restrict_api の影響を受けて封じられ得るため、
     * 拡張の有無と ini 値だけから組み立てる。
     */
    public static function fromRuntime(): self
    {
        $extensionLoaded = extension_loaded('Zend OPcache');

        if (! $extensionLoaded) {
            return self::fromIniValues(false, false, false, false, false);
        }

        return self::fromIniValues(
            true,
            ini_get('opcache.enable'),
            ini_get('opcache.validate_timestamps'),
            ini_get('opcache.revalidate_freq'),
            ini_get('opcache.preload'),
        );
    }

    public static function fromIniValues(
        bool $extensionLoaded,
        string|false $enable,
        string|false $validateTimestamps,
        string|false $revalidateFrequency,
        string|false $preload,
    ): self {
        if (! $extensionLoaded) {
            return new self(false, false, false, null, null);
        }

        return new self(
            true,
            self::toBool($enable),
            self::toBool($validateTimestamps),
            self::toInt($revalidateFrequency),
            // 値そのもの（起動時スクリプトの絶対パス）は公開エンドポイントに載せない。
            // 判定に必要なのは「設定されているか」だけ。
            $preload === false ? null : $preload !== '',
        );
    }

    /**
     * @return array{extensionLoaded: bool, enabled: bool|null, validateTimestamps: bool|null, revalidateFrequency: int|null, preloadConfigured: bool|null}
     */
    public function toArray(): array
    {
        return [
            'extensionLoaded' => $this->extensionLoaded,
            'enabled' => $this->enabled,
            'validateTimestamps' => $this->validateTimestamps,
            'revalidateFrequency' => $this->revalidateFrequency,
            'preloadConfigured' => $this->preloadConfigured,
        ];
    }

    /**
     * PHP の INI 真偽値（zend_ini_parse_bool）と同じ規則で解釈する。
     *
     * filter_var(FILTER_VALIDATE_BOOLEAN) は使えない。PHP が有効と解釈する
     * `opcache.enable=2` のような値を false へ落とし、「OPcache は無効だから同一性は厳密」と
     * 誤って結論づけて、再検証しないランタイムを承認してしまう（fail open）。
     *
     * 同じ理由で (int) キャストも使えない。zend_ini_parse_bool は atoi 相当で先頭の整数部
     * だけを読み `1e-1` を 1（有効）とするが、(int) は文字列全体を数値リテラルとして解釈し
     * float 0.1 を経由して 0（無効）にする。ini_get は生の INI 文字列を返すため、この差は
     * そのまま fail open になる。先頭に現れる非ゼロの整数部で atoi の真偽を再現する。
     *
     * ただし atoi の戻り値は C int なので、範囲外は切り詰めで値も符号も変わる（2^32 の倍数は
     * 0 = 無効）。この丸めを PHP 側で移植可能に再現する手段がないため、範囲外は true と
     * 決めつけず null（判定不能）にして fail closed へ倒す（ReleaseVerification は enabled /
     * validateTimestamps の null をいずれも fail として扱う）。結果として INT_MAX 超過の
     * ような Zend が有効と解釈する値も拒否するが、承認しすぎるより安全側に振る。
     */
    private static function toBool(string|false $value): ?bool
    {
        if ($value === false) {
            return null;
        }

        if (in_array(strtolower($value), ['on', 'yes', 'true'], true)) {
            return true;
        }

        // 先頭の空白と符号、無効な 0 埋めを飛ばし、有効数字だけを取り出す。
        // 一致しなければ atoi は 0 を返す＝無効。
        if (preg_match('/^\s*([+-]?)0*([1-9]\d*)/', $value, $matches) !== 1) {
            return false;
        }

        $magnitude = $matches[2];
        $limit = $matches[1] === '-' ? '2147483648' : '2147483647';

        if (strlen($magnitude) > strlen($limit)
            || (strlen($magnitude) === strlen($limit) && strcmp($magnitude, $limit) > 0)) {
            return null;
        }

        return true;
    }

    private static function toInt(string|false $value): ?int
    {
        return $value !== false && ctype_digit($value) ? (int) $value : null;
    }
}
