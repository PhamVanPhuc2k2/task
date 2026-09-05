<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Data\OvertimePolicy;
use App\Domain\Attendance\Data\WorkWeek;
use App\Support\Contracts\WorkCalendar;
use App\Support\Enums\DayKind;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Một ngày cụ thể thì làm thêm được hưởng hệ số nào, và ca hôm đó ra sao.
 *
 * ## Vì sao cần một đường riêng
 *
 * *"Tối nay là chủ nhật, 200%"* là thông tin quyết định người ta có nhận làm
 * hay không, và nó phải hiện **trước** khi họ đăng ký — chứ không phải sau khi
 * gửi xong.
 *
 * Giao diện KHÔNG tự tính được: hệ số phụ thuộc lịch tuần và bảng ngày lễ, mà
 * chép cả hai xuống trình duyệt là hai nơi cùng định nghĩa "ngày lễ" và chúng
 * sẽ lệch nhau ở lần nhân sự nhập thêm một ngày. Cùng lý do đã ghi ở thẻ quỹ
 * phép năm.
 *
 * Tách khỏi `/attendance/overtime/me` chứ không thêm tham số vào đó: màn hình
 * hỏi lại mỗi lần người dùng đổi ngày, và kéo về cả trăm đơn mỗi lần đổi ô
 * ngày là một cách rất tốn kém để lấy hai con số.
 *
 * Không cần quyền gì: đây là chính sách công ty, không phải dữ liệu của ai.
 */
final class OvertimePreviewController
{
    public function __invoke(Request $request, WorkCalendar $lich): JsonResponse
    {
        $request->validate(
            ['date' => ['required', 'date_format:Y-m-d']],
            ['date.date_format' => 'Ngày phải có dạng YYYY-MM-DD.'],
        );

        $ngay = (string) $request->string('date');
        $loai = $lich->kindOf($ngay);

        /*
        | Ca của hôm đó, để màn hình chặn ngay ở ô nhập giờ.
        |
        | `null` với ngày nghỉ và NGÀY LỄ — kể cả ngày lễ rơi vào thứ hai, vì
        | hôm đó không ai đi làm nên mọi mốc giờ đều là làm thêm. Đọc thẳng
        | `WorkWeek::shiftFor()` mà không kiểm loại ngày là đúng cái lỗi đã bắt
        | được ở `SubmitOvertimeAction`.
        */
        $ca = $loai === DayKind::Working ? WorkWeek::fromConfig()->shiftFor($ngay) : null;

        return new JsonResponse([
            'data' => [
                'work_date' => $ngay,
                'day_kind' => $loai->value,
                'day_kind_label' => $loai->label(),
                'rate_percent' => OvertimePolicy::fromConfig()->percentFor($loai),

                // Con số này còn đổi được cho tới lúc duyệt — giao diện nói
                // "dự kiến", cùng khuôn với `rate_is_final` trên từng đơn.
                'rate_is_final' => false,

                'shift' => $ca === null ? null : [
                    'start' => $ca->morningStart,
                    'end' => $ca->end,
                ],
            ],
        ]);
    }
}
