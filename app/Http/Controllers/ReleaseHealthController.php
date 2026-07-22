<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\OpcacheRevalidation;
use App\Support\ReleaseIdentity;
use App\Support\ReleaseVerification;
use Illuminate\Http\JsonResponse;

/**
 * リリース同一性エンドポイント（TADR-0006）。
 *
 * デプロイワークフローが「今回デプロイしたリリースが応答している」ことを PHP 経由で確認する。
 * リリースIDを解決できない場合と、OPcache 設定から同一性を照合できない場合は 503 を返す。
 * 503 でも観測値は返す: 導入初期の観測モードでは、この値の記録自体が目的になる。
 */
final class ReleaseHealthController
{
    public function __construct(
        private readonly ReleaseIdentity $releaseIdentity,
        private readonly OpcacheRevalidation $opcacheRevalidation,
    ) {}

    public function __invoke(): JsonResponse
    {
        $releaseId = $this->releaseIdentity->id();
        $verdict = ReleaseVerification::verdict($this->opcacheRevalidation, null);
        $verifiable = $releaseId !== null && $verdict !== ReleaseVerification::VERDICT_FAIL;

        return response()
            ->json([
                'release' => $releaseId,
                'opcache' => $this->opcacheRevalidation->toArray(),
            ], $verifiable ? 200 : 503)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache');
    }
}
