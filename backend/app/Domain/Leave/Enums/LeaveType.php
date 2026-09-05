<?php

declare(strict_types=1);

namespace App\Domain\Leave\Enums;

/**
 * Loại nghỉ.
 *
 * Ở phạm vi này, loại nghỉ **chỉ là nhãn** — nó không trừ vào quỹ nào, không
 * đổi cách tính công. Nó tồn tại vì quản lý cần biết mình đang duyệt cái gì:
 * duyệt nghỉ ốm và duyệt nghỉ việc riêng là hai quyết định khác nhau, dù hệ
 * thống xử lý giống hệt.
 *
 * Khi có quỹ phép (đợt 4 đầy đủ) thì chính enum này là chỗ gắn "loại nào trừ
 * quỹ nào" — nên tách sẵn từ bây giờ, không gộp thành một ô ghi chú tự do.
 */
enum LeaveType: string
{
    case Annual = 'annual';
    case Sick = 'sick';
    case Unpaid = 'unpaid';
    case Personal = 'personal';

    public function label(): string
    {
        return match ($this) {
            self::Annual => 'Nghỉ phép năm',
            self::Sick => 'Nghỉ ốm',
            self::Unpaid => 'Nghỉ không lương',
            self::Personal => 'Nghỉ việc riêng',
        };
    }

    /**
     * Ngày nghỉ loại này có được công ty trả lương không.
     *
     * **Đây là chỗ duy nhất quyết định điều đó**, và nó ra tiền — chú thích đầu
     * lớp đã lường trước rằng enum này sẽ là chỗ gắn luật ấy.
     *
     * - **Phép năm**: có, Điều 113 Bộ luật Lao động 2019.
     * - **Nghỉ ốm**: có. Công ty vẫn trả ngày ốm có giấy; phần bảo hiểm xã hội
     *   chi trả được quyết toán ngoài hệ thống này. Đổi ở đây nếu công ty
     *   chuyển sang để BHXH gánh hẳn.
     * - **Việc riêng**: không. Điều 115 chỉ cho nghỉ có lương trong vài trường
     *   hợp cụ thể (kết hôn, con kết hôn, tang); ngoài ra là nghỉ không hưởng
     *   lương. Người thuộc diện Điều 115 thì nhân sự cộng bù bằng ô điều chỉnh
     *   quỹ phép, chứ không nới luật ở đây.
     * - **Không lương**: không, theo đúng tên gọi.
     *
     * Phiếu lương hiện tách riêng từng loại chứ không gộp thành một dòng "nghỉ"
     * — người đọc phải thấy được ngày nào bị trừ và vì sao.
     */
    public function isPaidLeave(): bool
    {
        return match ($this) {
            self::Annual, self::Sick => true,
            self::Personal, self::Unpaid => false,
        };
    }
}
