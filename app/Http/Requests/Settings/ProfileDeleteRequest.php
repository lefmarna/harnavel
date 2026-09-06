<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();

        // Google認証のみのユーザーはパスワードがないのでスキップ
        if ($user === null || $user->password === null) {
            return [];
        }

        return ['password' => $this->currentPasswordRules()];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => __('form.profile_delete.password_required'),
            'password.string' => __('form.profile_delete.password_string'),
            'password.current_password' => __('form.profile_delete.password_invalid'),
        ];
    }
}
