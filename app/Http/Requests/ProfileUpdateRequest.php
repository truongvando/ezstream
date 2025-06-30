<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'telegram_bot_token' => ['nullable', 'string', 'max:255', 'regex:/^\d+:[A-Za-z0-9_-]+$/'],
            'telegram_chat_id' => ['nullable', 'numeric', 'digits_between:1,20'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'telegram_bot_token.regex' => 'Bot Token không đúng định dạng. Ví dụ: 123456789:ABCDEFghijklmnop',
            'telegram_chat_id.numeric' => 'Chat ID phải là số.',
            'telegram_chat_id.digits_between' => 'Chat ID phải có từ 1 đến 20 chữ số.',
        ];
    }
}
