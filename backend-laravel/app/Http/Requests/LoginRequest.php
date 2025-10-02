<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="LoginRequest",
 *     type="object",
 *     required={"email","password"},
 *     @OA\Property(property="email", type="string", example="user@example.com"),
 *     @OA\Property(property="password", type="string", example="password123")
 * )
 */
class LoginRequest extends FormRequest
{
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email обязателен',
            'email.email' => 'Неверный формат email',
            'password.required' => 'Пароль обязателен',
            'password.min' => 'Пароль должен быть минимум 6 символов',
        ];
    }
}
