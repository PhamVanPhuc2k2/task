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

    /*
    |--------------------------------------------------------------------------
    | Hạn mức nghỉ KHÔNG LƯƠNG mỗi năm
    |--------------------------------------------------------------------------
    |
    | Chỉ áp cho loại `unpaid`. Phép năm, nghỉ ốm và việc riêng không đếm ở đây
    | — phép năm sẽ có quỹ riêng ở đợt 4, còn hai loại kia là chuyện của chính
    | sách công ty chứ không phải một con số chặn cứng.
    |
    | Đếm theo NĂM DƯƠNG LỊCH của từng ngày nghỉ, không theo ngày bắt đầu đơn:
    | một đơn từ 28/12 sang 03/01 phải chia phần cho đúng hai năm. Đếm cả đơn
    | ĐANG CHỜ DUYỆT — nếu không thì nộp năm đơn nhỏ cùng lúc là lách được, mỗi
    | đơn nhìn riêng đều nằm trong hạn mức.
    |
    | Đặt 0 để tắt hạn mức.
    |
    */

    'unpaid_max_days_per_year' => (int) env('LEAVE_UNPAID_MAX_DAYS_YEAR', 10),

    /*
    |--------------------------------------------------------------------------
    | Hạn mức số lần xin đi muộn mỗi tháng
    |--------------------------------------------------------------------------
    |
    | Đếm theo THÁNG chứ không theo năm, vì đây là chuyện lặt vặt lặp lại — hạn
    | mức năm thì người ta dùng hết từ tháng ba rồi cả năm còn lại không xin
    | được nữa, mà mục đích của con số này là điều chỉnh thói quen chứ không
    | phải trừng phạt.
    |
    | Đếm số ĐƠN, không đếm số phút. Một đơn xin tới 9h và một đơn xin tới 11h
    | đều là một lần phải báo trước.
    |
    | Đặt 0 để tắt hạn mức.
    |
    */

    'late_arrival_max_per_month' => (int) env('LATE_ARRIVAL_MAX_PER_MONTH', 3),

];
