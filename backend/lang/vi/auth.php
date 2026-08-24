<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Thông báo xác thực
|--------------------------------------------------------------------------
|
| `failed` cố ý KHÔNG nói rõ sai email hay sai mật khẩu. Nói rõ là biến trang
| đăng nhập thành công cụ dò xem địa chỉ nào có tài khoản trong công ty.
|
| Phần lớn thông báo đăng nhập của hệ thống không đi qua đây mà qua các lớp
| `DomainException` (`InvalidCredentialsException`, `AccountDisabledException`,
| `InvalidTwoFactorCodeException`…) — chúng mang sẵn câu tiếng Việt và mã lỗi
| để frontend xử lý theo trường hợp. Tệp này chỉ phủ những chỗ Laravel tự sinh.
|
*/

return [
    'failed' => 'Email hoặc mật khẩu không đúng.',
    'password' => 'Mật khẩu không đúng.',
    'throttle' => 'Thử quá nhiều lần. Vui lòng đợi :seconds giây rồi thử lại.',
];
