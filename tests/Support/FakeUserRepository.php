<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use App\Repositories\User\UserRepositoryContract;
use BadMethodCallException;
use Closure;
use Illuminate\Support\Collection;

/**
 * 呼ばれる経路だけをクロージャで差し込むリポジトリのフェイク。
 * 差し込まなかったメソッドは BadMethodCallException で落ちるため、
 * そのテストが通す経路がコンストラクタの引数名に現れる。
 */
final class FakeUserRepository implements UserRepositoryContract
{
    /**
     * @param  (Closure(array{name: string, email: string, password: string}): User)|null  $create
     * @param  (Closure(string|int, bool): ?User)|null  $find
     * @param  (Closure(string, string, bool): Collection<int, User>)|null  $findCandidates
     * @param  (Closure(string, bool): ?User)|null  $findByGoogleId
     * @param  (Closure(string, bool): ?User)|null  $findByEmail
     * @param  (Closure(User): void)|null  $save
     * @param  (Closure(User, string): void)|null  $updatePassword
     */
    public function __construct(
        private readonly ?Closure $create = null,
        private readonly ?Closure $find = null,
        private readonly ?Closure $findCandidates = null,
        private readonly ?Closure $findByGoogleId = null,
        private readonly ?Closure $findByEmail = null,
        private readonly ?Closure $save = null,
        private readonly ?Closure $updatePassword = null,
    ) {}

    public function create(array $attributes): User
    {
        return ($this->create ?? $this->missing(__FUNCTION__))($attributes);
    }

    public function find(string|int $id, bool $lock = false): ?User
    {
        return ($this->find ?? $this->missing(__FUNCTION__))($id, $lock);
    }

    public function findCandidates(string $googleId, string $email, bool $lock = false): Collection
    {
        return ($this->findCandidates ?? $this->missing(__FUNCTION__))($googleId, $email, $lock);
    }

    public function findByGoogleId(string $googleId, bool $lock = false): ?User
    {
        return ($this->findByGoogleId ?? $this->missing(__FUNCTION__))($googleId, $lock);
    }

    public function findByEmail(string $email, bool $lock = false): ?User
    {
        return ($this->findByEmail ?? $this->missing(__FUNCTION__))($email, $lock);
    }

    public function save(User $user): void
    {
        ($this->save ?? $this->missing(__FUNCTION__))($user);
    }

    public function updatePassword(User $user, string $newPassword): void
    {
        ($this->updatePassword ?? $this->missing(__FUNCTION__))($user, $newPassword);
    }

    private function missing(string $method): never
    {
        throw new BadMethodCallException(
            sprintf('%s::%s() は差し込まれていません。', self::class, $method),
        );
    }
}
