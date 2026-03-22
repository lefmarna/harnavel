<?php

declare(strict_types=1);

namespace App\Params;

final readonly class UpdateProfileParams
{
    public function __construct(
        /** 表示名 */
        public string $name,
        /** メールアドレス */
        public string $email,
    ) {}

    /**
     * @param  array{name: string, email: string}  $input
     */
    public static function fromInput(array $input): self
    {
        return new self(
            name: $input['name'],
            email: $input['email'],
        );
    }

    /**
     * @return array{name: string, email: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
