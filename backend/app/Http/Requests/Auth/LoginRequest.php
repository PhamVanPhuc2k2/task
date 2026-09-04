<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

final class LoginRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            // Ghi nhớ đăng nhập trên máy này. Không bắt buộc — thiếu thì coi
            // như không, tức là hành vi cũ.
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Khoá giới hạn số lần thử: theo cặp email + IP.
     *
     * Chỉ theo IP thì cả văn phòng dùng chung một IP sẽ khoá nhầm lẫn nhau.
     * Chỉ theo email thì kẻ dò có thể đổi IP để né. Ghép cả hai là cân bằng
     * hợp lý cho một hệ thống nội bộ.
     */
    public function throttleKey(): string
    {
        return 'login:'.Str::lower((string) $this->input('email')).'|'.$this->ip();
    }
}
