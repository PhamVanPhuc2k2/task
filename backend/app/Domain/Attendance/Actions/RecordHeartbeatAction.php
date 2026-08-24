<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Models\WorkSession;
use App\Domain\Identity\Models\User;
use App\Support\Time\WorkDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

/**
 * Nhận một nhịp tim từ giao diện và gộp vào phiên làm việc.
 *
 * Giao diện gửi nhịp mỗi phút, **chỉ khi** có tương tác thật trong phút đó và
 * tab đang hiển thị. Action này quyết định nhịp vừa tới thuộc phiên đang mở hay
 * mở phiên mới.
 *
 * **Vì sao không lưu từng nhịp tim.** Một nhịp mỗi phút, tám tiếng, hai trăm
 * người, hai mươi hai ngày công ≈ 2,1 triệu dòng mỗi tháng cho một thông tin
 * không ai đọc. Ở đây mỗi nhịp chỉ nối dài `ended_at` của phiên hiện hành, còn
 * lại 3–6 dòng mỗi người mỗi ngày.
 *
 * **Vì sao không dùng cặp "vào ca / ra ca".** Sập nắp laptop, mất điện, mất
 * mạng, đóng trình duyệt trên điện thoại — `beforeunload` không chạy. Thiết kế
 * theo cặp sự kiện sẽ để lại những phiên không bao giờ đóng, và bảng công hiện
 * ai đó làm 47 tiếng liên tục. Ở đây phiên **tự kết thúc** ở nhịp cuối cùng
 * nhận được; không có nhịp nữa thì phiên dừng ở đó, không cần ai báo.
 */
final class RecordHeartbeatAction
{
    /**
     * Khoảng lặng tối đa vẫn coi là cùng một phiên.
     *
     * Mười phút: đủ dài để đọc tài liệu, nghe điện thoại, đi pha cà phê mà
     * không bị cắt phiên; đủ ngắn để nghỉ trưa hai tiếng không bị tính thành
     * giờ làm. Khoảng lặng vượt ngưỡng thì **không được tính** vào tổng — đó là
     * khác biệt cốt lõi so với cách đếm "tab còn mở".
     */
    private const int NGUONG_PHUT = 10;

    public function execute(User $user): WorkSession
    {
        /** @var CarbonImmutable $bayGio */
        $bayGio = Date::now();

        $phienGanNhat = WorkSession::query()
            ->where('user_id', $user->id)
            ->orderByDesc('ended_at')
            ->first();

        if ($this->noiDuoc($phienGanNhat, $bayGio)) {
            /** @var WorkSession $phienGanNhat */
            $phienGanNhat->forceFill(['ended_at' => $bayGio])->save();

            return $phienGanNhat;
        }

        return WorkSession::query()->create([
            'user_id' => $user->id,
            'started_at' => $bayGio,
            'ended_at' => $bayGio,
            'work_date' => WorkDate::from($bayGio),
            'source' => 'heartbeat',
        ]);
    }

    /**
     * Nhịp vừa tới có thuộc phiên đang mở không.
     *
     * Hai điều kiện, và điều kiện thứ hai dễ bị quên: ngoài việc khoảng lặng
     * phải dưới ngưỡng, nhịp mới còn phải **cùng ngày công**. Người làm xuyên
     * qua nửa đêm sẽ được cắt sang phiên mới của ngày hôm sau — nếu không thì
     * một phiên nằm vắt qua hai ngày và toàn bộ số giờ của nó bị dồn hết vào
     * ngày bắt đầu.
     */
    private function noiDuoc(?WorkSession $phien, CarbonImmutable $bayGio): bool
    {
        if ($phien === null) {
            return false;
        }

        if ($phien->ended_at->diffInMinutes($bayGio) > self::NGUONG_PHUT) {
            return false;
        }

        return $phien->work_date === WorkDate::from($bayGio);
    }
}
