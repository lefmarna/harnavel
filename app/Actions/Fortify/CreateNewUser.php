<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Repositories\User\UserRepositoryContract;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Fortify;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        private readonly UserRepositoryContract $userRepository,
    ) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $normalizedInput = $this->normalizeInput($input);

        /** @var array{name: string, email: string, password: string} $validated */
        $validated = Validator::make($normalizedInput, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return $this->userRepository->create($validated);
    }

    /**
     * @param  array<string, string>  $input
     * @return array<string, string>
     */
    private function normalizeInput(array $input): array
    {
        if (! config('fortify.lowercase_usernames')) {
            return $input;
        }

        $usernameField = Fortify::username();
        $username = $input[$usernameField] ?? null;

        if (! is_string($username)) {
            return $input;
        }

        $input[$usernameField] = Str::lower($username);

        return $input;
    }
}
