<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Chặn duyệt lại hoặc rút một đơn giải trình đã có người xử lý.
 *
 * Đơn đã duyệt đã ghi một dòng vào `work_days` — số giờ của một ngày trong quá
 * khứ đã đổi, và con số đó có thể đã đi vào một kỳ đã chốt. Rút ngược lại là để
 * nó đổi lần nữa mà không ai biết.
 *
 * Cũng là lưới chặn hai người duyệt cùng lúc: người thứ hai phải nhận lỗi chứ
 * không được ghi đè quyết định của người thứ nhất một cách im lặng.
 */
final class AdjustmentNotEditableException extends DomainException
{
    public function __construct(string $trangThai)
    {
        parent::__construct(sprintf(
            'Đơn giải trình đang ở trạng thái "%s" nên không đổi được nữa.',
            $trangThai,
        ));
    }

    public function errorCode(): string
    {
        return 'ADJUSTMENT_NOT_EDITABLE';
    }
}
