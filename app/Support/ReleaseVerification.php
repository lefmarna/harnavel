<?php

declare(strict_types=1);

namespace App\Support;

/**
 * OPcache スナップショットと経過秒数だけからリリース同一性の照合可否を決める純粋な判定。
 *
 * - safe: 今回のリリースIDとの照合が意味を持つ
 * - wait: 設定は安全だが、まだ再検証期限が経過していない
 * - fail: 再検証を前提にできない、または設定を解釈できない
 *
 * XServer 共有ホスティングでは PHP-FPM を再起動できず、CLI の OPcache 操作は Web 側へ
 * 波及しない。したがって「待てば直る」以外はすべて fail closed に倒す。判断根拠は TADR-0006。
 */
final class ReleaseVerification
{
    public const string VERDICT_SAFE = 'safe';

    public const string VERDICT_WAIT = 'wait';

    public const string VERDICT_FAIL = 'fail';

    /**
     * 再検証間隔の安全上限（秒）。検証ステップのリトライ窓（10 回 × 15 秒）に収まり、
     * settle 待機後もリリースIDの一致を観測する試行が残る値。
     */
    public const int MAX_REVALIDATE_FREQUENCY = 60;

    /**
     * @param  int|null  $elapsedSeconds  リモートの書き込み完了からの経過秒数。null は未評価。
     */
    public static function verdict(OpcacheRevalidation $opcache, ?int $elapsedSeconds): string
    {
        if (! $opcache->extensionLoaded) {
            return self::VERDICT_SAFE;
        }

        if ($opcache->enabled === null) {
            return self::VERDICT_FAIL;
        }

        if (! $opcache->enabled) {
            return self::VERDICT_SAFE;
        }

        if ($opcache->validateTimestamps !== true) {
            return self::VERDICT_FAIL;
        }

        // preload されたコードはプロセス終了までタイムスタンプ再検証の対象外になる。
        if ($opcache->preload !== '') {
            return self::VERDICT_FAIL;
        }

        $frequency = $opcache->revalidateFrequency;

        if ($frequency === null || $frequency > self::MAX_REVALIDATE_FREQUENCY) {
            return self::VERDICT_FAIL;
        }

        if ($elapsedSeconds === null) {
            return self::VERDICT_SAFE;
        }

        return $elapsedSeconds >= $frequency ? self::VERDICT_SAFE : self::VERDICT_WAIT;
    }
}
