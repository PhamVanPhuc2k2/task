<?php

declare(strict_types=1);

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

final class SetSalaryRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            /*
            | `decimal:0,2` chứ không `numeric`.
            |
            | `numeric` nhận cả `1.0e7` và `0x1A`, rồi ép sang float ở tầng dưới
            | — đúng cái cửa sai số mà kiểu DECIMAL sinh ra để đóng. Luật này ép
            | chuỗi phải là số thập phân tối đa hai chữ số sau dấu phẩy, giữ
            | nguyên dạng chuỗi tới lúc ghi vào database.
            |
            | Trần một tỉ đồng mỗi tháng: không phải để hạn chế, mà để chặn lỗi
            | gõ thừa số 0 — 12.000.000 thành 120.000.000 thì không có gì báo.
            */
            'base_salary' => ['required', 'decimal:0,2', 'min:0', 'max:1000000000'],
            'allowance' => ['nullable', 'decimal:0,2', 'min:0', 'max:1000000000'],

            'effective_from' => ['required', 'date_format:Y-m-d'],

            // Bắt buộc, tối thiểu 5 ký tự — cùng lý do với lý do duyệt ngày
            // công: không có mức sàn thì trường này đầy những dòng "ok".
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'base_salary' => 'lương cơ bản',
            'allowance' => 'phụ cấp',
            'effective_from' => 'ngày hiệu lực',
            'reason' => 'lý do',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Phải ghi lý do — sáu tháng sau sẽ có người hỏi vì sao mức lương này thay đổi.',
            'reason.min' => 'Lý do quá ngắn để người khác hiểu được.',
            'base_salary.max' => 'Số quá lớn. Kiểm tra lại xem có gõ thừa số 0 không.',
        ];
    }
}
