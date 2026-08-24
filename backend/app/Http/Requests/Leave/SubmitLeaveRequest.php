<?php

declare(strict_types=1);

namespace App\Http\Requests\Leave;

use App\Domain\Leave\Data\LeaveWindow;
use App\Domain\Leave\Enums\LeaveType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SubmitLeaveRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /*
        | Khoảng ngày cho phép. Luật THẬT nằm ở SubmitLeaveRequestAction — chỗ
        | này chỉ để câu báo lỗi hiện đúng cạnh ô nhập thay vì rơi xuống dải lỗi
        | chung. Cả hai đọc từ LeaveWindow nên không có đường nào lệch nhau.
        */
        $khoang = LeaveWindow::current();

        return [
            'type' => ['required', Rule::enum(LeaveType::class)],

            'start_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.$khoang->earliest,
                'before_or_equal:'.$khoang->latest,
            ],

            // `after_or_equal:start_date` là ràng buộc quan trọng nhất ở đây.
            // Đơn "từ 20/08 đến 15/08" làm mọi phép so sánh khoảng trả về rỗng
            // — ngày nghỉ đơn giản là không bao giờ khớp, và không có gì báo.
            // Database cũng có CHECK cho việc này; hai lớp vì nó hỏng im lặng.
            'end_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:start_date',
                'before_or_equal:'.$khoang->latest,
            ],

            /*
            | Lý do bắt buộc, tối thiểu 10 ký tự.
            |
            | Cùng lý do với lý do duyệt ngày công: không có mức sàn thì trường
            | này đầy những dòng "bận" và "việc riêng" — vẫn không ai trả lời
            | được câu "vì sao", mà lại tưởng là đã ghi.
            */
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'loại nghỉ',
            'start_date' => 'ngày bắt đầu',
            'end_date' => 'ngày kết thúc',
            'reason' => 'lý do',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $khoang = LeaveWindow::current();

        return [
            'start_date.after_or_equal' => $khoang->message(),
            'start_date.before_or_equal' => $khoang->message(),
            'end_date.before_or_equal' => $khoang->message(),
            'end_date.after_or_equal' => 'Ngày kết thúc phải từ ngày bắt đầu trở đi.',
            'reason.min' => 'Viết rõ hơn một chút — quản lý cần đủ thông tin để quyết định.',
        ];
    }
}
