<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Domain\Attendance\Actions\SubmitOvertimeAction;
use App\Domain\Attendance\Data\OvertimePolicy;
use App\Domain\Attendance\Models\OvertimeRequest;
use App\Domain\Identity\Models\User;
use App\Domain\Leave\Data\LeaveWindow;
use App\Http\Concerns\PresentsOvertime;
use App\Support\Contracts\WorkCalendar;
use App\Support\Time\WorkDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

/**
 * Đăng ký làm thêm giờ của chính người đang đăng nhập.
 *
 * Không cần quyền gì thêm: ai cũng xem được đơn của mình.
 *
 * ## Trả kèm chính sách VÀ số đã dùng
 *
 * Ba trần của Điều 107 chồng lên nhau — ngày, tháng, năm — và người nộp không
 * có cách nào tự biết mình đã dùng bao nhiêu. Không nói ra thì họ gõ xong cả
 * cái đơn rồi mới nhận một câu từ chối, và lần sau vẫn không đoán được.
 *
 * Hệ số cũng trả về đây: *"tối nay là chủ nhật, 200%"* là thông tin quyết định
 * người ta có nhận làm hay không, và nó phải hiện TRƯỚC khi họ đăng ký.
 */
final class MyOvertimeController
{
    use PresentsOvertime;

    /** Trần số dòng. Luôn trả kèm tổng — quy ước chung của cả dự án. */
    private const int TRAN = 100;

    public function __invoke(
        Request $request,
        WorkCalendar $lich,
        SubmitOvertimeAction $dem,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $ds = OvertimeRequest::query()
            ->where('user_id', $actor->id)
            ->with('reviewer')
            ->orderByDesc('work_date')
            ->orderByDesc('start_time')
            ->limit(self::TRAN)
            ->get();

        $khoang = LeaveWindow::current();
        $chinhSach = OvertimePolicy::fromConfig();

        // Hôm nay theo GIỜ VIỆT NAM, không phải `now()` ở UTC — từ 00:00 tới
        // 07:00 giờ Việt Nam mỗi ngày, `now()` của Laravel vẫn ở hôm trước.
        $homNay = CarbonImmutable::parse(WorkDate::from(Date::now()));
        $nam = (int) $homNay->year;

        return new JsonResponse([
            'data' => [
                'requests' => $ds->map(
                    fn (OvertimeRequest $d): array => $this->presentOvertime($d, $lich),
                )->all(),

                // Trả tổng kèm trần: cắt im lặng thì người có 120 đơn tưởng
                // mình chỉ từng nộp 100.
                'total' => OvertimeRequest::query()->where('user_id', $actor->id)->count(),
                'limit' => self::TRAN,

                'window' => [
                    'earliest' => $khoang->earliest,
                    'latest' => $khoang->latest,
                ],

                'policy' => [
                    'rate_working_percent' => $chinhSach->workingPercent,
                    'rate_weekly_rest_percent' => $chinhSach->weeklyRestPercent,
                    'rate_holiday_percent' => $chinhSach->holidayPercent,
                    'max_minutes_per_day' => $chinhSach->maxMinutesPerDay,
                    'max_minutes_per_month' => $chinhSach->maxMinutesPerMonth,
                    'max_minutes_per_year' => $chinhSach->maxMinutesPerYear,
                ],

                'used' => [
                    'month' => $dem->daDangKy(
                        $actor->id,
                        $homNay->startOfMonth()->toDateString(),
                        $homNay->endOfMonth()->toDateString(),
                    ),
                    'year' => $dem->daDangKy(
                        $actor->id,
                        sprintf('%04d-01-01', $nam),
                        sprintf('%04d-12-31', $nam),
                    ),
                ],
            ],
        ]);
    }
}
