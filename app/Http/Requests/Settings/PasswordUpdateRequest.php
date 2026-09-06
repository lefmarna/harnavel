<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PasswordUpdateRequest extends FormRequest
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

        return [
            // Google認証のみのユーザーはパスワードがないので current_password をスキップ
            ...($user !== null && $user->password === null ? [] : [
                'current_password' => $this->currentPasswordRules(),
            ]),
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => __('form.password_update.current_password_required'),
            'current_password.string' => __('form.password_update.current_password_string'),
            'current_password.current_password' => __('form.password_update.current_password_invalid'),
            'password.required' => __('form.password_update.password_required'),
            'password.string' => __('form.password_update.password_string'),
            'password.confirmed' => __('form.password_update.password_confirmed'),
        ];
    }

    /**
     * この画面だけ password の意味が「新しいパスワード」に変わるので上書きする。
     * current_password と password_confirmation は validation.attributes の既定と同じ。
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'password' => __('form.password_update.new_password'),
        ];
    }
}
