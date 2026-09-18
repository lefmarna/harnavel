<?php

declare(strict_types=1);

namespace App\Repositories\User;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class EloquentUserRepository implements UserRepositoryContract
{
    public function create(array $attributes): User
    {
        return User::create($attributes);
    }

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

    public function updatePassword(User $user, string $newPassword): void
    {
        $user->password = Hash::make($newPassword);
        $user->save();
    }
}
