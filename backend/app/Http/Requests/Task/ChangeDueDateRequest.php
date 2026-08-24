<?php

declare(strict_types=1);

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

final class ChangeDueDateRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'due_date' => ['nullable', 'date'],
            // Lý do là BẮT BUỘC. Đây là ràng buộc nghiệp vụ, không phải trường
            // cho có: dời hạn trong im lặng làm hỏng mọi chỉ số đúng hạn.
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Vui lòng nêu lý do dời hạn.',
            'reason.min' => 'Lý do dời hạn cần cụ thể hơn.',
        ];
    }
}
