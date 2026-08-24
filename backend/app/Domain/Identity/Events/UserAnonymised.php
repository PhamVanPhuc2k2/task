<?php

declare(strict_types=1);

namespace App\Domain\Identity\Events;

use App\Domain\Identity\Models\User;

/**
 * Một người dùng vừa bị xoá dữ liệu cá nhân theo Nghị định 13.
 *
 * Miền Identity xoá được phần của nó — tên, email, số điện thoại, mã nhân viên.
 * Nhưng dữ liệu cá nhân của người đó **nằm rải ở các miền khác**, và Identity
 * không được gọi thẳng sang chúng (README, "Quy tắc phụ thuộc"). Bắn event là
 * đường duy nhất đúng.
 *
 * Listener của event này chạy **đồng bộ, trong cùng giao dịch** với việc ẩn
 * danh — cố ý, không đưa vào hàng đợi. Đây là thao tác tuân thủ pháp luật: một
 * listener chạy nền mà thất bại sẽ để lại dữ liệu nhạy cảm nằm nguyên trong
 * khi nhật ký đã ghi "đã xoá", và không ai biết cho tới khi có người đi kiểm.
 */
final readonly class UserAnonymised
{
    public function __construct(public User $user) {}
}
