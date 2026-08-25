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
    | Lưu ý về `weekly_rest_days` ngay bên dưới: nó vẫn KHÔNG dùng để tính giờ
    | công. Làm cuối tuần vẫn được tính đủ, và người làm ngoài ca vẫn được tính
    | đủ số phút. Ca chuẩn chỉ sinh ra thêm một thông tin — "hôm nay đến muộn
    | bao nhiêu" — chứ không cắt của ai phút nào.
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
    ],

    /*
    |--------------------------------------------------------------------------
    | Ngày nghỉ hằng tuần
    |--------------------------------------------------------------------------
    |
    | Dùng để tính nghỉ bù theo khoản 3 Điều 112 Bộ luật Lao động: ngày lễ trùng
    | ngày nghỉ hằng tuần thì nghỉ bù vào ngày làm việc kế tiếp.
    |
    | Theo cách đánh số của Carbon: 0 = Chủ nhật, 6 = Thứ bảy. Mặc định nghỉ cả
    | thứ bảy và chủ nhật — đúng với phần lớn văn phòng hiện nay. Công ty làm 6
    | ngày một tuần thì đổi thành `[0]`.
    |
    | KHÔNG dùng danh sách này để tính giờ công. Công ty làm remote với giờ giấc
    | linh hoạt, nên "làm cuối tuần" là chuyện bình thường và vẫn được tính đủ.
    | Đây chỉ là mốc pháp lý cho việc nghỉ bù.
    |
    */

    'weekly_rest_days' => [0, 6],

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

];
