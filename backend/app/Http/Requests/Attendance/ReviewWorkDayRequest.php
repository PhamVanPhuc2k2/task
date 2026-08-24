<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use App\Domain\Attendance\Enums\AttendanceDecision;
use App\Support\Time\WorkDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;

final class ReviewWorkDayRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            /*
            | Không duyệt được ngày chưa tới.
            |
            | Chỉ chặn phía tương lai: quản lý vẫn phải xử lý được bảng công
            | tháng trước, nên phía quá khứ để mở. Duyệt trước một ngày chưa xảy
            | ra thì con số giờ của ngày đó còn thay đổi sau khi đã có quyết
            | định, và bản ghi để lại một lý do viết cho việc chưa xảy ra.
            |
            | Mốc lấy theo **giờ Việt Nam** chứ không phải `today` của Laravel:
            | ứng dụng chạy UTC, nên từ 00:00 tới 07:00 giờ Việt Nam mỗi ngày,
            | `today` vẫn còn là hôm qua và quản lý đi làm sớm sẽ không duyệt
            | được ngày hôm nay.
            */
            'work_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:'.WorkDate::from(Date::now()),
            ],
            'decision' => ['required', Rule::enum(AttendanceDecision::class)],

            // Bắt buộc, và tối thiểu 5 ký tự. Không có mức tối thiểu thì trường
            // này sẽ đầy những dòng "ok" và "x" — tức là vẫn không ai trả lời
            // được câu hỏi vì sao, mà lại tưởng là đã ghi.
            'reason' => ['required', 'string', 'min:5', 'max:500'],

            // Tối đa 24 giờ. Chặn lỗi gõ thừa số 0 biến một ngày công thành
            // sáu mươi tiếng.
            'adjusted_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'work_date' => 'ngày công',
            'decision' => 'quyết định',
            'reason' => 'lý do',
            'adjusted_minutes' => 'số phút ấn định',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Phải ghi lý do — sáu tháng sau sẽ có người hỏi vì sao ngày này được xử lý như vậy.',
            'reason.min' => 'Lý do quá ngắn để người khác hiểu được.',
            'work_date.before_or_equal' => 'Không duyệt được ngày chưa tới.',
        ];
    }
}
