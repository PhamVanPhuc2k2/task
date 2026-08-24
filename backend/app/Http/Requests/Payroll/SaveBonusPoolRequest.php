<?php

declare(strict_types=1);

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

final class SaveBonusPoolRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // `decimal:0,2` chứ không `numeric` — xem SetSalaryRequest. `min:0`
            // là lớp chặn số âm thứ nhất trong ba lớp; hai lớp còn lại ở Action
            // và ở ràng buộc CHECK của database.
            'total_amount' => ['required', 'decimal:0,2', 'min:0', 'max:100000000000'],

            'condition_note' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'total_amount' => 'tổng quỹ',
            'condition_note' => 'điều kiện mở quỹ',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'condition_note.required' => 'Ghi rõ điều kiện mở quỹ — ví dụ "dự án nghiệm thu đúng hạn và khách hàng thanh toán đủ".',
        ];
    }
}
