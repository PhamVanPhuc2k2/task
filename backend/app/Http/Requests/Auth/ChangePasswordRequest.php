<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class ChangePasswordRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // `current_password` là rule sẵn có của Laravel, đối chiếu với mật
            // khẩu của người đang đăng nhập.
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.current_password' => 'Mật khẩu hiện tại không đúng.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ];
    }
}
