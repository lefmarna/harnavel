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
        public ?string $preload,
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
            $preload === false ? null : $preload,
        );
    }

    /**
     * @return array{extensionLoaded: bool, enabled: bool|null, validateTimestamps: bool|null, revalidateFrequency: int|null, preload: string|null}
     */
    public function toArray(): array
    {
        return [
            'extensionLoaded' => $this->extensionLoaded,
            'enabled' => $this->enabled,
            'validateTimestamps' => $this->validateTimestamps,
            'revalidateFrequency' => $this->revalidateFrequency,
            'preload' => $this->preload,
        ];
    }

    /**
     * PHP の INI 真偽値（zend_ini_parse_bool）と同じ規則で解釈する。
     *
     * filter_var(FILTER_VALIDATE_BOOLEAN) は使えない。PHP が有効と解釈する
     * `opcache.enable=2` のような値を false へ落とし、「OPcache は無効だから同一性は厳密」と
     * 誤って結論づけて、再検証しないランタイムを承認してしまう（fail open）。
     */
    private static function toBool(string|false $value): ?bool
    {
        if ($value === false) {
            return null;
        }

        if (in_array(strtolower($value), ['on', 'yes', 'true'], true)) {
            return true;
        }

        return (int) $value !== 0;
    }

    private static function toInt(string|false $value): ?int
    {
        return $value !== false && ctype_digit($value) ? (int) $value : null;
    }
}
