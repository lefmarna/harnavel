<?php

declare(strict_types=1);

namespace App\Repositories\User;

use App\Models\User;
use Illuminate\Support\Collection;

interface UserRepositoryContract
{
    public function find(string|int $id, bool $lock = false): ?User;

    /**
     * $lock = true のとき id 昇順でロックする（デッドロック防止）。email の大小文字の違いは、
     * このメソッドと findByEmail() のどちらも MariaDB の照合順序に委ねる。
     *
     * @return Collection<int, User>
     */
    public function findCandidates(string $googleId, string $email, bool $lock = false): Collection;

    public function findByGoogleId(string $googleId, bool $lock = false): ?User;

    public function findByEmail(string $email, bool $lock = false): ?User;

    public function save(User $user): void;
}
