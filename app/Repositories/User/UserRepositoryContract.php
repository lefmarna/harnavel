<?php

declare(strict_types=1);

namespace App\Repositories\User;

use App\Models\User;
use Illuminate\Support\Collection;

interface UserRepositoryContract
{
    /**
     * @param  array{name: string, email: string, password: string}  $attributes
     */
    public function create(array $attributes): User;

    public function find(string|int $id, bool $lock = false): ?User;

    /**
     * google_id または email に一致する候補ユーザーをまとめて取得する。
     * $lock = true のとき id 昇順で SELECT ... FOR UPDATE を行う（デッドロック防止）。
     * このリポジトリでは、email の大小文字非依存性は MariaDB の
     * case-insensitive collation 前提で通常比較に委ねる。
     *
     * @return Collection<int, User>
     */
    public function findCandidates(string $googleId, string $email, bool $lock = false): Collection;

    public function findByGoogleId(string $googleId, bool $lock = false): ?User;

    /**
     * email でユーザーを検索する（フォールバック再取得用）。
     * このリポジトリでは、email の大小文字非依存性は MariaDB の
     * case-insensitive collation 前提で通常比較に委ねる。
     */
    public function findByEmail(string $email, bool $lock = false): ?User;

    public function save(User $user): void;

    public function updatePassword(User $user, string $newPassword): void;
}
