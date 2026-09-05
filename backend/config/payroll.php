<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Số ngày công chuẩn của một kỳ
    |--------------------------------------------------------------------------
    |
    | KHÔNG có tham số cho việc này, và đó là một quyết định.
    |
    | Công ty chọn tính theo **lịch thực tế từng tháng**: số phút chuẩn của kỳ
    | là tổng số phút ca của mọi ngày làm việc trong kỳ đó, lấy thẳng từ
    | `WorkCalendar` — thứ bảy nửa buổi tính 225 phút, ngày lễ tính 0.
    |
    | Hướng còn lại là một con số cố định (26 ngày). Nó cho lương giờ không đổi
    | quanh năm, dễ giải thích hơn, nhưng tháng có 27 ngày công thực tế thì
    | người làm đủ vẫn chỉ được tính như 26 — và ngược lại.
    |
    | Hệ quả: lương giờ đổi theo tháng. Phiếu lương nói ra số phút chuẩn của kỳ
    | ngay cạnh lương giờ, để con số không xuất hiện từ hư không.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Ân hạn thiếu giờ mỗi ngày
    |--------------------------------------------------------------------------
    |
    | Thiếu dưới ngần này phút trong MỘT ngày thì bỏ qua, không trừ.
    |
    | Ân hạn DỜI NGƯỠNG, không trừ vào số phút: thiếu 20 phút thì tính đủ 20,
    | không phải 15. Cùng quy ước với ân hạn đi muộn và ân hạn về sớm — ba chỗ
    | mà hiểu khác nhau là ba con số không ai đối chiếu nổi.
    |
    | Tính theo TỪNG NGÀY chứ không theo cả kỳ: cộng dồn cả tháng rồi mới trừ
    | ân hạn một lần nghĩa là năm phút lẻ mỗi ngày thành gần hai tiếng cuối
    | tháng, mà mục đích của ân hạn là bỏ qua những lệch vặt.
    |
    | 5 phút — khớp với ngưỡng về sớm hiện hành. Đặt 0 để trừ từ phút đầu tiên.
    |
    */

    'shortfall_grace_minutes' => (int) env('PAYROLL_GRACE_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | Làm tròn số phút thiếu
    |--------------------------------------------------------------------------
    |
    | 0 = tính đúng số phút. Công ty đã chốt như vậy: minh bạch nhất, không ai
    | cãi được, đổi lại con số trên phiếu lương lẻ.
    |
    | Đặt 15 hoặc 30 thì số phút thiếu của mỗi ngày được làm tròn XUỐNG bội của
    | nó — có lợi cho người lao động, nhưng người thiếu 14 phút mỗi ngày thì cả
    | tháng không bị trừ gì.
    |
    */

    'shortfall_round_to_minutes' => (int) env('PAYROLL_ROUND_MINUTES', 0),

];
