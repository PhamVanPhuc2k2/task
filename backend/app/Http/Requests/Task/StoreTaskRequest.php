<?php

declare(strict_types=1);

namespace App\Http\Requests\Task;

use App\Domain\Task\Enums\ProjectStatus;
use App\Domain\Task\Enums\TaskPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTaskRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'due_date' => ['nullable', 'date'],
            // DECIMAL(6,2) — xem README "Quy ước dữ liệu, thời gian & tiền tệ".
            'estimate_hours' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],

            // Nhận uuid chứ không nhận id tuần tự.
            //
            // Chỉ nhận dự án còn mở: dự án đã hoàn thành hoặc đã huỷ mà vẫn
            // nhận việc mới thì mọi báo cáo tiến độ dự án đều sai.
            'project_id' => [
                'nullable', 'uuid',
                Rule::exists('projects', 'uuid')
                    ->whereNull('deleted_at')
                    ->whereIn('status', ProjectStatus::openValues()),
            ],
            'parent_task_id' => ['nullable', 'uuid', Rule::exists('tasks', 'uuid')],
            'assignee_id' => ['nullable', 'uuid', Rule::exists('users', 'uuid')],
            'reviewer_id' => ['nullable', 'uuid', Rule::exists('users', 'uuid')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Mặc định sẽ là "dự án không tồn tại", sai và khó hiểu: dự án có
            // tồn tại, chỉ là đã đóng.
            'project_id.exists' => 'Dự án không tồn tại hoặc đã đóng, không nhận thêm việc mới.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'tiêu đề',
            'description' => 'mô tả',
            'priority' => 'mức ưu tiên',
            'due_date' => 'hạn hoàn thành',
            'estimate_hours' => 'số giờ ước lượng',
            'assignee_id' => 'người thực hiện',
            'reviewer_id' => 'người duyệt',
            'project_id' => 'dự án',
        ];
    }
}
