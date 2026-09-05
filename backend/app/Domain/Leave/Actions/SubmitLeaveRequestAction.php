<?php

declare(strict_types=1);

namespace App\Domain\Leave\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Leave\Data\LeaveQuota;
use App\Domain\Leave\Data\LeaveWindow;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Enums\LeaveType;
use App\Domain\Leave\Models\LeaveRequest;
use App\Support\Exceptions\AnnualLeaveExhaustedException;
use App\Support\Exceptions\LeaveDateOutOfWindowException;
use App\Support\Exceptions\LeaveOverlapsException;
use App\Support\Exceptions\LeaveQuotaExceededException;
use Illuminate\Support\Facades\DB;

/**
 * Nhân viên nộp đơn xin nghỉ.
 *
 * Bốn ràng buộc — khoảng ngày, chồng lấn, hạn mức nghỉ không lương, quỹ phép
 * năm — và cả bốn đều có hiệu lực Ở ĐÂY chứ không chỉ ở FormRequest: chúng là
 * chính sách nghiệp vụ, không phải luật định dạng dữ liệu. Chặn ở tầng nhận
 * request thì bất kỳ đường nào khác gọi tới Action sau này (một lệnh nhập liệu,
 * một job đồng bộ) đều đi vòng qua được mà không ai nhận ra.
 */
final class SubmitLeaveRequestAction
{
    public function __construct(
        private readonly ResolveLeaveBalanceAction $quyPhep,
    ) {}

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

            $this->kiemHanMucKhongLuong($nguoiNop->id, $loai, $tuNgay, $denNgay);
            $this->kiemQuyPhepNam($nguoiNop, $loai, $tuNgay, $denNgay);

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

    /**
     * Quỹ phép năm, kiểm cho TỪNG NĂM mà đơn chạm tới.
     *
     * ## Đếm ngày CÔNG, không đếm ngày lịch
     *
     * Đơn từ thứ sáu tới thứ hai phủ 4 ngày lịch nhưng chỉ tiêu 2,5 ngày phép —
     * thứ bảy nửa buổi, chủ nhật không tính, ngày lễ không tính. Đếm ngày lịch
     * thì một tuần nghỉ ăn 7 ngày trong quỹ 12 ngày và quỹ phép thành vô nghĩa.
     *
     * Phép đếm mượn của `ResolveLeaveBalanceAction` chứ không tự viết lại: hai
     * chỗ hiểu "một ngày phép" khác nhau là kiểu lệch chỉ lộ ra khi có người
     * cộng tay lại để đối chiếu.
     *
     * ## Vượt quỹ thì CHẶN, không cảnh báo
     *
     * Duyệt vượt quỹ là một khoản tiền công ty phải trả mà không ai quyết định.
     * Người vẫn cần nghỉ thì nộp lại dưới dạng nghỉ không lương — câu lỗi nói
     * thẳng ra đường đi đó.
     *
     * Nằm TRONG giao dịch và đọc có khoá dòng, cùng lý do với hạn mức bên dưới.
     */
    private function kiemQuyPhepNam(User $nguoiNop, LeaveType $loai, string $tuNgay, string $denNgay): void
    {
        if ($loai !== LeaveType::Annual) {
            return;
        }

        foreach (LeaveQuota::cacNamCham($tuNgay, $denNgay) as $nam) {
            $canThem = $this->quyPhep->soNgayCongTrongNam($tuNgay, $denNgay, $nam);

            // Đơn phủ trọn một kỳ nghỉ lễ, hoặc chỉ chạm vào năm sau đúng ngày
            // chủ nhật: không tiêu ngày phép nào của năm đó, không phải kiểm.
            if ($canThem <= 0.0) {
                continue;
            }

            $quy = $this->quyPhep->execute($nguoiNop, $nam, khoaDong: true);

            if (! $quy->fits($canThem)) {
                throw new AnnualLeaveExhaustedException($nam, $quy->remainingDays(), $canThem);
            }
        }
    }

    /**
     * Hạn mức nghỉ không lương, kiểm cho TỪNG NĂM mà đơn chạm tới.
     *
     * Đơn vắt qua giao thừa phải lọt hạn mức của cả hai năm. Chỉ kiểm năm bắt
     * đầu thì một đơn nộp cuối tháng 12 có thể tiêu hết hạn mức năm sau mà năm
     * sau không hề biết.
     *
     * Nằm TRONG giao dịch và đọc có khoá dòng: hai request gửi gần như cùng lúc
     * đều đếm ra "còn chỗ" rồi cùng ghi, và hạn mức bị vượt mà không có gì báo.
     * Cùng lý do với phép kiểm chồng lấn ở trên.
     *
     * Chỉ áp cho loại `unpaid`. Phép năm có quỹ riêng — xem `kiemQuyPhepNam()`
     * ngay trên. Nghỉ ốm và việc riêng là chuyện chính sách, không phải một con
     * số chặn cứng.
     */
    private function kiemHanMucKhongLuong(int $userId, LeaveType $loai, string $tuNgay, string $denNgay): void
    {
        if ($loai !== LeaveType::Unpaid) {
            return;
        }

        $hanMuc = LeaveQuota::fromConfig();

        if ($hanMuc->unpaidMaxDaysPerYear <= 0) {
            return;
        }

        foreach (LeaveQuota::cacNamCham($tuNgay, $denNgay) as $nam) {
            $canThem = LeaveQuota::soNgayTrongNam($tuNgay, $denNgay, $nam);

            if ($canThem === 0) {
                continue;
            }

            $daDung = $hanMuc->unpaidDaysUsed($userId, $nam, khoaDong: true);

            if ($daDung + $canThem > $hanMuc->unpaidMaxDaysPerYear) {
                throw LeaveQuotaExceededException::nghiKhongLuong(
                    $nam,
                    $daDung,
                    $hanMuc->unpaidMaxDaysPerYear,
                    $canThem,
                );
            }
        }
    }
}
