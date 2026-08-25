<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Domain\Identity\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Giống `StoreDepartmentRequest` trừ hai chỗ mà chép nguyên sang thì hỏng:
 *
 *   1. `unique` phải BỎ QUA chính bản ghi đang sửa. Không có `ignore()` thì bấm
 *      Lưu mà không đổi mã cũng báo "mã phòng ban đã tồn tại" — mã của chính
 *      phòng ban đó.
 *   2. `parent_id` không được là chính nó. Đây chỉ là lớp chặn đầu tiên cho
 *      thông báo lỗi gắn đúng ô nhập; vòng dài hơn (A → B → A) do
 *      `UpdateDepartmentAction` bắt, vì kiểm tra đó phải duyệt cả cây.
 */
final class UpdateDepartmentRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $phongBan = $this->route('department');
        $id = $phongBan instanceof Department ? $phongBan->getKey() : null;
        $uuid = $phongBan instanceof Department ? $phongBan->uuid : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('departments', 'code')->ignore($id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id' => [
                'nullable', 'uuid',
                Rule::exists('departments', 'uuid'),
                Rule::notIn(array_filter([$uuid])),
            ],
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_id.not_in' => 'Một phòng ban không thể là cấp trên của chính nó.',
        ];
    }
}
