<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Chặn hai đơn xin đi muộn còn hiệu lực cho cùng một ngày.
 *
 * Không chặn thì một ngày có thể có hai đơn với hai giờ khác nhau, và câu hỏi
 * "hôm đó được duyệt tới mấy giờ" không còn đáp án duy nhất — mà đó chính là
 * con số bảng công dùng để quyết định có miễn hay không.
 *
 * Tách khỏi `LeaveOverlapsException` chỉ vì câu chữ: dùng lại lớp kia thì người
 * dùng nhận được thông báo nói về "đơn nghỉ" trong khi họ đang nộp đơn đi muộn.
 *
 * Chỉ đơn **đang chờ duyệt hoặc đã duyệt** mới chặn. Bị từ chối rồi nộp lại với
 * lý do rõ hơn là chuyện bình thường.
 */
final class LateArrivalAlreadyRequestedException extends DomainException
{
    public function __construct(string $ngay)
    {
        parent::__construct(sprintf(
            'Bạn đã có đơn xin đi muộn cho ngày %s. Rút đơn cũ trước nếu muốn đổi giờ.',
            $ngay,
        ));
    }

    public function errorCode(): string
    {
        return 'LATE_ARRIVAL_ALREADY_REQUESTED';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return ['date' => [$this->getMessage()]];
    }
}
