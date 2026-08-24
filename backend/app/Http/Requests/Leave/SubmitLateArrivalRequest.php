<?php

declare(strict_types=1);

namespace App\Http\Requests\Leave;

use App\Domain\Attendance\Data\WorkShift;
use App\Domain\Leave\Data\LeaveWindow;
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
        // Dùng chung khoảng ngày với đơn nghỉ: cùng một câu hỏi "được khai lùi
        // bao xa, khai trước bao lâu", nên hai chỗ trả lời khác nhau là sai.
        $khoang = LeaveWindow::current();

        return [
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
                'required',
                'date_format:H:i',
                'after:'.WorkShift::fromConfig()->morningStart,
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
}
