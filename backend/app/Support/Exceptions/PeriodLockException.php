<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Thao tác bị chặn vì kỳ công đã chốt, hoặc lệnh chốt/mở khoá không hợp lệ.
 *
 * ## Vì sao gom bốn tình huống vào một lớp
 *
 * Cả bốn đều trả lời cùng một câu hỏi cho người dùng — *"vì sao tôi không làm
 * được việc này với tháng đó"* — và cả bốn đều dẫn tới cùng một hành động: đi
 * hỏi giám đốc. Tách thành bốn lớp là bốn chỗ phải sửa mỗi khi câu chữ đổi, cho
 * một khác biệt mà người đọc không quan tâm.
 *
 * ## Câu chữ phải nói ra KỲ NÀO
 *
 * "Kỳ công đã chốt" là câu vô dụng khi người ta đang sửa một ngày của tháng
 * trước mà không nhận ra. Mọi thông báo ở đây đều gọi tên kỳ.
 */
final class PeriodLockException extends DomainException
{
    private function __construct(string $message, private readonly string $truong)
    {
        parent::__construct($message);
    }

    /** Chặn mọi thao tác ghi vào một kỳ đã chốt. */
    public static function daChot(string $ky, string $truong = 'date'): self
    {
        return new self(
            sprintf(
                'Kỳ công %s đã chốt sổ nên không sửa được nữa. Cần điều chỉnh thì đề nghị giám đốc mở khoá kỳ này.',
                self::tenKy($ky),
            ),
            $truong,
        );
    }

    /**
     * Không chốt được kỳ chưa kết thúc.
     *
     * Chốt giữa kỳ là khoá luôn những ngày chưa ai đi làm, và người ta sẽ phát
     * hiện ra vào sáng hôm sau khi nhịp tim không ghi được gì.
     */
    public static function chuaKetThuc(string $ky): self
    {
        return new self(
            sprintf('Kỳ công %s chưa kết thúc nên chưa chốt sổ được.', self::tenKy($ky)),
            'period',
        );
    }

    public static function daChotRoi(string $ky): self
    {
        return new self(
            sprintf('Kỳ công %s đã chốt từ trước.', self::tenKy($ky)),
            'period',
        );
    }

    public static function chuaChot(string $ky): self
    {
        return new self(
            sprintf('Kỳ công %s chưa chốt nên không có gì để mở khoá.', self::tenKy($ky)),
            'period',
        );
    }

    public function errorCode(): string
    {
        return 'PERIOD_LOCKED';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return [$this->truong => [$this->getMessage()]];
    }

    /** `2026-09` thành `tháng 09/2026` — cách người ta đọc một kỳ công. */
    private static function tenKy(string $ky): string
    {
        [$nam, $thang] = array_pad(explode('-', $ky), 2, '');

        return $thang === '' ? $ky : sprintf('tháng %s/%s', $thang, $nam);
    }
}
