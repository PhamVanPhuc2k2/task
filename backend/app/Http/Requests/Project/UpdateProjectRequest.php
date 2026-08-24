<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use App\Domain\Task\Enums\ProjectStatus;
use App\Domain\Task\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH sửa từng phần: chỉ kiểm tra những trường thực sự gửi lên.
 *
 * Dùng `sometimes` thay vì `required` — nếu không, sửa mỗi mô tả cũng bắt gửi
 * kèm tên dự án, và client nào quên là ghi đè mất dữ liệu.
 */
final class UpdateProjectRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $project = $this->route('project');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => [
                'sometimes', 'nullable', 'string', 'max:50',
                Rule::unique('projects', 'code')
                    ->ignore($project instanceof Project ? $project->getKey() : null),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'status' => ['sometimes', 'required', Rule::enum(ProjectStatus::class)],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],

            'owner_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('users', 'uuid')],
            'department_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('departments', 'uuid')],
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
