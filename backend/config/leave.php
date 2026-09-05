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

    /*
    |--------------------------------------------------------------------------
    | Xin về sớm
    |--------------------------------------------------------------------------
    |
    | Hạn mức RIÊNG, không dùng chung với đi muộn — công ty đã chốt như vậy. Hai
    | con số tách nhau nghĩa là dùng hết quota đi muộn không làm mất quyền xin
    | về sớm, và ngược lại.
    |
    | `grace_minutes` = 5 chứ không phải 0 như đi muộn: về sớm năm phút mà bắt
    | làm đơn thì không ai dùng, và một tính năng không ai dùng còn tệ hơn không
    | có — nó làm bảng công trông như đã theo dõi trong khi thực tế thì không.
    |
    | Ân hạn ở đây DỜI NGƯỠNG, không trừ vào số phút: về sớm 20 phút thì tính
    | đủ 20, không phải 15. Cùng quy ước với ân hạn đi muộn.
    |
    | Đặt hạn mức 0 để tắt.
    |
    */

    'early_leave_max_per_month' => (int) env('EARLY_LEAVE_MAX_PER_MONTH', 3),
    'early_leave_grace_minutes' => (int) env('EARLY_LEAVE_GRACE_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | Quỹ phép năm
    |--------------------------------------------------------------------------
    |
    | Mặc định bám Bộ luật Lao động 2019:
    |
    |   - Điều 113: 12 ngày phép/năm với điều kiện làm việc bình thường, cho
    |     người làm đủ 12 tháng. Chưa đủ thì tính theo tỷ lệ số tháng làm việc
    |     (Nghị định 145/2020, Điều 66).
    |
    |   - Điều 114: cứ đủ 5 năm làm việc cho cùng một người sử dụng lao động
    |     thì được thêm 1 ngày.
    |
    | Ba con số dưới đây là MỨC SÀN theo luật, không phải mức công ty phải
    | dùng. Công ty hào phóng hơn thì đổi ở màn Cài đặt, không sửa mã.
    |
    | CHÚ Ý: `annual_base_days = 0` nghĩa là công ty KHÔNG có phép năm, tức mọi
    | đơn nghỉ phép năm đều bị chặn. Nó KHÔNG mang nghĩa "không giới hạn" như
    | số 0 ở các hạn mức phía trên — đây là một cái quỹ, và quỹ rỗng thì rỗng.
    |
    */

    'annual_base_days' => (int) env('LEAVE_ANNUAL_BASE_DAYS', 12),

    'annual_seniority_step_years' => (int) env('LEAVE_ANNUAL_SENIORITY_STEP', 5),

    'annual_seniority_extra_days' => (int) env('LEAVE_ANNUAL_SENIORITY_EXTRA', 1),

    /*
    |--------------------------------------------------------------------------
    | Trần phép tồn chuyển sang năm sau
    |--------------------------------------------------------------------------
    |
    | Chỉ là TRẦN cho ô nhập của nhân sự, không phải phép chuyển tự động. Chuyển
    | phép tồn là một quyết định có người chịu trách nhiệm — làm tự động thì
    | không ai biết con số đến từ đâu, và nó lặng lẽ đúng hoặc lặng lẽ sai.
    |
    | Màn quỹ phép nói sẵn năm ngoái còn dư bao nhiêu, nên nhân sự không phải
    | tự tính. Đặt 0 để cấm chuyển tiếp hoàn toàn.
    |
    */

    'annual_carry_over_max_days' => (int) env('LEAVE_CARRY_OVER_MAX_DAYS', 5),

];
