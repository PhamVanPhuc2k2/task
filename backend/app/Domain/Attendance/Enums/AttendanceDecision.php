<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Enums;

/**
 * Quyết định của người quản lý trên một ngày công.
 *
 * Hệ thống **đo và gắn cờ**; con người **quyết định**. Không có công thức nào
 * tự động trừ tiền — con số giờ chỉ là một trong ba thứ người quản lý nhìn,
 * cạnh task đã động và báo cáo ngày.
 */
enum AttendanceDecision: string
{
    /** Ngày công được chấp nhận như hệ thống đo được. */
    case Confirmed = 'confirmed';

    /**
     * Bỏ qua: giờ thấp nhưng có lý do chính đáng.
     *
     * Đây là trường hợp thường gặp nhất và cũng là lý do bảng này tồn tại —
     * họp cả ngày ngoài công ty, mất mạng, đi gặp khách.
     */
    case Waived = 'waived';

    /** Đánh dấu để hỏi lại. Không phải kết luận, chỉ là chưa xong. */
    case Flagged = 'flagged';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Đã ghi nhận',
            self::Waived => 'Bỏ qua',
            self::Flagged => 'Cần hỏi lại',
        };
    }
}
