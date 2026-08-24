<?php

declare(strict_types=1);

namespace App\Domain\Report\Enums;

/**
 * Trạng thái báo cáo ngày.
 *
 * Ba trạng thái, và `draft` không phải "chờ duyệt" — nó là chỗ để người dùng
 * viết dở rồi quay lại. Với quản lý, bản nháp coi như **chưa nộp**.
 */
enum DailyReportStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Reviewed = 'reviewed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Bản nháp',
            self::Submitted => 'Đã nộp',
            self::Reviewed => 'Quản lý đã xem',
        };
    }

    /** Đã nộp chưa — dùng cho câu hỏi "hôm nay ai chưa báo cáo". */
    public function isSubmitted(): bool
    {
        return $this !== self::Draft;
    }

    /**
     * Còn sửa được nội dung không.
     *
     * Nộp rồi vẫn sửa được, cố ý: báo cáo ngày không phải hồ sơ pháp lý, và
     * khoá lại ngay sau khi nộp chỉ khiến người ta ngại nộp sớm rồi dồn hết
     * vào cuối tuần. Quản lý đã xem thì thôi — sửa sau khi có người đọc là đổi
     * thứ họ đã đọc.
     */
    public function isEditable(): bool
    {
        return $this !== self::Reviewed;
    }
}
