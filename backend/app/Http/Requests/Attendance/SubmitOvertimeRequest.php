<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use App\Domain\Leave\Data\LeaveWindow;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Đăng ký làm thêm giờ cho một khoảng giờ trong một ngày.
 *
 * ## Dùng chung khoảng ngày với đơn nghỉ
 *
 * Cùng một câu hỏi — *"được khai lùi bao xa, đăng ký trước bao lâu"* — nên hai
 * chỗ trả lời khác nhau là sai. Cùng lý do đã ghi ở `SubmitLateArrivalRequest`.
 *
 * Phía quá khứ vẫn mở: người ta hay ở lại làm tối rồi hôm sau mới nhớ ra phải
 * đăng ký. Chặn cứng thì họ đi nhắn quản lý qua Zalo — đúng thứ tính năng này
 * sinh ra để gom vào. Nhưng đơn khai lùi vẫn phải qua tay người duyệt, và kỳ đã
 * chốt thì controller chặn.
 *
 * ## Ba luật NGHIỆP VỤ không nằm ở đây
 *
 * Giờ phải ngoài ca, không chồng lấn, và ba trần của Điều 107 — cả ba nằm trong
 * `SubmitOvertimeAction`, có khoá dòng. Chỗ này chỉ kiểm hình dạng dữ liệu và
 * thứ tự hai mốc giờ.
 */
final class SubmitOvertimeRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $khoang = LeaveWindow::current();

        return [
            'work_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.$khoang->earliest,
                'before_or_equal:'.$khoang->latest,
            ],

            'start_time' => ['required', 'date_format:H:i'],

            /*
            | Giờ kết thúc phải SAU giờ bắt đầu, trong cùng một ngày.
            |
            | Ca làm thêm vắt qua nửa đêm chưa hỗ trợ: nó cần cả phụ cấp làm
            | đêm (Điều 98 khoản 2) lẫn quy tắc chia phần cho hai ngày công, mà
            | công ty hiện không có ca đêm. Chặn thẳng ở đây còn hơn nhận một
            | đơn ra số phút âm rồi không ai hiểu con số đó từ đâu.
            */
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],

            // Tối thiểu 10 ký tự, cùng lý do với các loại đơn khác: không có
            // mức sàn thì trường này đầy những dòng "làm nốt việc".
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'work_date' => 'ngày',
            'start_time' => 'giờ bắt đầu',
            'end_time' => 'giờ kết thúc',
            'reason' => 'lý do',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'work_date.after_or_equal' => LeaveWindow::current()->message(),
            'work_date.before_or_equal' => LeaveWindow::current()->message(),
            'end_time.after' => 'Giờ kết thúc phải sau giờ bắt đầu. Ca làm thêm vắt qua nửa đêm chưa hỗ trợ.',
            'reason.min' => 'Viết rõ hơn một chút — quản lý cần đủ thông tin để quyết định.',
        ];
    }
}
