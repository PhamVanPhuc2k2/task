<?php

declare(strict_types=1);

namespace App\Http\Requests\Leave;

use App\Domain\Attendance\Data\WorkShift;
use App\Domain\Attendance\Data\WorkWeek;
use App\Domain\Leave\Data\LeaveWindow;
use App\Domain\Leave\Enums\AttendanceExceptionType;
use App\Domain\Leave\Enums\LeaveStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SubmitLateArrivalRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        // Mặc định `late` để client cũ — chỉ gửi `expected_arrival` — vẫn chạy
        // y như trước. Thêm một trường bắt buộc vào API đang dùng là làm hỏng
        // mọi bản giao diện chưa kịp cập nhật.

        // Dùng chung khoảng ngày với đơn nghỉ: cùng một câu hỏi "được khai lùi
        // bao xa, khai trước bao lâu", nên hai chỗ trả lời khác nhau là sai.
        $khoang = LeaveWindow::current();

        return [
            'type' => ['sometimes', Rule::enum(AttendanceExceptionType::class)],

            'date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.$khoang->earliest,
                'before_or_equal:'.$khoang->latest,

                /*
                | Một ngày chỉ có một đơn còn hiệu lực.
                |
                | Đây là lớp bắt trường hợp thường gặp, cho câu lỗi hiện đúng
                | cạnh ô nhập. Luật THẬT nằm trong SubmitLateArrivalAction, có
                | khoá dòng — chỗ này không chặn được hai request chạy song
                | song.
                */
                Rule::unique('late_arrival_requests', 'date')
                    ->where('user_id', $this->user()?->id)
                    // Lọc theo LOẠI: một người xin đi muộn buổi sáng vẫn phải
                    // xin về sớm buổi chiều cùng ngày được.
                    ->where('type', $this->loai()->value)
                    ->whereIn('status', [
                        LeaveStatus::Pending->value,
                        LeaveStatus::Approved->value,
                    ]),
            ],

            /*
            | Giờ dự kiến phải MUỘN HƠN giờ vào làm.
            |
            | Xin "đi muộn" tới 8h00 trong khi ca bắt đầu 8h15 là không có
            | nghĩa. Cho qua thì sinh ra những đơn được duyệt mà chẳng miễn cái
            | gì, và người nộp tưởng mình đã xin phép xong.
            */
            'expected_arrival' => [
                'required_if:type,late',
                'exclude_unless:type,late',
                'date_format:H:i',
                'after:'.WorkShift::fromConfig()->morningStart,
            ],

            /*
            | Giờ dự kiến rời phải SỚM HƠN giờ tan ca của đúng ngày đó.
            |
            | Đọc ca theo ngày chứ không lấy 17:30 cứng: thứ bảy tan lúc 12:00,
            | nên xin "về sớm lúc 16h" vào thứ bảy là một đơn vô nghĩa được
            | duyệt mà chẳng miễn cái gì.
            */
            'expected_departure' => [
                'required_if:type,early',
                'exclude_unless:type,early',
                'date_format:H:i',
                'before:'.$this->gioTanCa(),
            ],

            // Tối thiểu 10 ký tự, cùng lý do với đơn nghỉ: không có mức sàn thì
            // trường này đầy những dòng "bận" và "việc riêng".
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date' => 'ngày',
            'expected_arrival' => 'giờ dự kiến đến',
            'reason' => 'lý do',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $ca = WorkShift::fromConfig();

        return [
            'date.after_or_equal' => LeaveWindow::current()->message(),
            'date.before_or_equal' => LeaveWindow::current()->message(),
            'date.unique' => 'Bạn đã có đơn xin đi muộn cho ngày này.',
            'expected_arrival.after' => sprintf(
                'Ca làm bắt đầu lúc %s — giờ dự kiến phải muộn hơn mốc đó.',
                $ca->morningStart,
            ),
            'reason.min' => 'Viết rõ hơn một chút — quản lý cần đủ thông tin để quyết định.',
        ];
    }

    /**
     * Điền sẵn `type = late` khi client không gửi.
     *
     * Bắt buộc chứ không phải cho gọn: luật `exclude_unless:type,late` **loại
     * bỏ** trường khi `type` vắng mặt, nên thiếu bước này thì đơn đi muộn của
     * client cũ đi qua mà không bị kiểm giờ nào cả — im lặng.
     */
    protected function prepareForValidation(): void
    {
        if (! is_string($this->input('type'))) {
            $this->merge(['type' => AttendanceExceptionType::Late->value]);
        }
    }

    /**
     * Giờ tan ca của ngày người dùng đang xin nghỉ sớm.
     *
     * Ngày nghỉ hoặc ngày chưa gửi thì lùi về ca ngày làm cả ngày — luật ngày
     * nghỉ không thuộc về ô nhập này, và trả `null` ở đây sẽ làm luật `before:`
     * mất hiệu lực im lặng.
     */
    /** Loại đơn đang nộp. Thiếu trường `type` thì coi như đi muộn. */
    private function loai(): AttendanceExceptionType
    {
        $tho = $this->input('type');

        return is_string($tho)
            ? (AttendanceExceptionType::tryFrom($tho) ?? AttendanceExceptionType::Late)
            : AttendanceExceptionType::Late;
    }

    private function gioTanCa(): string
    {
        $ngay = $this->input('date');

        if (! is_string($ngay) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $ngay) !== 1) {
            return WorkShift::fromConfig()->end;
        }

        $ca = WorkWeek::fromConfig()->shiftFor($ngay);

        return $ca === null ? WorkShift::fromConfig()->end : $ca->end;
    }
}
