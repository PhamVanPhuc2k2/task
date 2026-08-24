<?php

declare(strict_types=1);

namespace App\Support\Enums;

/**
 * Tình trạng của một thành phần hạ tầng, và của cả hệ thống.
 *
 * Có ba mức chứ không phải hai, vì **hỏng không phải lúc nào cũng là chết**:
 * mất kho ảnh thì mọi người vẫn giao việc, chấm công, viết báo cáo bình thường
 * — chỉ ảnh không mở được. Gộp nó chung với "mất database" rồi trả 503 sẽ khiến
 * bộ cân bằng tải rút cả máy chủ ra khỏi vòng phục vụ vì một tính năng phụ.
 */
enum HealthStatus: string
{
    /** Chạy bình thường. */
    case Ok = 'ok';

    /** Hỏng, nhưng phần lõi vẫn phục vụ được. */
    case Degraded = 'degraded';

    /** Hỏng tới mức không phục vụ được. */
    case Down = 'down';

    /** Không dùng tới nên không kiểm — ví dụ R2 khi đang lưu tệp ở đĩa nội bộ. */
    case Skipped = 'skipped';

    /**
     * Mức nặng hơn trong hai mức.
     *
     * `Skipped` nhẹ nhất: một thành phần không dùng tới thì không được phép kéo
     * tình trạng chung xuống.
     */
    public function worseOf(self $khac): self
    {
        return $this->weight() >= $khac->weight() ? $this : $khac;
    }

    /** Có nên trả 503 để bộ cân bằng tải rút máy chủ này ra không. */
    public function shouldFailRequest(): bool
    {
        return $this === self::Down;
    }

    private function weight(): int
    {
        return match ($this) {
            self::Skipped => 0,
            self::Ok => 1,
            self::Degraded => 2,
            self::Down => 3,
        };
    }
}
