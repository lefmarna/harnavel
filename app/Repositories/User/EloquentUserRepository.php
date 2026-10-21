<?php

declare(strict_types=1);

namespace App\Repositories\User;

use App\Models\User;
use Illuminate\Support\Collection;

class EloquentUserRepository implements UserRepositoryContract
{
    public function find(string|int $id, bool $lock = false): ?User
    {
        $query = User::query()->whereKey($id);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function findCandidates(string $googleId, string $email, bool $lock = false): Collection
    {
        $query = User::query()
            ->where('google_id', $googleId)
            ->orWhere('email', $email)
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    public function findByGoogleId(string $googleId, bool $lock = false): ?User
    {
        $query = User::query()->where('google_id', $googleId);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function findByEmail(string $email, bool $lock = false): ?User
    {
        $query = User::query()->where('email', $email);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function save(User $user): void
    {
        $user->save();
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
