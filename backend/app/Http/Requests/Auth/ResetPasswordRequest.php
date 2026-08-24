<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class ResetPasswordRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],

            /*
            | `Password::defaults()` — cùng chính sách với mọi chỗ khác trong hệ
            | thống: tối thiểu 12 ký tự, có chữ và số, và đối chiếu với danh
            | sách mật khẩu đã lộ của HaveIBeenPwned.
            |
            | Viết lại luật riêng ở đây là cách chắc chắn để đường đặt lại mật
            | khẩu trở thành cửa sau: người dùng đặt được mật khẩu yếu ở đây
            | trong khi đường đổi mật khẩu thông thường vẫn chặn.
            */
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'password' => 'mật khẩu mới',
            'email' => 'email',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ];
    }
}
