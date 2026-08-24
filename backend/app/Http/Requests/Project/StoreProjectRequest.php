<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use App\Domain\Task\Enums\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreProjectRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Mã dự án là thứ mọi người gõ trong báo cáo và tên nhánh git, nên
            // phải duy nhất kể cả với dự án đã xoá mềm — trùng mã là tra cứu
            // sau này ra hai kết quả khác nhau.
            'code' => ['nullable', 'string', 'max:50', Rule::unique('projects', 'code')],
            'description' => ['nullable', 'string', 'max:20000'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],

            // Nhận uuid chứ không nhận id tuần tự.
            'owner_id' => ['nullable', 'uuid', Rule::exists('users', 'uuid')],
            'department_id' => ['nullable', 'uuid', Rule::exists('departments', 'uuid')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'tên dự án',
            'code' => 'mã dự án',
            'description' => 'mô tả',
            'status' => 'trạng thái',
            'start_date' => 'ngày bắt đầu',
            'end_date' => 'ngày kết thúc',
            'owner_id' => 'người phụ trách',
            'department_id' => 'phòng ban',
        ];
    }
}
