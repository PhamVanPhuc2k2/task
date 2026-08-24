<?php

declare(strict_types=1);

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCommentRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:20000'],
            // Nhận uuid chứ không nhận id tuần tự. Bình luận cha phải thuộc
            // đúng task này — kiểm ở controller vì cần biết task nào.
            'parent_id' => ['nullable', 'uuid', Rule::exists('task_comments', 'uuid')->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => 'nội dung',
            'parent_id' => 'bình luận được trả lời',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Vui lòng nhập nội dung bình luận.',
        ];
    }
}
