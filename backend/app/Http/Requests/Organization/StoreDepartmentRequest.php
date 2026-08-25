<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreDepartmentRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            // `code` để trống được, nhưng đã nhập thì phải duy nhất — nó là
            // khoá mà `users:import` dùng để nối nhân sự vào phòng ban, và là
            // khoá mà OrganizationSeeder dùng để chạy lại không tạo trùng.
            'code' => ['nullable', 'string', 'max:50', Rule::unique('departments', 'code')],
            'description' => ['nullable', 'string', 'max:1000'],

            // Nhận uuid chứ không nhận id tuần tự — xem README "Quy ước dữ liệu".
            'parent_id' => ['nullable', 'uuid', Rule::exists('departments', 'uuid')],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'tên phòng ban',
            'code' => 'mã phòng ban',
            'description' => 'mô tả',
            'parent_id' => 'phòng ban cấp trên',
            'is_active' => 'đang hoạt động',
        ];
    }
}
