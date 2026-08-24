<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Data\DailyAttendance;
use App\Domain\Attendance\Models\Holiday;
use App\Domain\Attendance\Models\WorkDay;
use App\Domain\Attendance\Models\WorkSession;
use Illuminate\Support\Collection;

/**
 * Gom phiên làm việc thành số giờ theo từng ngày công.
 *
 * Nhận danh sách id người dùng chứ không nhận từng người: bảng tháng của một
 * phòng ba mươi người phải là **ba truy vấn cố định**, không phải ba mươi lần
 * gọi. Đây đúng là chỗ dễ thành N+1 nhất của cả tính năng.
 *
 * Không nằm ở Controller vì cả màn "của tôi" lẫn màn bảng tháng của quản lý đều
 * cần đúng phép tính này — hai bản sao sẽ lệch nhau ngay lần đầu ai đó sửa một
 * bên.
 */
final class SummariseAttendanceAction
{
    /**
     * @param  list<int>  $userIds
     * @return Collection<string, DailyAttendance> Khoá là "userId:workDate"
     */
    public function execute(array $userIds, string $tuNgay, string $denNgay): Collection
    {
        if ($userIds === []) {
            return new Collection;
        }

        // Gom ở database. Cộng dồn trong PHP thì phải nạp mọi phiên của cả
        // tháng về bộ nhớ chỉ để cộng vài con số.
        $tong = WorkSession::query()
            ->selectRaw('user_id, work_date')
            ->selectRaw('SUM(TIMESTAMPDIFF(SECOND, started_at, ended_at)) as total_seconds')
            ->selectRaw('MIN(started_at) as first_seen_at')
            ->selectRaw('MAX(ended_at) as last_seen_at')
            ->selectRaw('COUNT(*) as session_count')
            ->whereIn('user_id', $userIds)
            ->betweenWorkDates($tuNgay, $denNgay)
            ->groupBy('user_id', 'work_date')
            ->get();

        $quyetDinh = WorkDay::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('work_date', [$tuNgay, $denNgay])
            ->get()
            ->keyBy(fn (WorkDay $d): string => $d->user_id.':'.$d->work_date);

        /** @var Collection<string, DailyAttendance> $ketQua */
        $ketQua = new Collection;

        foreach ($tong as $dong) {
            $userId = (int) $dong->getAttribute('user_id');
            $ngay = (string) $dong->getAttribute('work_date');
            $khoa = $userId.':'.$ngay;

            $quyet = $quyetDinh->get($khoa);

            $ketQua->put($khoa, new DailyAttendance(
                userId: $userId,
                workDate: $ngay,
                // Làm tròn xuống: 59 giây không phải một phút làm việc.
                measuredMinutes: (int) floor((int) $dong->getAttribute('total_seconds') / 60),
                sessionCount: (int) $dong->getAttribute('session_count'),
                firstSeenAt: (string) $dong->getAttribute('first_seen_at'),
                lastSeenAt: (string) $dong->getAttribute('last_seen_at'),
                decision: $quyet?->decision,
                adjustedMinutes: $quyet?->adjusted_minutes,
                reason: $quyet?->reason,
            ));
        }

        // Ngày có quyết định nhưng KHÔNG có phiên nào cũng phải hiện ra. Đây
        // chính là trường hợp hay gặp nhất: người nghỉ họp cả ngày, không đụng
        // vào hệ thống, quản lý bấm "bỏ qua". Bỏ sót thì ngày đó biến mất khỏi
        // bảng và trông như chưa ai xử lý.
        foreach ($quyetDinh as $khoa => $quyet) {
            if ($ketQua->has($khoa)) {
                continue;
            }

            $ketQua->put($khoa, new DailyAttendance(
                userId: $quyet->user_id,
                workDate: $quyet->work_date,
                measuredMinutes: 0,
                sessionCount: 0,
                firstSeenAt: null,
                lastSeenAt: null,
                decision: $quyet->decision,
                adjustedMinutes: $quyet->adjusted_minutes,
                reason: $quyet->reason,
            ));
        }

        return $ketQua;
    }

    /**
     * Ngày nghỉ lễ trong khoảng, theo **ngày thực nghỉ**.
     *
     * Dùng `observed_date` chứ không dùng `date`: lễ trùng ngày nghỉ hằng tuần
     * thì nghỉ bù sang ngày kế tiếp (Điều 112 Bộ luật Lao động 2019), và bảng
     * công phải đếm theo ngày người ta thật sự nghỉ.
     *
     * @return array<string, string> ngày => tên ngày lễ
     */
    public function holidays(string $tuNgay, string $denNgay): array
    {
        return Holiday::query()
            ->whereBetween('observed_date', [$tuNgay, $denNgay])
            ->pluck('name', 'observed_date')
            ->all();
    }
}
