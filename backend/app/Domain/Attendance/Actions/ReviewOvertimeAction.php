<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Data\OvertimePolicy;
use App\Domain\Attendance\Enums\RequestStatus;
use App\Domain\Attendance\Models\OvertimeRequest;
use App\Domain\Identity\Models\User;
use App\Support\Contracts\WorkCalendar;
use App\Support\Exceptions\RequestNotEditableException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Quản lý duyệt hoặc từ chối một đơn đăng ký làm thêm giờ.
 *
 * ## Hệ số đóng băng ở ĐÂY, không phải lúc đăng ký
 *
 * Hệ số suy từ loại ngày — thường 150%, nghỉ tuần 200%, lễ 300% (Điều 98). Loại
 * ngày có thể đổi sau khi đơn đã nộp: nhân sự nhập thêm một ngày lễ, hoặc công
 * ty đổi lịch tuần.
 *
 * Lúc đăng ký, màn hình tính sống để người nộp biết mình sắp được trả bao
 * nhiêu. Lúc duyệt, con số được ghi vào `rate_percent` và không đổi nữa — đó là
 * thời điểm công ty cam kết trả, và bảng lương của chặng sau đọc con số đã cam
 * kết chứ không tính lại theo lịch của hôm nay.
 *
 * ## Số phút của NGƯỜI DUYỆT thắng số đã đăng ký
 *
 * "Đăng ký 3 tiếng, thực tế làm 2" là chuyện thường. `$soPhut` là con số người
 * duyệt chốt; để trống thì lấy đúng số đã đăng ký. Tầng Http chặn không cho
 * duyệt NHIỀU HƠN số đã đăng ký — nếu không thì ba cái trần của Điều 107 đã
 * kiểm lúc nộp trở thành vô nghĩa.
 *
 * ## Khoá dòng rồi đọc lại trạng thái trước khi ghi
 *
 * Hai quản lý cùng mở hộp duyệt và cùng bấm thì người thứ hai phải nhận lỗi,
 * không được ghi đè quyết định của người thứ nhất một cách im lặng.
 */
final class ReviewOvertimeAction
{
    public function __construct(
        private readonly WorkCalendar $lich,
    ) {}

    public function execute(
        OvertimeRequest $don,
        User $nguoiDuyet,
        bool $dongY,
        ?int $soPhut,
        ?string $ghiChu,
    ): OvertimeRequest {
        return DB::transaction(function () use ($don, $nguoiDuyet, $dongY, $soPhut, $ghiChu): OvertimeRequest {
            /** @var OvertimeRequest $khoa */
            $khoa = OvertimeRequest::query()
                ->whereKey($don->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($khoa->status !== RequestStatus::Pending) {
                throw new RequestNotEditableException($khoa->status->label());
            }

            $khoa->forceFill([
                'status' => $dongY ? RequestStatus::Approved : RequestStatus::Rejected,
                'rate_percent' => $dongY ? $this->heSo($khoa->work_date) : null,
                'approved_minutes' => $dongY ? ($soPhut ?? $khoa->minutes) : null,
                'reviewed_by' => $nguoiDuyet->id,
                'reviewed_at' => Date::now(),
                'review_note' => $ghiChu,
            ])->save();

            return $khoa->refresh();
        });
    }

    /** Hệ số phần trăm của một ngày, theo loại ngày trên lịch công ty. */
    public function heSo(string $ngay): int
    {
        return OvertimePolicy::fromConfig()->percentFor($this->lich->kindOf($ngay));
    }
}
