<?php

namespace App\Http\Requests;

use App\Enums\EventEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImageUploadStoreRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:5120'],
            'image_base64' => ['nullable', 'string'],
            'metadata' => ['nullable', 'string'],
            'event' => ['nullable', 'string', Rule::in(EventEnum::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'Файл изображения обязателен.',
            'image.image' => 'Файл должен быть изображением.',
            'image.mimes' => 'Допустимые форматы: jpeg, png, jpg, gif.',
            'image.max' => 'Максимальный размер: 5 МБ.',
        ];
    }
}
