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
    /** @var array<string, Closure> */
    private readonly array $handlers;

    public function __construct(
        ?Closure $create = null,
        ?Closure $find = null,
        ?Closure $findCandidates = null,
        ?Closure $findByGoogleId = null,
        ?Closure $findByEmail = null,
        ?Closure $save = null,
        ?Closure $updatePassword = null,
    ) {
        $this->handlers = array_filter(compact(
            'create',
            'find',
            'findCandidates',
            'findByGoogleId',
            'findByEmail',
            'save',
            'updatePassword',
        ));
    }

    public function create(array $attributes): User
    {
        /** @var User */
        return ($this->handler('create'))($attributes);
    }

    public function find(string|int $id, bool $lock = false): ?User
    {
        /** @var User|null */
        return ($this->handler('find'))($id, $lock);
    }

    public function findCandidates(string $googleId, string $email, bool $lock = false): Collection
    {
        /** @var Collection<int, User> */
        return ($this->handler('findCandidates'))($googleId, $email, $lock);
    }

    public function findByGoogleId(string $googleId, bool $lock = false): ?User
    {
        /** @var User|null */
        return ($this->handler('findByGoogleId'))($googleId, $lock);
    }

    public function findByEmail(string $email, bool $lock = false): ?User
    {
        /** @var User|null */
        return ($this->handler('findByEmail'))($email, $lock);
    }

    public function save(User $user): void
    {
        ($this->handler('save'))($user);
    }

    public function updatePassword(User $user, string $newPassword): void
    {
        ($this->handler('updatePassword'))($user, $newPassword);
    }

    private function handler(string $method): Closure
    {
        return $this->handlers[$method] ?? throw new BadMethodCallException(
            sprintf('%s::%s() は差し込まれていません。', self::class, $method),
        );
    }
}
