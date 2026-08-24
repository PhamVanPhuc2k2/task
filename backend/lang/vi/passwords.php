<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Thông báo đặt lại mật khẩu
|--------------------------------------------------------------------------
|
| Hệ thống hiện KHÔNG có luồng "quên mật khẩu" tự phục vụ: nhân viên liên hệ
| nhân sự và quản trị viên đặt lại hộ (xem `ResetUserPasswordAction`). Chọn vậy
| vì bắt buộc xác thực hai lớp qua email — nếu email cũng là đường khôi phục mật
| khẩu thì chiếm được hộp thư là chiếm được cả hai lớp.
|
| Giữ tệp này để nếu sau này bật luồng tự phục vụ thì thông báo đã sẵn tiếng
| Việt, không phải nhớ quay lại.
|
*/

return [
    'reset' => 'Đã đặt lại mật khẩu.',
    'sent' => 'Đã gửi liên kết đặt lại mật khẩu tới email.',
    'throttled' => 'Vui lòng đợi một lát rồi thử lại.',
    'token' => 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.',
    'user' => 'Không tìm thấy tài khoản với email này.',
];
