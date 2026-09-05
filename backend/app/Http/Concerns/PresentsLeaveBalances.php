<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Domain\Identity\Models\User;
use App\Domain\Leave\Data\AnnualLeaveBalance;

/**
 * Hình dạng JSON của một quỹ phép năm.
 *
 * Ba controller cùng trả về nó — quỹ của tôi, bảng của nhân sự, và kết quả sau
 * khi sửa. Chép ba lần là ba chỗ sẽ lệch nhau ở lần thêm trường đầu tiên, và
 * giao diện thấy một trường chỉ xuất hiện sau khi bấm Lưu chứ không có lúc tải
 * trang.
 *
 * ## Trả CẢ BỐN SỐ NGUỒN, không chỉ số còn lại
 *
 * Câu hỏi thật của người dùng không phải *"tôi còn mấy ngày"* mà là *"vì sao
 * tôi còn ngần này"*. Trả mỗi số dư thì mọi thắc mắc đều dồn về nhân sự, và
 * nhân sự cũng phải mở database ra xem.
 */
trait PresentsLeaveBalances
{
    /**
     * @return array<string, mixed>
     */
    protected function presentBalance(
        AnnualLeaveBalance $quy,
        ?User $nhanVien = null,
        ?float $conLaiNamTruoc = null,
    ): array {
        $data = [
            'year' => $quy->year,

            // Số được hưởng, đã áp ghi đè nếu có.
            'entitled_days' => $quy->entitledDays,

            // Số hệ thống TỰ TÍNH theo Điều 113 và 114, giữ lại kể cả khi đã bị
            // ghi đè: màn hình nói được "tự tính 12, nhân sự đặt 15".
            'computed_entitled_days' => $quy->computedEntitledDays,
            'is_overridden' => $quy->isOverridden,

            'carried_over_days' => $quy->carriedOverDays,
            'adjustment_days' => $quy->adjustmentDays,

            'total_days' => $quy->totalDays(),
            'used_days' => $quy->usedDays,

            // Được phép ÂM — ai đó đã duyệt vượt quỹ. Kẹp về 0 ở đây sẽ giấu
            // mất đúng cái tình huống cần người nhìn tới.
            'remaining_days' => $quy->remainingDays(),

            'note' => $quy->note,
        ];

        if ($conLaiNamTruoc !== null) {
            /*
            | Năm ngoái còn dư bao nhiêu.
            |
            | Nhân sự cần con số này để quyết định chuyển bao nhiêu sang năm
            | nay. Không trả thì họ phải đổi năm, ghi ra giấy, rồi đổi về —
            | và một phần sẽ gõ nhầm.
            |
            | Chỉ là GỢI Ý, không phải phép chuyển tự động: chuyển phép tồn là
            | một quyết định có người chịu trách nhiệm.
            */
            $data['previous_remaining_days'] = $conLaiNamTruoc;
        }

        if ($nhanVien instanceof User) {
            $data['user'] = [
                'id' => $nhanVien->uuid,
                'name' => $nhanVien->name,
                'department' => $nhanVien->department?->name,
                // Ngày vào làm quyết định số phép được hưởng năm đầu. Hiện ra
                // để nhân sự đối chiếu được ngay khi con số trông lạ — và để
                // thấy ai đang thiếu ngày vào làm.
                'joined_at' => $nhanVien->joined_at?->toDateString(),
            ];
        }

        return $data;
    }
}
