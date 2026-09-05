<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Đơn nghỉ phép năm vượt quá quỹ phép của năm đó.
 *
 * ## Câu chữ phải nói ra ĐƯỜNG ĐI TIẾP
 *
 * "Bạn đã hết phép năm" là câu cụt: người ta vẫn cần nghỉ, và việc phải làm là
 * nộp lại đơn dưới dạng **nghỉ không lương**. Không nói ra thì họ đi hỏi nhân
 * sự, mà nhân sự cũng chỉ trả lời đúng câu đó.
 *
 * Nói cả ba con số — còn lại, cần thêm, và của năm nào — vì một đơn vắt qua
 * giao thừa tiêu quỹ của hai năm, và "hết phép" mà không nói năm nào thì người
 * nộp không biết sửa ngày nào.
 */
final class AnnualLeaveExhaustedException extends DomainException
{
    public function __construct(
        int $nam,
        float $conLai,
        float $canThem,
    ) {
        parent::__construct(sprintf(
            'Quỹ phép năm %d chỉ còn %s ngày, mà đơn này cần %s ngày. Nghỉ thêm thì nộp đơn nghỉ không lương.',
            $nam,
            self::soNgay(max($conLai, 0.0)),
            self::soNgay($canThem),
        ));
    }

    public function errorCode(): string
    {
        return 'ANNUAL_LEAVE_EXHAUSTED';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return ['start_date' => [$this->getMessage()]];
    }

    /**
     * `2.5` thành `2,5` và `3.0` thành `3` — cách người Việt viết số ngày.
     *
     * Dấu phẩy chứ không dấu chấm: "2.5 ngày" đọc ra hai nghìn năm trăm với
     * người quen định dạng Việt Nam.
     */
    private static function soNgay(float $ngay): string
    {
        return $ngay === floor($ngay)
            ? (string) (int) $ngay
            : number_format($ngay, 1, ',', '');
    }
}
