<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Enums\AttendanceDecision;
use App\Domain\Attendance\Enums\RequestStatus;
use App\Domain\Attendance\Models\AttendanceAdjustment;
use App\Domain\Identity\Models\User;
use App\Support\Exceptions\RequestNotEditableException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Quản lý duyệt hoặc từ chối một đơn giải trình.
 *
 * ## Duyệt thì ghi ngày công qua ĐÚNG đường nút bấm tay vẫn đi
 *
 * Gọi `ReviewWorkDayAction` chứ không tự `updateOrCreate` vào `work_days`. Luật
 * "chỉ 'bỏ qua' mới được ấn định số phút" sống ở đó; chép lại phép ghi ở đây là
 * hai chỗ cùng viết vào một bảng, và chúng sẽ lệch nhau ở lần đổi luật đầu
 * tiên. Bảng công không cần biết con số đến từ nút bấm hay từ đơn.
 *
 * Quyết định luôn là `Waived` — đó chính là nghĩa của một đơn giải trình được
 * chấp nhận: *"giờ thấp nhưng có lý do chính đáng"*. Cũng là quyết định duy
 * nhất nhận được `adjusted_minutes`.
 *
 * ## Số phút của NGƯỜI DUYỆT thắng số người nộp xin
 *
 * `$soPhut` là con số người duyệt chốt. Giao diện điền sẵn `requested_minutes`
 * cho tiện, nhưng cái đi vào `work_days` là cái người duyệt gửi lên — nếu không
 * thì "duyệt" chỉ còn nghĩa là "đồng ý với mọi con số nhân viên tự khai".
 *
 * NULL nghĩa là bỏ qua ngày này mà không ấn định số nào — số hệ thống đo được
 * giữ nguyên, chỉ là ngày đó thôi không bị coi là bất thường nữa.
 *
 * ## Khoá dòng rồi đọc lại trạng thái trước khi ghi
 *
 * Hai quản lý cùng mở hộp duyệt và cùng bấm thì người thứ hai phải nhận lỗi,
 * không được ghi đè quyết định của người thứ nhất một cách im lặng.
 */
final class ReviewAdjustmentAction
{
    /** `work_days.reason` là varchar(500) — xem chú thích ở `motLyDoNgan()`. */
    private const int TRAN_LY_DO = 500;

    public function __construct(
        private readonly ReviewWorkDayAction $ghiNgayCong,
    ) {}

    public function execute(
        AttendanceAdjustment $don,
        User $nguoiDuyet,
        bool $dongY,
        ?int $soPhut,
        ?string $ghiChu,
    ): AttendanceAdjustment {
        return DB::transaction(function () use ($don, $nguoiDuyet, $dongY, $soPhut, $ghiChu): AttendanceAdjustment {
            /** @var AttendanceAdjustment $khoa */
            $khoa = AttendanceAdjustment::query()
                ->whereKey($don->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($khoa->status !== RequestStatus::Pending) {
                throw new RequestNotEditableException($khoa->status->label());
            }

            $khoa->forceFill([
                'status' => $dongY ? RequestStatus::Approved : RequestStatus::Rejected,
                // Chép lại số đã duyệt. `work_days.adjusted_minutes` là số HIỆN
                // HÀNH và có thể bị sửa thẳng sau đó; cột này là số đã duyệt
                // trên đơn này, và không bao giờ đổi nữa.
                'approved_minutes' => $dongY ? $soPhut : null,
                'reviewed_by' => $nguoiDuyet->id,
                'reviewed_at' => Date::now(),
                'review_note' => $ghiChu,
            ])->save();

            if ($dongY) {
                $nhanVien = $khoa->user;

                if ($nhanVien instanceof User) {
                    $this->ghiNgayCong->execute(
                        nhanVien: $nhanVien,
                        workDate: $khoa->work_date,
                        decision: AttendanceDecision::Waived,
                        reason: $this->motLyDoNgan($khoa, $ghiChu),
                        actor: $nguoiDuyet,
                        adjustedMinutes: $soPhut,
                    );
                }
            }

            return $khoa->refresh();
        });
    }

    /**
     * Lý do ghi lên ngày công, cắt vừa `varchar(500)`.
     *
     * Bản đầy đủ nằm trên đơn; dòng này là con trỏ để người mở bảng công sáu
     * tháng sau biết con số đó từ đâu ra và đi tìm ở đâu. Cắt cứng chứ không
     * nới cột: `work_days.reason` là một dòng người ta liếc qua trong bảng, còn
     * một bài giải trình dài đọc ở màn đơn.
     */
    private function motLyDoNgan(AttendanceAdjustment $don, ?string $ghiChu): string
    {
        $day = $ghiChu === null || $ghiChu === ''
            ? sprintf('Duyệt đơn giải trình: %s', $don->reason)
            : sprintf('Duyệt đơn giải trình: %s — %s', $don->reason, $ghiChu);

        return mb_strlen($day) <= self::TRAN_LY_DO
            ? $day
            : mb_substr($day, 0, self::TRAN_LY_DO - 1).'…';
    }
}
