<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Repositories\User\UserRepositoryContract;
use App\Support\EmailAddress;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Fortify;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private readonly UserRepositoryContract $userRepository) {}

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
        ], attributes: ['name' => __('form.profile_update.name')])->validate();

        $user = new User([
            ...$validated,
            'password' => Hash::make($validated['password']),
        ]);
        $this->userRepository->save($user);

        return $user;
    }

    /**
     * @param  array<string, string>  $input
     * @return array<string, string>
     */
    private function normalizeInput(array $input): array
    {
        $usernameField = Fortify::username();
        $username = $input[$usernameField] ?? null;

        if (is_string($username)) {
            $input[$usernameField] = EmailAddress::normalize($username);
        }

        return $input;
    }
}
