<?php

declare(strict_types=1);

namespace App\Params;

use Illuminate\Support\Str;

final readonly class StoreUserParams
{
    public function __construct(
        /** 表示名 */
        public readonly string $name,
        /** メールアドレス */
        public readonly string $email,
        /** パスワード */
        public readonly string $password,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string}  $input
     */
    public static function fromInput(array $input): self
    {
        return new self(
            name: $input['name'],
            email: Str::lower($input['email']),
            password: $input['password'],
        );
    }

    /**
     * @return array{name: string, email: string, password: string}
     *
     * User モデルの mass assignment 用の属性配列へ変換する。
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
        ];
    }
}
