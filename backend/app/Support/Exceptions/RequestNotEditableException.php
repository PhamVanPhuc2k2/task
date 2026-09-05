<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Chặn duyệt lại hoặc rút một đơn của miền Attendance đã có người xử lý.
 *
 * Dùng chung cho đơn giải trình công và đơn đăng ký làm thêm giờ — hai loại đơn
 * đi qua đúng một vòng đời, cùng lý do đã ghi ở `RequestStatus`.
 *
 * Đơn giải trình đã duyệt đã ghi một dòng vào `work_days`: số giờ của một ngày
 * trong quá khứ đã đổi, và con số đó có thể đã đi vào một kỳ đã chốt. Đơn làm
 * thêm đã duyệt là một khoản tiền đã cam kết trả cho công việc đã làm. Rút
 * ngược lại là để một con số trong quá khứ đổi mà không ai biết.
 *
 * Cũng là lưới chặn hai người duyệt cùng lúc: người thứ hai phải nhận lỗi chứ
 * không được ghi đè quyết định của người thứ nhất một cách im lặng.
 */
final class RequestNotEditableException extends DomainException
{
    public function __construct(string $trangThai)
    {
        parent::__construct(sprintf(
            'Đơn đang ở trạng thái "%s" nên không đổi được nữa.',
            $trangThai,
        ));
    }

    public function errorCode(): string
    {
        return 'REQUEST_NOT_EDITABLE';
    }
}
