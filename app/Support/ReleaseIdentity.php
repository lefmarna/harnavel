<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 稼働中リリースの識別子。
 *
 * 値の出所はフロントコントローラ（public/index.php）が define したコンパイル時リテラルだけで、
 * 実行時に読み直せる補助ファイルへはフォールバックしない。更新可能なデータを読む実装にすると、
 * 旧バイトコードのまま新しい ID を返せてしまい、リリース同一性の検証が偽陽性になる。
 * 保証範囲と限界は TADR-0006 を参照。
 */
final readonly class ReleaseIdentity
{
    private const string CONSTANT = 'LARAVEL_DEPLOYMENT_RELEASE_ID';

    public function __construct(private ?string $id) {}

    /**
     * 収集側の adapter。リポジトリ上のリテラルは常に空なので、静的解析はこの定数を空文字と
     * 見なす。実際の値はデプロイ時に焼き込まれるため、判定は fromValue 側で行う。
     */
    public static function fromFrontController(): self
    {
        return self::fromValue(defined(self::CONSTANT) ? constant(self::CONSTANT) : null);
    }

    /** 正規化。未定義・空文字・非文字列はいずれも「リリースID未確定」として扱う。 */
    public static function fromValue(mixed $value): self
    {
        return new self(is_string($value) && $value !== '' ? $value : null);
    }

    public function id(): ?string
    {
        return $this->id;
    }
}
