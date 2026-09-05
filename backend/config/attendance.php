<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Ngưỡng "có thực sự làm việc trên hệ thống"
    |--------------------------------------------------------------------------
    |
    | Dùng để đối chiếu giờ công với báo cáo ngày. Dưới ngưỡng này thì coi như
    | ngày đó người ta không làm việc *trên hệ thống* — có thể vẫn làm việc
    | thật ở nơi khác, nên đây KHÔNG phải mốc để kết luận ai lười.
    |
    | 60 phút chứ không phải 1 phút: mở ứng dụng xem thông báo rồi tắt cũng sinh
    | ra một phiên. Lấy mốc quá thấp thì mọi người đều "có làm" và cột đối chiếu
    | mất hết ý nghĩa.
    |
    | Ép kiểu int vì env() trả về chuỗi, và Config::integer() ném lỗi khi gặp
    | chuỗi.
    |
    */

    'min_worked_minutes' => (int) env('ATTENDANCE_MIN_WORKED_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Ca làm chuẩn
    |--------------------------------------------------------------------------
    |
    | 8h15–12h sáng, 13h30–17h30 chiều — công ty đã chốt. Giờ ghi ở đây là GIỜ
    | VIỆT NAM, còn `work_sessions.started_at` lưu UTC; mọi so sánh phải đi qua
    | App\Domain\Attendance\Data\WorkShift chứ đừng so chuỗi thẳng.
    |
    | Đây là ca của NGÀY LÀM CẢ NGÀY. Ngày nửa buổi dùng chung `morning_start`
    | nhưng tan ở `half_end`; xem `work_days_half` bên dưới.
    |
    | Ca chuẩn KHÔNG cắt giờ của ai. Làm cuối tuần vẫn được tính đủ, người làm
    | ngoài ca vẫn được tính đủ số phút. Nó chỉ sinh thêm một thông tin — "hôm
    | nay đến muộn bao nhiêu" — và chỉ sinh vào những ngày có ca.
    |
    | `grace_minutes` = 0 theo đúng quyết định của công ty. Để sẵn thành cấu
    | hình vì đây là loại chính sách hay đổi.
    |
    */

    'shift' => [
        'morning_start' => env('ATTENDANCE_SHIFT_MORNING_START', '08:15'),
        'lunch_start' => env('ATTENDANCE_SHIFT_LUNCH_START', '12:00'),
        'lunch_end' => env('ATTENDANCE_SHIFT_LUNCH_END', '13:30'),
        'end' => env('ATTENDANCE_SHIFT_END', '17:30'),
        'grace_minutes' => (int) env('ATTENDANCE_SHIFT_GRACE_MINUTES', 0),

        /*
        | Giờ tan của NGÀY NỬA BUỔI (mặc định thứ bảy).
        |
        | Ngày nửa buổi không có nghỉ trưa — tan trước giờ đó rồi. Giờ vào
        | làm dùng chung `morning_start`: công ty đổi giờ vào làm thì đổi
        | cho cả tuần, không ai muốn nhớ hai mốc khác nhau.
        */
        'half_end' => env('ATTENDANCE_SHIFT_HALF_END', '12:00'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Lịch làm việc trong tuần
    |--------------------------------------------------------------------------
    |
    | Theo cách đánh số của Carbon: 0 = Chủ nhật … 6 = Thứ bảy. Ngày không nằm
    | trong danh sách nào là ngày nghỉ.
    |
    | Chuỗi phân tách bằng dấu phẩy chứ không phải mảng PHP, vì hai giá trị này
    | là **cài đặt giám đốc sửa được trên giao diện** — mà bảng `site_settings`
    | lưu key/value dạng chuỗi. Một chỗ khai duy nhất, không có bản mảng song
    | song để lệch nhau. Việc phân tách nằm ở App\Domain\Attendance\Data\WorkWeek.
    |
    | Công ty chốt tháng 9/2026: thứ hai tới thứ sáu làm cả ngày, thứ bảy làm
    | buổi sáng, chủ nhật nghỉ.
    |
    | Danh sách này KHÔNG cắt giờ của ai. Người làm chủ nhật vẫn được tính đủ số
    | phút — công ty làm remote với giờ giấc linh hoạt. Nó chỉ quyết định ba
    | thứ: hôm đó có tính đi muộn không, có nhắc nộp báo cáo không, và ngày lễ
    | trùng ngày nghỉ thì nghỉ bù vào đâu (khoản 3 Điều 112).
    |
    */

    'work_days_full' => (string) env('ATTENDANCE_WORK_DAYS_FULL', '1,2,3,4,5'),
    'work_days_half' => (string) env('ATTENDANCE_WORK_DAYS_HALF', '6'),

    /*
    |--------------------------------------------------------------------------
    | Trần giờ công tự động của NGÀY NỬA BUỔI
    |--------------------------------------------------------------------------
    |
    | Tách riêng khỏi trần ngày thường, và đây không phải chuyện làm cho đẹp:
    | trần 600 phút áp lên một buổi sáng 225 phút nghĩa là cái tab quên đóng
    | chiều thứ bảy vẫn ghi thẳng 10 tiếng công. Vài lần như vậy là không ai còn
    | tin bảng công — mà mất niềm tin thì cả hệ thống chấm công thành vô dụng.
    |
    | 360 phút = 6 tiếng: rộng hơn ca 225 phút một quãng đủ để ai làm thêm buổi
    | chiều thứ bảy vẫn được ghi nhận, mà vẫn chặn được tab bỏ quên qua đêm.
    |
    */

    'max_daily_minutes_half' => (int) env('ATTENDANCE_MAX_DAILY_MINUTES_HALF', 360),

    /*
    |--------------------------------------------------------------------------
    | Trần giờ công tự động mỗi ngày
    |--------------------------------------------------------------------------
    |
    | Từ khi chấm công tính theo SỰ CÓ MẶT (mở tab là tính, không cần thao tác),
    | một cái tab quên đóng qua đêm sẽ ghi thẳng 16 tiếng công. Chỉ cần vài lần
    | như vậy là không ai còn tin bảng công nữa — và mất niềm tin thì cả hệ
    | thống chấm công thành vô dụng, chứ không chỉ sai vài con số.
    |
    | Chạm trần thì nhịp tim ngừng được ghi. Ai làm thật quá mức này vẫn khai
    | được bằng tay, kèm lý do và người duyệt — đúng chỗ mà một ngày công bất
    | thường nên đi qua.
    |
    | 600 phút = 10 tiếng: rộng hơn ca chuẩn 465 phút một quãng đủ để tăng ca
    | thật mà vẫn chặn được tab bỏ quên. Giám đốc đổi được trong Cài đặt trang.
    |
    */

    'max_daily_minutes' => (int) env('ATTENDANCE_MAX_DAILY_MINUTES', 600),

    /*
    |--------------------------------------------------------------------------
    | Làm thêm giờ
    |--------------------------------------------------------------------------
    |
    | Hệ số tiền lương làm thêm giờ, Điều 98 Bộ luật Lao động 2019:
    |
    |   - Ngày làm việc bình thường: ít nhất 150%
    |   - Ngày nghỉ hằng tuần:       ít nhất 200%
    |   - Ngày nghỉ lễ, tết:         ít nhất 300%
    |
    | Ghi bằng PHẦN TRĂM NGUYÊN chứ không phải hệ số thập phân, và đó là quyết
    | định có chủ ý: người ta nói "hệ số 150%" chứ không nói "1,5", ô nhập ở màn
    | Cài đặt nhận số nguyên, và cả module này không còn phép nhân số thực nào.
    |
    | Đây là MỨC SÀN theo luật. Công ty trả cao hơn thì đổi ở Cài đặt; đặt thấp
    | hơn là trái luật, nên màn hình chặn xuống dưới mức sàn.
    |
    | CHƯA TÍNH phụ cấp làm đêm (Điều 98 khoản 2 và 3: thêm 30% cho giờ làm ban
    | đêm, và thêm 20% nữa nếu là làm thêm giờ vào ban đêm). Công ty hiện không
    | có ca đêm; khi có thì đây là chỗ thêm, và `rate_percent` trên từng đơn đã
    | lưu sẵn con số của lúc duyệt nên dữ liệu cũ không đổi nghĩa.
    |
    */

    'overtime' => [
        'rate_working_percent' => (int) env('OVERTIME_RATE_WORKING', 150),
        'rate_weekly_rest_percent' => (int) env('OVERTIME_RATE_WEEKLY_REST', 200),
        'rate_holiday_percent' => (int) env('OVERTIME_RATE_HOLIDAY', 300),

        /*
        | Trần số giờ làm thêm, Điều 107 Bộ luật Lao động 2019:
        |
        |   - Không quá 50% số giờ làm việc bình thường trong 1 ngày
        |   - Không quá 40 giờ trong 1 tháng
        |   - Không quá 200 giờ trong 1 năm (một số ngành nghề được 300 giờ)
        |
        | Đếm theo PHÚT vì đơn đăng ký theo mốc giờ, và quy về giờ để hiển thị.
        | Đếm cả đơn đang chờ duyệt: chỉ đếm đơn đã duyệt thì nộp năm đơn nhỏ
        | cùng lúc là lách được, mỗi đơn nhìn riêng đều nằm trong trần.
        |
        | Đặt 0 để tắt một trần.
        */
        'max_minutes_per_day' => (int) env('OVERTIME_MAX_MINUTES_DAY', 240),
        'max_minutes_per_month' => (int) env('OVERTIME_MAX_MINUTES_MONTH', 2400),
        'max_minutes_per_year' => (int) env('OVERTIME_MAX_MINUTES_YEAR', 12000),
    ],

];
