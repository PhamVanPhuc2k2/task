<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Attendance\Actions\SummariseAttendanceAction;
use App\Domain\Attendance\Data\DailyAttendance;
use App\Domain\Identity\Models\User;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Report\Models\DailyReport;
use App\Domain\Report\Notifications\DailyReportMissingNotification;
use App\Support\Time\WorkDate;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Nhắc những người hôm nay có giờ làm mà chưa nộp báo cáo.
 *
 * **Là lệnh Artisan chứ không phải Job, và điều đó có lý do kiến trúc.** Việc
 * này phải đọc cả miền Attendance (giờ làm) lẫn miền Report (báo cáo), mà hai
 * miền nghiệp vụ **không được gọi nhau** — deptrac chặn, và luật đó đúng. Tầng
 * `Console` là một trong hai chỗ được phép biết nhiều miền cùng lúc (chỗ kia là
 * `Http`). Đặt job này vào `Domain/Report/Jobs` là vi phạm ngay từ dòng `use`.
 *
 * Bản thân lệnh không gửi email đồng bộ: `PreferenceAwareNotification` đã là
 * `ShouldQueue`, nên mỗi thông báo chỉ được đẩy vào hàng đợi. Lệnh chỉ chạy vài
 * truy vấn rồi thoát.
 *
 * ## Ai được nhắc
 *
 * Chỉ người **thật sự có giờ làm hôm nay** trên hệ thống, từ mốc
 * `attendance.min_worked_minutes`. Nhắc toàn bộ nhân sự thì người nghỉ phép,
 * nghỉ ốm, đi công tác đều nhận — và chỉ cần vài lần như thế là mọi người coi
 * thông báo của hệ thống là tiếng ồn.
 *
 * Đánh đổi đã biết: người làm việc **ngoài hệ thống** cả ngày (họp, gặp khách,
 * làm trên Word) sẽ không có giờ nên **không được nhắc**, dù họ vẫn cần báo
 * cáo. Chấp nhận có chủ ý — nhắc nhầm gây hại nhiều hơn nhắc sót, và bảng đối
 * chiếu ở trang Chấm công vẫn hiện ngày trống cho quản lý thấy. Có quỹ phép ở
 * đợt 4 rồi thì mở rộng được: lúc đó mới phân biệt được "không làm" với "nghỉ
 * có phép".
 */
final class RemindMissingReportsCommand extends Command
{
    protected $signature = 'reports:remind
                            {--date= : Ngày công cần nhắc, dạng YYYY-MM-DD. Mặc định là hôm nay theo giờ Việt Nam}
                            {--dry-run : Chỉ liệt kê, không gửi gì}';

    protected $description = 'Nhắc người có giờ làm hôm nay mà chưa nộp báo cáo ngày';

