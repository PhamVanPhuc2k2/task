<?php

declare(strict_types=1);

namespace App\Domain\Identity\Data;

use App\Domain\Identity\Models\User;

/**
 * Kết quả tạo tài khoản nhân viên.
 *
 * Mang theo **mật khẩu tạm dạng rõ** vì đó là thứ chỉ tồn tại đúng một lần:
 * database chỉ lưu bản băm, nên nếu không trả ra ngay tại đây thì không ai —
 * kể cả quản trị viên — biết mật khẩu để đưa cho nhân viên mới. Trước khi có
 * lớp này, tạo xong tài khoản là phải bấm thêm "đặt lại mật khẩu" mới dùng
 * được, tức là tạo ra một tài khoản chết rồi hồi sinh nó.
 *
 * Quy ước đặt tên: DTO đầu vào kết thúc bằng `Data`, đối tượng kết quả kết
 * thúc bằng `Result` — xem README, "Quyết định phát sinh khi làm 1.3".
 */
final readonly class CreateUserResult
{
    public function __construct(
        public User $user,
        public string $temporaryPassword,
    ) {}
}
