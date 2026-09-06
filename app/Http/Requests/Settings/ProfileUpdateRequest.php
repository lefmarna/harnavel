<?php

declare(strict_types=1);

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

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('form.profile_update.name_required'),
            'name.string' => __('form.profile_update.name_string'),
            'name.max' => __('form.profile_update.name_max'),
            'email.required' => __('form.profile_update.email_required'),
            'email.string' => __('form.profile_update.email_string'),
            'email.email' => __('form.profile_update.email_email'),
            'email.max' => __('form.profile_update.email_max'),
            'email.unique' => __('form.profile_update.email_unique'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('form.profile_update.name'),
            'email' => __('form.profile_update.email'),
        ];
    }
}
