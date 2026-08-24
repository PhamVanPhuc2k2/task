<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Domain\Identity\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreUserRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'employee_code' => ['required', 'string', 'max:50', Rule::unique('users', 'employee_code')],
            'role' => ['required', Rule::enum(Role::class)],
            'phone' => ['nullable', 'string', 'max:20'],
            'joined_at' => ['nullable', 'date'],

            // Nhận uuid chứ không nhận id tuần tự — xem README "Quy ước dữ liệu".
            'department_id' => ['nullable', 'uuid', Rule::exists('departments', 'uuid')],
            'position_id' => ['nullable', 'uuid', Rule::exists('positions', 'uuid')],
            'manager_id' => ['nullable', 'uuid', Rule::exists('users', 'uuid')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'họ tên',
            'email' => 'email',
            'employee_code' => 'mã nhân viên',
            'role' => 'vai trò',
            'department_id' => 'phòng ban',
            'position_id' => 'chức vụ',
            'manager_id' => 'quản lý trực tiếp',
        ];
    }
}
