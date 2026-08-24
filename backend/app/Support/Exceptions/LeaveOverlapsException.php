<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Chặn hai đơn nghỉ chồng lấn nhau.
 *
 * Không chặn thì một ngày có thể thuộc hai đơn, và câu hỏi "ngày này nghỉ theo
 * đơn nào, ai duyệt, lý do gì" không còn đáp án duy nhất. Tệ hơn: duyệt đơn
 * này rồi từ chối đơn kia thì cùng một ngày vừa được miễn chấm công vừa không.
 *
 * Chỉ đơn **đang chờ duyệt hoặc đã duyệt** mới chặn. Bị từ chối rồi nộp lại
 * với lý do rõ hơn là chuyện bình thường.
 */
final class LeaveOverlapsException extends DomainException
{
    public function __construct(string $tu, string $den)
    {
        parent::__construct(sprintf(
            'Bạn đã có đơn nghỉ khác trùng vào khoảng %s – %s. Rút đơn cũ trước, hoặc chọn ngày khác.',
            $tu,
            $den,
        ));
    }

    public function errorCode(): string
    {
        return 'LEAVE_OVERLAPS';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return ['start_date' => [$this->getMessage()]];
    }
}
