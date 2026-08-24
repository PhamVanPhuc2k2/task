<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Nhắc nộp báo cáo cuối ngày
    |--------------------------------------------------------------------------
    |
    | Giờ nhắc tính theo **giờ Việt Nam** (`app.display_timezone`), không phải
    | giờ máy chủ. Ứng dụng chạy UTC theo quy ước dữ liệu, nên quên khai múi giờ
    | ở lịch chạy nền là "17:30" thành 00:30 sáng hôm sau.
    |
    | 17h30 chứ không phải 18h00: nhắc lúc hết giờ làm thì người ta đã đóng máy.
    | Cần một khoảng đủ để làm xong việc đang dở rồi mới viết báo cáo.
    |
    */

    'reminder' => [

        'enabled' => (bool) env('REPORT_REMINDER_ENABLED', true),

        'at' => (string) env('REPORT_REMINDER_AT', '17:30'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Nộp bù được bao nhiêu ngày
    |--------------------------------------------------------------------------
    |
    | Tính từ **hôm nay theo giờ Việt Nam**. `2` nghĩa là hôm nay và hai ngày
    | liền trước. Ngày tương lai luôn bị chặn, không có tuỳ chọn nào mở.
    |
    | Con số này là chính sách, không phải chi tiết kỹ thuật. Để 0 thì ốm một
    | hôm là mất luôn, và người ta sẽ nhắn admin xin ngoại lệ — tức là chuyển
    | công việc từ hệ thống sang hộp thư của admin. Để rộng cả tháng thì nộp bù
    | hàng loạt sát kỳ đánh giá, và cột đối chiếu ở trang Chấm công mất hết ý
    | nghĩa.
    |
    */

    'backfill_days' => (int) env('REPORT_BACKFILL_DAYS', 2),

];
