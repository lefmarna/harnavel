<?php

declare(strict_types=1);

namespace App\Repositories\User;

use App\Models\User;
use Illuminate\Support\Collection;

interface UserRepositoryContract
{
    /**
     * google_id または email に一致する候補ユーザーをまとめて取得する。
     * $lock = true のとき id 昇順で SELECT ... FOR UPDATE を行う（デッドロック防止）。
     *
     * @return Collection<int, User>
     */
    public function findCandidates(string $googleId, string $email, bool $lock = false): Collection;

    /**
     * google_id でユーザーを検索する（フォールバック再取得用）。
     */
    public function findByGoogleId(string $googleId, bool $lock = false): ?User;

    /**
     * email でユーザーを検索する（フォールバック再取得用）。
     */
    public function findByEmail(string $email, bool $lock = false): ?User;

    /**
     * ユーザーを保存する（INSERT / UPDATE どちらも対応）。
     */
    public function save(User $user): void;
}
