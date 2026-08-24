<?php

declare(strict_types=1);

namespace App\Domain\Leave\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Leave\Data\LeaveWindow;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Enums\LeaveType;
use App\Domain\Leave\Models\LeaveRequest;
use App\Support\Exceptions\LeaveDateOutOfWindowException;
use App\Support\Exceptions\LeaveOverlapsException;
use Illuminate\Support\Facades\DB;

/**
 * Nhân viên nộp đơn xin nghỉ.
 *
 * Ba ràng buộc, và cả ba đều có hiệu lực Ở ĐÂY chứ không chỉ ở FormRequest —
 * chúng là chính sách nghiệp vụ, không phải luật định dạng dữ liệu. Chặn ở tầng
 * nhận request thì bất kỳ đường nào khác gọi tới Action sau này (một lệnh nhập
 * liệu, một job đồng bộ) đều đi vòng qua được mà không ai nhận ra.
 */
final class SubmitLeaveRequestAction
{
    public function execute(
        User $nguoiNop,
        LeaveType $loai,
        string $tuNgay,
        string $denNgay,
        string $lyDo,
    ): LeaveRequest {
        $khoang = LeaveWindow::current();

        if (! $khoang->allows($tuNgay, $denNgay)) {
            throw new LeaveDateOutOfWindowException($khoang->message());
        }

        if ($khoang->tooLong($tuNgay, $denNgay)) {
            throw new LeaveDateOutOfWindowException($khoang->tooLongMessage());
        }

        return DB::transaction(function () use ($nguoiNop, $loai, $tuNgay, $denNgay, $lyDo): LeaveRequest {
            /*
            | Khoá các đơn còn hiệu lực của người này trong lúc kiểm chồng lấn.
            |
            | Không khoá thì hai request gửi gần như cùng lúc — bấm đúp nút Nộp,
            | hoặc mở hai tab — đều thấy "chưa có đơn nào trùng" rồi cùng ghi.
            | Kết quả là hai đơn phủ cùng một ngày, và câu hỏi "ngày này nghỉ
            | theo đơn nào" hết đáp án duy nhất.
            */
            $trung = LeaveRequest::query()
                ->where('user_id', $nguoiNop->id)
                ->blocking()
                ->where('start_date', '<=', $denNgay)
                ->where('end_date', '>=', $tuNgay)
                ->lockForUpdate()
                ->first();

            if ($trung instanceof LeaveRequest) {
                throw new LeaveOverlapsException($trung->start_date, $trung->end_date);
            }

            return LeaveRequest::query()->create([
                'user_id' => $nguoiNop->id,
                'type' => $loai,
                'start_date' => $tuNgay,
                'end_date' => $denNgay,
                'reason' => $lyDo,
                'status' => LeaveStatus::Pending,
            ]);
        });
    }
}
