<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use App\Support\Time\HumanTime;

/**
 * Không chốt sổ được vì kỳ còn đơn chờ duyệt.
 *
 * ## Vì sao đây là lỗi chứ không phải cảnh báo
 *
 * Chốt sổ khoá luôn cả đơn từ. Một đơn giải trình còn treo qua ngày chốt là một
 * đơn **không ai duyệt được nữa, vĩnh viễn** — kể cả giám đốc, trừ khi mở khoá
 * lại cả kỳ. Nhân viên đã làm đúng phần việc của mình rồi; thứ biến mất là câu
 * trả lời.
 *
 * Cùng chuyện với đơn nghỉ và đơn đi muộn còn chờ: duyệt sau khi chốt sẽ đổi số
 * ngày công đã dùng để tính lương, nên nó cũng bị chặn — và đơn nằm lại đó mãi.
 *
 * Cho qua kèm một dòng cảnh báo là để người bấm nút tự gánh trách nhiệm nhớ, ở
 * đúng lúc họ đang muốn xong việc. Đây là loại hỏng im lặng mà dự án này đã trả
 * giá nhiều lần.
 *
 * ## Câu chữ phải nói ra CÒN BAO NHIÊU VÀ Ở ĐÂU
 *
 * "Còn đơn chờ duyệt" là câu vô dụng: người bấm nút không biết đi đâu, và ba
 * loại đơn nằm ở ba màn hình khác nhau.
 */
final class PeriodHasPendingWorkException extends DomainException
{
    /**
     * @param  array<string, int>  $conTreo  nhãn loại đơn => số đơn còn chờ
     */
    public function __construct(string $ky, array $conTreo)
    {
        $manh = [];

        foreach ($conTreo as $nhan => $so) {
            if ($so > 0) {
                $manh[] = sprintf('%d %s', $so, $nhan);
            }
        }

        parent::__construct(sprintf(
            'Kỳ công %s còn %s chờ duyệt. Xử lý hết rồi mới chốt được — chốt sổ khoá luôn đơn từ, nên đơn còn treo sẽ không ai duyệt được nữa.',
            HumanTime::ky($ky),
            implode(', ', $manh),
        ));
    }

    public function errorCode(): string
    {
        return 'PERIOD_HAS_PENDING_WORK';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return ['period' => [$this->getMessage()]];
    }
}
