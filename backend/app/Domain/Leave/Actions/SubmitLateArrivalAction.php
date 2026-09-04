<?php

declare(strict_types=1);

namespace App\Domain\Leave\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Leave\Data\LeaveQuota;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Models\LateArrivalRequest;
use App\Support\Exceptions\LateArrivalAlreadyRequestedException;
use App\Support\Exceptions\LeaveQuotaExceededException;
use Illuminate\Support\Facades\DB;

/**
 * Nộp đơn xin đi làm muộn.
 *
 * Kiểm trùng bên trong giao dịch và có khoá dòng, cùng lý do với đơn nghỉ: hai
 * lần bấm nút liên tiếp — hoặc một cú double-click — chạy song song thì cả hai
 * đều thấy "chưa có đơn nào" và cả hai đều ghi. Validate ở tầng HTTP bắt được
 * trường hợp thường gặp; chỗ này bắt trường hợp đua nhau.
 */
final class SubmitLateArrivalAction
{
    public function execute(
        User $nguoiNop,
        string $ngay,
        string $gioDuKien,
        string $lyDo,
    ): LateArrivalRequest {
        return DB::transaction(function () use ($nguoiNop, $ngay, $gioDuKien, $lyDo): LateArrivalRequest {
            $daCo = LateArrivalRequest::query()
                ->where('user_id', $nguoiNop->id)
                ->where('date', $ngay)
                ->blocking()
                ->lockForUpdate()
                ->exists();

            if ($daCo) {
                throw new LateArrivalAlreadyRequestedException($ngay);
            }

            /*
            | Hạn mức số lần xin đi muộn trong THÁNG chứa ngày này.
            |
            | Đếm số ĐƠN chứ không đếm số phút: một đơn xin tới 9h và một đơn
            | xin tới 11h đều là một lần phải báo trước.
            |
            | Đọc có khoá dòng vì đang ở trong giao dịch — cùng lý do với phép
            | kiểm trùng ngày ngay trên.
            */
            $hanMuc = LeaveQuota::fromConfig();

            if ($hanMuc->lateArrivalMaxPerMonth > 0) {
                $daDung = $hanMuc->lateArrivalsUsed($nguoiNop->id, $ngay, khoaDong: true);

                if ($daDung >= $hanMuc->lateArrivalMaxPerMonth) {
                    throw LeaveQuotaExceededException::xinDiMuon(
                        substr($ngay, 0, 7),
                        $daDung,
                        $hanMuc->lateArrivalMaxPerMonth,
                    );
                }
            }

            return LateArrivalRequest::query()->create([
                'user_id' => $nguoiNop->id,
                'date' => $ngay,
                'expected_arrival' => $gioDuKien,
                'reason' => $lyDo,
                'status' => LeaveStatus::Pending,
            ]);
        });
    }
}
