<?php

declare(strict_types=1);

namespace App\Domain\Leave;

use App\Domain\Identity\Events\UserAnonymised;
use App\Domain\Leave\Listeners\ScrubLeaveReasons;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Miền Leave: đơn nghỉ, duyệt, và miễn chấm công cho ngày đã duyệt.
 *
 * Nơi duy nhất miền này nghe ngóng miền khác. Identity không được gọi thẳng
 * sang đây (README, "Quy tắc phụ thuộc"), nên việc xoá dữ liệu cá nhân đi qua
 * event `UserAnonymised` — miền nào có dữ liệu của người đó thì tự dọn phần
 * của mình.
 */
final class LeaveServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Đồng bộ, không qua hàng đợi — xem chú thích ở UserAnonymised. Đây là
        // thao tác tuân thủ pháp luật; chạy nền mà hỏng thì dữ liệu nhạy cảm
        // nằm nguyên trong khi nhật ký đã ghi "đã xoá".
        Event::listen(UserAnonymised::class, ScrubLeaveReasons::class);
    }
}
