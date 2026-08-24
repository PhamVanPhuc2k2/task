<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use App\Domain\Report\Data\ReportWindow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveDailyReportRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /*
        | Khoảng ngày được nộp. Luật thật nằm ở SaveDailyReportAction — chỗ này
        | chỉ để câu báo lỗi hiện đúng cạnh ô nhập thay vì rơi xuống dải lỗi
        | chung. Cả hai đọc từ ReportWindow nên không có đường nào lệch nhau.
        |
        | `before_or_equal` tính theo ngày Việt Nam, không theo `today` của
        | Laravel: ứng dụng chạy UTC nên từ 00:00 tới 07:00 giờ Việt Nam mỗi
        | ngày, `today` vẫn còn là hôm qua.
        */
        $khoang = ReportWindow::current();

        return [
            'report_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.$khoang->earliest,
                'before_or_equal:'.$khoang->latest,
            ],

            /*
            | Tối thiểu 10 ký tự.
            |
            | Không có mức sàn thì trường này đầy những dòng "ok", "làm việc",
            | "như hôm qua" — và lúc đó báo cáo ngày trở thành nghi thức bấm nút
            | chứ không còn là thứ quản lý đọc được. Mười ký tự đủ thấp để không
            | làm phiền người viết thật, đủ cao để chặn bấm cho xong.
            */
            'content' => ['required', 'string', 'min:10', 'max:5000'],

            // Không bắt buộc: người họp cả ngày hoặc hỗ trợ đồng nghiệp vẫn nộp
            // được mà không gắn task nào. Bắt buộc phải có task là loại ràng
            // buộc khiến người ta bịa ra một task để nộp cho xong.
            'task_ids' => ['present', 'array', 'max:50'],
            'task_ids.*' => ['uuid', Rule::exists('tasks', 'uuid')],

            // `false` = lưu nháp, `true` = nộp.
            'submit' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'report_date' => 'ngày báo cáo',
            'content' => 'nội dung báo cáo',
            'task_ids' => 'danh sách công việc',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $khoang = ReportWindow::current();

        return [
            'content.min' => 'Viết rõ hơn một chút — vài chữ "ok" thì quản lý không đọc được gì.',
            // Cả hai luật ngày cùng nói một câu: người dùng chỉ cần biết khoảng
            // được phép, không cần biết mình vi phạm đầu nào của khoảng đó.
            'report_date.after_or_equal' => $khoang->message(),
            'report_date.before_or_equal' => $khoang->message(),
        ];
    }
}
