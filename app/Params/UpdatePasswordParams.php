<?php

declare(strict_types=1);

namespace App\Params;

use Illuminate\Support\Facades\Hash;

final readonly class UpdatePasswordParams
{
    public function __construct(
        /** ハッシュ化前の新しいパスワード */
        public string $password,
    ) {}

    /**
     * @param  array{password: string}  $input
     */
    public static function fromInput(array $input): self
    {
        return new self(
            password: $input['password'],
        );
    }

    /**
     * @return array{password: string}
     */
    public function toArray(): array
    {
        return [
            'password' => Hash::make($this->password),
        ];
    }
}
