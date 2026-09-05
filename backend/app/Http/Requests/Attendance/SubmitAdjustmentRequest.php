<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use App\Domain\Attendance\Enums\RequestStatus;
use App\Support\Time\WorkDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;

/**
 * Nộp đơn giải trình cho một ngày công.
 *
 * ## Không có khoảng ngày như đơn nghỉ
 *
 * `LeaveWindow` trả lời câu "được xin nghỉ trước bao lâu, khai lùi bao xa" —
 * câu hỏi của một đơn hướng về tương lai. Giải trình hướng về quá khứ và chỉ có
 * hai cận:
 *
 *   - **Không quá hôm nay.** Giải trình một ngày chưa xảy ra là vô nghĩa, và
 *     cho qua thì sinh ra những đơn được duyệt trước khi có gì để giải trình.
 *   - **Không trước ngày vào làm.** Ngày trước đó không phải ngày công của
 *     người này.
 *
 * Cận dưới thật sự là **chốt sổ kỳ công**, và nó được kiểm ở controller chứ
 * không ở đây: nó là luật bắc qua nhiều miền, và câu lỗi của nó nói ra kỳ nào.
 *
 * ## `today` phải là hôm nay theo GIỜ VIỆT NAM
 *
 * Luật `before_or_equal:today` của Laravel so theo múi giờ ứng dụng (UTC). Từ
 * 00:00 tới 07:00 giờ Việt Nam mỗi ngày, `today` của nó vẫn đang ở hôm trước —
 * nên người giải trình ngày hôm qua lúc 1h sáng sẽ bị từ chối nhầm. Cùng cái
 * bẫy đã ghi ở `ClosePeriodAction`.
 */
final class SubmitAdjustmentRequest extends FormRequest
{
    /** Một ngày không thể có quá 24 giờ công. */
    private const int PHUT_TOI_DA = 1440;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'work_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:'.WorkDate::from(Date::now()),
                'after_or_equal:'.$this->ngayVaoLam(),

                /*
                | Một ngày chỉ có một đơn còn hiệu lực.
                |
                | Lớp này bắt trường hợp thường gặp và cho câu lỗi hiện đúng
                | cạnh ô nhập. Luật THẬT nằm trong `SubmitAdjustmentAction`, có
                | khoá dòng — chỗ này không chặn được hai request song song.
                */
                Rule::unique('attendance_adjustments', 'work_date')
                    ->where('user_id', $this->user()?->id)
                    ->whereIn('status', [
                        RequestStatus::Pending->value,
                        RequestStatus::Approved->value,
                    ]),
            ],

            /*
            | Số phút đề nghị — KHÔNG bắt buộc, và đó là điểm chính.
            |
            | Người đi gặp khách cả ngày không đếm phút. Bắt nhập thì họ điền
            | một con số bịa cho xong, và người duyệt mất luôn tín hiệu "người
            | này không khẳng định con số nào".
            */
            'requested_minutes' => ['nullable', 'integer', 'min:1', 'max:'.self::PHUT_TOI_DA],

            // Tối thiểu 10 ký tự, cùng lý do với đơn nghỉ: không có mức sàn thì
            // trường này đầy những dòng "bận" và "đi công tác".
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'work_date' => 'ngày công',
            'requested_minutes' => 'số phút đề nghị',
            'reason' => 'lý do',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'work_date.before_or_equal' => 'Chưa giải trình được cho một ngày chưa xảy ra.',
            'work_date.after_or_equal' => 'Ngày này trước ngày bạn vào làm.',
            'work_date.unique' => 'Bạn đã có đơn giải trình còn hiệu lực cho ngày này.',
            'requested_minutes.max' => 'Một ngày không có quá 24 giờ công.',
            'reason.min' => 'Viết rõ hơn một chút — quản lý cần đủ thông tin để quyết định.',
        ];
    }

    /**
     * Ngày vào làm, dùng làm cận dưới.
     *
     * Người chưa có `joined_at` thì không chặn — lùi về một mốc quá khứ xa thay
     * vì trả `null`, vì `after_or_equal:` với chuỗi rỗng sẽ mất hiệu lực im
     * lặng thay vì báo lỗi.
     */
    private function ngayVaoLam(): string
    {
        $ngay = $this->user()?->joined_at;

        return $ngay === null ? '1970-01-01' : WorkDate::from($ngay);
    }
}
