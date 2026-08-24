<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Dọn thông báo cũ
    |--------------------------------------------------------------------------
    |
    | Bảng `notifications` chỉ có lớn lên: hai trăm người × vài thông báo mỗi
    | ngày × nhiều năm. Không ai đọc lại thông báo của quý trước, nhưng chúng
    | vẫn nằm đó làm chậm mọi truy vấn chạm tới bảng.
    |
    | Hai mốc khác nhau vì hai loại rủi ro khác nhau:
    |
    |   - Đã đọc: xoá sớm được, người dùng đã xem rồi.
    |   - Chưa đọc: giữ lâu hơn. Xoá một thông báo chưa ai đọc là xoá thứ có
    |     thể còn cần — người nghỉ thai sản sáu tháng về vẫn nên thấy mình đã
    |     bỏ lỡ gì.
    |
    | Đây KHÔNG phải nhật ký kiểm toán. Nhật ký nhân sự (`user_activities`) và
    | nhật ký lương (`payroll_audits`) nằm ở bảng riêng và không bị dọn.
    |
    */

    'prune' => [

        'read_after_days' => (int) env('NOTIFICATIONS_PRUNE_READ_DAYS', 90),

        'unread_after_days' => (int) env('NOTIFICATIONS_PRUNE_UNREAD_DAYS', 365),

    ],

];
