<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Actions\RecordHeartbeatAction;
use App\Domain\Attendance\Actions\SummariseAttendanceAction;
use App\Domain\Identity\Models\User;
use App\Support\Time\WorkDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

/**
 * Nhịp tim từ giao diện: "người này đang mở Explus".
 *
 * Giao diện gửi mỗi phút chừng nào tab còn mở. Đây
 * là đường được gọi nhiều nhất trong cả hệ thống — hai trăm nhân sự × tám tiếng
 * ≈ 96.000 lượt mỗi ngày, nên nó cố tình làm ít việc: một truy vấn tìm phiên
 * gần nhất, một truy vấn ghi.
 *
 * **Trả về luôn số phút hôm nay.** Giao diện cần con số đó để hiện thanh "Hôm
 * nay 6h12"; trả kèm ngay đây thì không phải thêm một endpoint nữa và không
 * phải hỏi thêm một lượt mỗi phút. Nhân viên **nhìn thấy số của chính mình theo
 * thời gian thực** — đó là khác biệt giữa tự theo dõi và bị theo dõi lén.
 */
final class HeartbeatController
{
    public function __invoke(
        Request $request,
        RecordHeartbeatAction $ghiNhip,
        SummariseAttendanceAction $tongHop,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $phien = $ghiNhip->execute($actor);

        /** @var CarbonImmutable $bayGio */
        $bayGio = Date::now();
        $homNay = WorkDate::from($bayGio);

        $ngay = $tongHop->execute([$actor->id], $homNay, $homNay)
            ->get($actor->id.':'.$homNay);

        return new JsonResponse([
            'data' => [
                'work_date' => $homNay,
                'today_minutes' => $ngay?->effectiveMinutes() ?? 0,
                // Null khi đã chạm trần giờ trong ngày. Giao diện đọc cờ này
                // để nói thẳng "đã đạt trần" thay vì im lặng đứng yên ở một
                // con số không nhúc nhích — người dùng sẽ tưởng hệ thống hỏng.
                'capped' => $phien === null,
                'session_started_at' => $phien?->started_at->toIso8601String(),
            ],
        ]);
    }
}
