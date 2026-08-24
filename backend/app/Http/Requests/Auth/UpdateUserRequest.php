<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sửa hồ sơ nhân viên đã có.
 *
 * Gần giống `StoreUserRequest`, nhưng khác ở đúng hai chỗ mà nếu chép nguyên
 * sang thì hỏng:
 *
 *   1. `unique` phải **bỏ qua chính bản ghi đang sửa**. Không có `ignore()` thì
 *      bấm Lưu mà không đổi email cũng báo "email đã tồn tại" — email của
 *      chính người đó.
 *   2. `manager_id` không được là chính mình. Đây chỉ là lớp chặn đầu tiên cho
 *      thông báo lỗi đẹp; vòng lặp dài hơn (A→B→A) do `UpdateUserAction` bắt,
 *      vì kiểm tra đó phải đi ngược cả chuỗi quản lý.
 */
final class UpdateUserRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $id = $user instanceof User ? $user->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($id),
            ],
            'employee_code' => [
                'required', 'string', 'max:50',
                Rule::unique('users', 'employee_code')->ignore($id),
            ],
            'role' => ['required', Rule::enum(Role::class)],
            'phone' => ['nullable', 'string', 'max:20'],
            'joined_at' => ['nullable', 'date'],

            // Nhận uuid chứ không nhận id tuần tự — xem README "Quy ước dữ liệu".
            'department_id' => ['nullable', 'uuid', Rule::exists('departments', 'uuid')],
            'position_id' => ['nullable', 'uuid', Rule::exists('positions', 'uuid')],
            'manager_id' => [
                'nullable', 'uuid',
                Rule::exists('users', 'uuid'),
                Rule::notIn([$user instanceof User ? $user->uuid : null]),
            ],
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'manager_id.not_in' => 'Một người không thể là quản lý trực tiếp của chính mình.',
        ];
    }
}
