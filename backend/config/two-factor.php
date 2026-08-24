<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Kênh xác thực hai lớp
    |--------------------------------------------------------------------------
    |
    | Hỗ trợ: "email" (gửi mã 6 số tới hộp thư) và "totp" (ứng dụng xác thực
    | như Google Authenticator).
    |
    | Đổi giá trị này là đổi kênh cho toàn hệ thống — luồng đăng nhập hai bước,
    | mã khôi phục và quyền gỡ 2FA của quản trị viên đều không phải sửa gì.
    |
    | Cân nhắc khi chọn:
    |
    |   email — quen thuộc, không phải cài thêm app. Nhưng yếu hơn: ai chiếm
    |           được hộp thư là chiếm được tài khoản. Phụ thuộc nhà cung cấp
    |           mail; thư vào spam là nhân viên không đăng nhập được.
    |
    |   totp  — mạnh hơn, chạy offline, không tốn phí. Nhưng nhân viên phải cài
    |           ứng dụng xác thực và quét mã QR một lần.
    |
    */

    'driver' => env('TWO_FACTOR_DRIVER', 'email'),

    /*
    |--------------------------------------------------------------------------
    | Thời hạn mã email
    |--------------------------------------------------------------------------
    |
    | Ngắn quá thì người dùng chưa kịp mở hộp thư; dài quá thì mã bị lộ còn
    | sống lâu. 10 phút là cân bằng thường dùng.
    |
    */

    'email_code_lifetime_minutes' => (int) env('TWO_FACTOR_CODE_LIFETIME', 10),

    /*
    |--------------------------------------------------------------------------
    | Hàng đợi gửi mã
    |--------------------------------------------------------------------------
    |
    | Mã OTP đi hàng đợi RIÊNG, không dùng chung với `default`, `notifications`
    | hay `media`. Đây là hàng đợi duy nhất có người đang ngồi đợi kết quả ngay
    | lúc đó — mọi hàng khác đều là việc nền.
    |
    | Hàng này phải đứng ĐẦU danh sách ưu tiên trong config/horizon.php. Nếu
    | không, một đợt quét deadline đẩy hai trăm email vào hàng sẽ khiến mã OTP
    | của người đang đứng ở màn đăng nhập xếp sau tất cả.
    |
    | Job mang mã ở dạng rõ, nên hàng đợi phải là Redis nội bộ — không đẩy sang
    | dịch vụ hàng đợi của bên thứ ba.
    |
    */

    'queue' => env('TWO_FACTOR_QUEUE', 'auth'),

    /*
    |--------------------------------------------------------------------------
    | Giới hạn số lần
    |--------------------------------------------------------------------------
    |
    | `verify_attempts` — số lần nhập sai trước khi khoá tạm.
    | `resend_attempts` — số lần bấm "gửi lại" trong `decay_seconds`. Không
    |   chặn thì nút gửi lại thành công cụ spam hộp thư người khác.
    |
    */

    'verify_attempts' => 5,
    'resend_attempts' => 3,
    'decay_seconds' => 300,

];