    public function handle(SummariseAttendanceAction $tongHop): int
    {
        $ngay = $this->ngayCanNhac();

        /** @var list<int> $ids */
        $ids = User::query()->where('is_active', true)->pluck('id')->all();

        if ($ids === []) {
            $this->info('Không có nhân sự đang hoạt động.');

            return self::SUCCESS;
        }

        // Ai có giờ làm hôm nay, và bao nhiêu phút. Dùng lại đúng phép tính của
        // màn chấm công — viết một bản khác ở đây là chắc chắn hai bên lệch
        // nhau ngay lần đầu ai đó sửa một bên.
        $gioLam = $tongHop->execute($ids, $ngay, $ngay);

        $daNop = DailyReport::query()
            ->where('report_date', $ngay)
            ->submitted()
            ->pluck('user_id')
            ->flip();

        /*
        | Ai đang nghỉ phép hôm nay.
        |
        | Người có đơn ĐÃ DUYỆT thì không bị nhắc, kể cả khi hệ thống ghi nhận
        | vài giờ làm — mở máy trả lời một tin nhắn trong ngày nghỉ không biến
        | ngày đó thành ngày làm việc. Nhắc họ nộp báo cáo là đúng loại thông
        | báo khiến người ta tắt hết thông báo của hệ thống.
        |
        | Đọc được cả miền Leave lẫn Report vì đây là tầng Console — xem chú
        | thích đầu lớp về lý do lệnh này không phải một Job.
        */
        $dangNghi = LeaveRequest::query()
            ->whereIn('user_id', $ids)
            ->approvedBetween($ngay, $ngay)
            ->pluck('user_id')
            ->flip();

        $nguong = config()->integer('attendance.min_worked_minutes');
        $canNhac = [];

        foreach ($gioLam as $d) {
            if ($d->effectiveMinutes() < $nguong) {
                continue;
            }

            if ($daNop->has($d->userId) || $dangNghi->has($d->userId)) {
                continue;
            }

            $canNhac[$d->userId] = $d;
        }

        // Lọc người đã nhận lời nhắc SAU khi đã thu hẹp danh sách, không phải
        // trước. Xem daDuocNhac() để biết vì sao thứ tự này quan trọng.
        foreach ($this->daDuocNhac(array_keys($canNhac), $ngay) as $userId) {
            unset($canNhac[$userId]);
        }

        if ($canNhac === []) {
            $this->info("Ngày {$ngay}: không ai cần nhắc.");

            return self::SUCCESS;
        }

        /** @var EloquentCollection<int, User> $nguoi */
        $nguoi = User::query()
            ->whereIn('id', array_keys($canNhac))
            ->with('notificationSettings')
            ->get();

        foreach ($nguoi as $u) {
            /** @var DailyAttendance $d */
            $d = $canNhac[$u->id];

            $this->line(sprintf('  %s — %d phút', $u->name, $d->effectiveMinutes()));

            if ($this->option('dry-run') !== true) {
                Notification::send(
                    $u,
                    new DailyReportMissingNotification($ngay, $d->effectiveMinutes()),
                );
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Ngày %s: %d người %s.',
            $ngay,
            $nguoi->count(),
            $this->option('dry-run') === true ? 'sẽ được nhắc (chạy thử)' : 'đã được nhắc',
        ));

        return self::SUCCESS;
    }

    private function ngayCanNhac(): string
    {
        $tuyChon = $this->option('date');

        return is_string($tuyChon) && $tuyChon !== ''
            ? $tuyChon
            : WorkDate::from(Date::now());
    }

    /**
     * Trong số những người này, ai đã nhận lời nhắc cho ngày công đó rồi.
     *
     * Lịch chạy nền bắn một lần mỗi ngày, nhưng lệnh này còn chạy được bằng tay
     * — và người vận hành chạy lại để kiểm tra là chuyện bình thường. Không có
     * lớp chặn này thì lần chạy thứ hai gửi lại cho đúng những người vừa nhận,
     * và lời nhắc lập tức mất hết uy tín.
     *
     * **Nhận vào danh sách id, chứ không quét cả bảng.** Bản đầu tôi viết
     * `where('type', ...)->where('data->report_date', ...)` trên toàn bảng —
     * `EXPLAIN` cho ra `type=ALL, key=NULL`, tức quét toàn bộ. Bảng
     * `notifications` chỉ có index theo `(notifiable_type, notifiable_id)`, và
     * nó là bảng **không bao giờ bị dọn**: hai trăm người × vài thông báo mỗi
     * ngày × nhiều năm. Lúc viết thì bảng có 5 dòng nên không ai thấy gì.
     *
     * Lọc theo `report_date` trong phần dữ liệu chứ không theo ngày tạo: thông
     * báo đi qua **hàng đợi**, nên `created_at` là lúc worker xử lý, có thể rơi
     * sang hôm sau nếu hàng đợi dồn.
     *
     * Dùng query builder chứ không dùng model `DatabaseNotification`: cú pháp
     * đường dẫn JSON `data->report_date` không phải tên cột, nên Larastan mức 8
     * từ chối nó trên Eloquent builder. Ở đây cũng không cần model — chỉ lấy ra
     * một cột id.
     *
     * @param  list<int>  $userIds
     * @return list<int>
     */
    private function daDuocNhac(array $userIds, string $ngay): array
    {
        if ($userIds === []) {
            return [];
        }

        /** @var list<int> $ids */
        $ids = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->whereIn('notifiable_id', $userIds)
            ->where('type', DailyReportMissingNotification::class)
            ->where('data->report_date', $ngay)
            ->pluck('notifiable_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return $ids;
    }
}
