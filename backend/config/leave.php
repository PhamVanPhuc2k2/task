<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Khoảng ngày nộp đơn được
    |--------------------------------------------------------------------------
    |
    | Tính từ hôm nay theo giờ Việt Nam.
    |
    | Phía QUÁ KHỨ mở khá rộng, có chủ ý: nghỉ ốm đột xuất thường được khai sau
    | khi đã nghỉ, và đó chính là trường hợp cần miễn chấm công nhất. Chặn quá
    | chặt thì người ta nhắn quản lý qua Zalo — tức là đẩy việc ra khỏi hệ thống,
    | đúng thứ tính năng này sinh ra để gom vào.
    |
    | Nhưng vẫn phải có mốc: không có thì nộp được đơn nghỉ cho năm 2020, và
    | bảng công của một kỳ đã chốt đổi nghĩa.
    |
    */

    'backdate_days' => (int) env('LEAVE_BACKDATE_DAYS', 90),

    'future_days' => (int) env('LEAVE_FUTURE_DAYS', 365),

    /*
    |--------------------------------------------------------------------------
    | Độ dài tối đa một đơn
    |--------------------------------------------------------------------------
    |
    | Chặn lỗi gõ nhầm năm — "từ 12/08/2026 đến 12/08/2027" là một đơn nghỉ 366
    | ngày, và nếu duyệt nhầm thì cả năm đó miễn chấm công.
    |
    */

    'max_days_per_request' => (int) env('LEAVE_MAX_DAYS', 60),

];
