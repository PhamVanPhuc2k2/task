<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Thời hạn lưu dữ liệu cá nhân sau khi nghỉ việc
    |--------------------------------------------------------------------------
    |
    | Tính bằng tháng, kể từ `terminated_at`. Quá hạn thì `php artisan
    | users:anonymise` xoá được thông tin nhận dạng cá nhân — tên, email, số
    | điện thoại, mã nhân viên — trong khi vẫn giữ nguyên lịch sử công việc.
    |
    | ⚠️ **Con số này là quyết định của công ty, không phải của lập trình viên.**
    | Nghị định 13/2023/NĐ-CP yêu cầu bên xử lý dữ liệu xác định rõ thời hạn lưu
    | trữ, và thời hạn đó phải cân giữa:
    |
    |   - Quyền được xoá của người đã nghỉ việc
    |   - Nghĩa vụ lưu chứng từ lương và bảng công theo pháp luật kế toán
    |   - Nhu cầu tra cứu khi có tranh chấp lao động
    |
    | 60 tháng (5 năm) chỉ là mốc khởi điểm để bàn, chọn theo thời hạn lưu chứng
    | từ kế toán. Người có thẩm quyền của công ty phải chốt lại và ký.
    |
    */

    'retention_months' => (int) env('IDENTITY_RETENTION_MONTHS', 60),

];
