<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    protected function prepareForValidation(): void
    {
        if (! config('fortify.lowercase_usernames')) {
            return;
        }

        $username = $this->input(Fortify::username());
        if (! is_string($username)) {
            return;
        }

        $this->merge([
            Fortify::username() => Str::lower($username),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        if (! $user instanceof User) {
            abort(403);
        }

        return $this->profileRules($user->id);
    }
}
