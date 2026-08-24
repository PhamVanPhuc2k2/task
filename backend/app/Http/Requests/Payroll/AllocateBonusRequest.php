<?php

declare(strict_types=1);

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AllocateBonusRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // Mảng rỗng hợp lệ: xoá sạch phần chia để làm lại từ đầu là thao
            // tác bình thường lúc còn nháp.
            'allocations' => ['present', 'array', 'max:200'],

            'allocations.*.user_id' => ['required', 'uuid', Rule::exists('users', 'uuid')],

            /*
            | `min:0` — lớp chặn số âm thứ nhất.
            |
            | Đây không phải luật kỹ thuật mà là luật pháp lý: Điều 127 Bộ luật
            | Lao động 2019 cấm phạt tiền. Một số âm ở đây, dù gọi là "trừ
            | thưởng", về bản chất là khoản phạt trừ vào thu nhập.
            */
            'allocations.*.amount' => ['required', 'decimal:0,2', 'min:0', 'max:100000000000'],

            'allocations.*.reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'allocations.*.reason.required' => 'Mỗi phần chia phải có lý do — "vì sao người này được nhiều hơn người kia" là câu chắc chắn có người hỏi.',
            'allocations.*.reason.min' => 'Lý do quá ngắn để người khác hiểu được.',
            'allocations.*.amount.min' => 'Số tiền thưởng không được âm. Muốn giảm phần của ai thì đặt số nhỏ hơn, kể cả 0.',
        ];
    }
}
