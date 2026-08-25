<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Models\WorkSession;
use App\Domain\Identity\Models\User;
use App\Support\Time\WorkDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Nhận một nhịp tim từ giao diện và gộp vào phiên làm việc.
 *
 * Giao diện gửi nhịp mỗi phút **chừng nào tab còn mở** — không cần thao tác,
 * không cần tab đang hiển thị. Action này quyết định nhịp vừa tới thuộc phiên
 * đang mở hay mở phiên mới.
 *
 * ── Vì sao không đòi thao tác nữa ────────────────────────────────────────────
 *
 * Bản trước chỉ tính khi có bấm/gõ/cuộn trên Explus. Với lập trình viên đó là
 * đo sai người: họ sống trong IDE và terminal, cả buổi sáng viết code xong hệ
 * thống hiện số 0.
 *
 * Đo hụt người làm thật tệ hơn hẳn đếm dư người treo máy. Cái thứ nhất làm
 * người ta mất niềm tin vào bảng công; cái thứ hai nhìn dòng thời gian là thấy,
 * và còn bị trần ngày chặn.
 *
 * Nhưng **tín hiệu cũ không bị vứt đi**: mỗi nhịp vẫn mang cờ `$coThaoTac`, và
 * phiên bị cắt khi cờ đổi. Tổng giờ cộng cả hai loại, dòng thời gian vẫn vẽ
 * được hai màu.
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
     * Từ khi tính theo sự có mặt, ngưỡng này không còn là thứ phân biệt "làm"
     * với "không làm" nữa — tab mở thì nhịp về đều mỗi phút. Nó chuyển thành
     * thứ đo **máy còn sống hay không**: đóng trình duyệt, sập nắp laptop, mất
     * mạng, hết pin đều làm nhịp ngừng, và phiên đóng lại tại đó.
     *
     * Mười phút là đủ rộng để một lần mất mạng hay một lần treo máy ngắn không
     * cắt vụn phiên, mà vẫn đủ hẹp để tắt máy đi ăn trưa hai tiếng không bị
     * tính thành giờ làm.
     */
    private const int NGUONG_PHUT = 10;

    /**
     * @param  bool  $coThaoTac  Phút vừa rồi người dùng có bấm/gõ/cuộn không.
     *                           Mặc định `true` để bản giao diện cũ — vốn chỉ
     *                           gửi khi CÓ thao tác — vẫn ghi đúng loại.
     * @return WorkSession|null Null khi đã chạm trần giờ trong ngày.
     */
    public function execute(User $user, bool $coThaoTac = true): ?WorkSession
    {
        /** @var CarbonImmutable $bayGio */
        $bayGio = Date::now();
        $homNay = WorkDate::from($bayGio);

        if ($this->chamTran($user, $homNay)) {
            return null;
        }

        $phienGanNhat = WorkSession::query()
            ->where('user_id', $user->id)
            ->orderByDesc('ended_at')
            ->first();

        if ($this->noiDuoc($phienGanNhat, $bayGio, $coThaoTac)) {
            /** @var WorkSession $phienGanNhat */
            $phienGanNhat->forceFill(['ended_at' => $bayGio])->save();

            return $phienGanNhat;
        }

        return WorkSession::query()->create([
            'user_id' => $user->id,
            'started_at' => $bayGio,
            'ended_at' => $bayGio,
            'work_date' => $homNay,
            'source' => 'heartbeat',
            'interactive' => $coThaoTac,
        ]);
    }

    /**
     * Đã đủ trần giờ tự động của ngày hôm nay chưa.
     *
     * Từ khi tính theo sự có mặt, một tab quên đóng qua đêm ghi thẳng 16 tiếng
     * công. Vài lần như vậy là không ai còn tin bảng công — mà mất niềm tin thì
     * cả hệ thống chấm công thành vô dụng, chứ không chỉ sai vài con số.
     *
     * Chỉ đếm phiên `heartbeat`. Quãng do người quản lý nhập tay không nên bị
     * một cái trần tự động chặn: nó đã đi qua một con người rồi.
     */
    private function chamTran(User $user, string $homNay): bool
    {
        $tran = (int) config('attendance.max_daily_minutes');

        if ($tran <= 0) {
            return false;
        }

        $giay = (int) WorkSession::query()
            ->where('user_id', $user->id)
            ->where('work_date', $homNay)
            ->where('source', 'heartbeat')
            ->sum(DB::raw('TIMESTAMPDIFF(SECOND, started_at, ended_at)'));

        return (int) floor($giay / 60) >= $tran;
    }

    /**
     * Nhịp vừa tới có thuộc phiên đang mở không.
     *
     * Ba điều kiện:
     *
     *   1. Khoảng lặng dưới ngưỡng.
     *   2. **Cùng ngày công** — điều kiện dễ quên nhất. Người làm xuyên nửa đêm
     *      phải được cắt sang phiên mới của ngày hôm sau, nếu không thì một
     *      phiên nằm vắt qua hai ngày và toàn bộ số giờ dồn hết vào ngày bắt
     *      đầu.
     *   3. **Cùng loại** — có thao tác hay chỉ mở tab. Không cắt theo loại thì
     *      một phiên bốn tiếng lẫn lộn cả hai, và dòng thời gian mất khả năng
     *      phân biệt "ngồi làm" với "để đó". Cắt như vậy làm số phiên mỗi ngày
     *      nhiều hơn trước, nhưng vẫn ở mức hàng chục chứ không hàng nghìn: chỉ
     *      sinh thêm dòng ở đúng lúc người dùng chuyển giữa hai trạng thái.
     */
    private function noiDuoc(?WorkSession $phien, CarbonImmutable $bayGio, bool $coThaoTac): bool
    {
        if ($phien === null) {
            return false;
        }

        if ($phien->ended_at->diffInMinutes($bayGio) > self::NGUONG_PHUT) {
            return false;
        }

        if ($phien->interactive !== $coThaoTac) {
            return false;
        }

        return $phien->work_date === WorkDate::from($bayGio);
    }
}
