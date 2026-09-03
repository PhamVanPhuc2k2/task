<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Attendance\Actions\SummariseAttendanceAction;
use App\Domain\Attendance\Data\WorkWeek;
use App\Domain\Attendance\Models\Holiday;
use App\Domain\Identity\Models\User;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Report\Models\DailyReport;
use App\Domain\Report\Notifications\DailyReportMissingNotification;
use App\Domain\Task\Models\Task;
use App\Support\Time\WorkDate;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Nhắc những người hôm nay phải nộp báo cáo mà chưa nộp.
 *
 * **Là lệnh Artisan chứ không phải Job, và điều đó có lý do kiến trúc.** Việc
 * này phải đọc bốn miền cùng lúc — Attendance, Leave, Task, Report — mà các
 * miền nghiệp vụ **không được gọi nhau** (deptrac chặn, và luật đó đúng). Tầng
 * `Console` là một trong hai chỗ được phép biết nhiều miền (chỗ kia là `Http`).
 * Đặt job này vào `Domain/Report/Jobs` là vi phạm ngay từ dòng `use`.
 *
 * Bản thân lệnh không gửi email đồng bộ: `PreferenceAwareNotification` đã là
 * `ShouldQueue`, nên mỗi thông báo chỉ được đẩy vào hàng đợi.
 *
 * ## Nghĩa vụ báo cáo đến từ LỊCH LÀM VIỆC, không đến từ phép đo
 *
 * Bản trước chỉ nhắc người có giờ làm đo được trên hệ thống. Cách đó bỏ sót
 * đúng nhóm cần nhắc nhất: người đi gặp khách, đi họp với đối tác, hướng dẫn
 * khách vận hành website — họ không thể treo trình duyệt để có giờ, nhưng vẫn
 * phải báo cáo, và thường là báo cáo đáng đọc nhất trong ngày.
 *
 * Đó là cùng một lỗi mà miền Attendance đã sửa một lần rồi: lấy một dấu vết kỹ
 * thuật làm đại diện cho một sự thật nghiệp vụ. Chấm công từng đo "thời gian có
 * thao tác trên Explus" rồi bỏ vì nó đo sai người. Ở đây cũng vậy — giờ công
 * trả lời câu hỏi *làm việc thế nào*, còn nghĩa vụ báo cáo là câu hỏi *hôm nay
 * có phải ngày làm việc của người này không*. Hai câu hỏi khác nhau.
 *
 * Nên giờ công **không còn tham gia** vào việc quyết định ai bị nhắc. Nó chỉ
 * còn dùng để chọn câu chữ cho lời nhắc.
 *
 * ## Ai được nhắc
 *
 * Người thoả **tất cả** những điều dưới đây:
 *
 *   0. Hôm đó là ngày làm việc theo lịch tuần, và không phải ngày lễ
 *   1. Tài khoản đang hoạt động
 *   2. `joined_at` đã tới — chưa đi làm thì chưa có gì để báo cáo
 *   3. Đã từng được giao ít nhất một task — xem `daTungCoViec()`
 *   4. Không có đơn nghỉ ĐÃ DUYỆT cho ngày đó
 *   5. Chưa nộp báo cáo cho ngày đó
 *   6. Chưa nhận lời nhắc cho ngày đó
 *
 * Ngày nghỉ hằng tuần và ngày lễ đều lọc trong lệnh chứ không ở lịch chạy:
 * lịch làm việc của công ty chỉ nên có MỘT nguồn sự thật.
 *
 * ## Đánh đổi đã biết, nói thẳng
 *
 * Người vắng mặt **không phép** vẫn bị nhắc. Chấp nhận có chủ ý: không có tín
 * hiệu nào phân biệt được "nghỉ không phép" với "đi làm ở ngoài", nên phải chọn
 * một phía để sai. Bỏ sót một người đi làm thật thì mất hẳn một báo cáo; nhắc
 * dư một người nghỉ không phép thì họ nhận một dòng nhắc — mà họ đang nợ công
 * ty một trong hai thứ, báo cáo hoặc đơn nghỉ, nên cũng không oan.
 */
final class RemindMissingReportsCommand extends Command
{
    protected $signature = 'reports:remind
                            {--date= : Ngày công cần nhắc, dạng YYYY-MM-DD. Mặc định là hôm nay theo giờ Việt Nam}
                            {--dry-run : Chỉ liệt kê, không gửi gì}';

    protected $description = 'Nhắc người phải nộp báo cáo ngày mà chưa nộp';

    public function handle(SummariseAttendanceAction $tongHop): int
    {
        $ngay = $this->ngayCanNhac();

        /*
        | Ngày lễ phải kiểm TƯỜNG MINH kể từ bản này.
        |
        | Trước đây không cần: điều kiện "phải có giờ làm" tự nó đã lọc ngày lễ,
        | vì cả công ty nghỉ thì không ai có giờ. Bỏ điều kiện đó là mất luôn lá
        | chắn ấy — và hậu quả là nhắc cả công ty nộp báo cáo vào mùng 1 Tết.
        |
        | Đây đúng loại hệ quả không nhìn thấy khi đọc diff: dòng bị xoá nằm ở
        | một chỗ, còn thứ nó vô tình bảo vệ nằm ở chỗ khác.
        */
        if ($this->laNgayLe($ngay)) {
            $this->info("Ngày {$ngay} là ngày lễ — không nhắc ai.");

            return self::SUCCESS;
        }

        /*
        | Ngày nghỉ hằng tuần lọc ở ĐÂY, không lọc ở lịch chạy.
        |
        | Trước đây `routes/console.php` khai `weekdays()`, tức là lịch làm việc
        | của công ty nằm ở hai chỗ: một bản trong cấu hình chấm công, một bản
        | cứng trong lịch chạy. Công ty chuyển sang làm sáng thứ bảy thì bản thứ
        | hai không ai nhớ mà sửa, và lời nhắc thứ bảy im lặng không bao giờ
        | bắn. Giờ lệnh chạy mỗi ngày và tự hỏi lịch tuần — một nguồn sự thật.
        */
        if (! WorkWeek::fromConfig()->isWorkingDay($ngay)) {
            $this->info("Ngày {$ngay} không phải ngày làm việc — không nhắc ai.");

            return self::SUCCESS;
        }

        $ids = $this->dienPhaiNop($ngay);

        if ($ids === []) {
            $this->info('Không có ai thuộc diện phải nộp báo cáo.');

            return self::SUCCESS;
        }

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
        */
        $dangNghi = LeaveRequest::query()
            ->whereIn('user_id', $ids)
            ->approvedBetween($ngay, $ngay)
            ->pluck('user_id')
            ->flip();

        $canNhac = [];

        foreach ($ids as $id) {
            if ($daNop->has($id) || $dangNghi->has($id)) {
                continue;
            }

            $canNhac[$id] = true;
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

        /** @var list<int> $canNhacIds */
        $canNhacIds = array_keys($canNhac);

        /*
        | Giờ làm CHỈ để chọn câu chữ, không còn quyết định ai bị nhắc.
        |
        | Có giờ thì lời nhắc nói ra con số — nó giúp người đọc nhớ lại hôm nay
        | mình đã làm gì. Không đo được giờ nào thì câu chữ đổi hẳn: xem
        | DailyReportMissingNotification để biết vì sao không được nói "0 phút".
        */
        $gioLam = $tongHop->execute($canNhacIds, $ngay, $ngay)->keyBy('userId');

        /** @var EloquentCollection<int, User> $nguoi */
        $nguoi = User::query()
            ->whereIn('id', $canNhacIds)
            ->with('notificationSettings')
            ->orderBy('name')
            ->get();

        foreach ($nguoi as $u) {
            $phut = $gioLam->get($u->id)?->effectiveMinutes() ?? 0;

            $this->line(sprintf(
                '  %s — %s',
                $u->name,
                $phut > 0 ? $phut.' phút' : 'không đo được giờ',
            ));

            if ($this->option('dry-run') !== true) {
                Notification::send($u, new DailyReportMissingNotification($ngay, $phut));
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
     * Ngày lễ theo bảng `holidays`.
     *
     * Dùng `observed_date` chứ không dùng `date`: lễ trùng ngày nghỉ hằng tuần
     * thì nghỉ bù vào ngày làm việc kế tiếp theo khoản 3 Điều 112, và ngày nghỉ
     * bù mới là ngày không ai đi làm.
     */
    private function laNgayLe(string $ngay): bool
    {
        return Holiday::query()->where('observed_date', $ngay)->exists();
    }

    /**
     * Những người thuộc diện phải nộp báo cáo cho ngày đó.
     *
     * `joined_at` để trống nghĩa là **vẫn tính**, không phải bỏ qua. Nhân sự
     * nhập từ CSV đợt đầu có thể thiếu cột này, và một luật im lặng ngừng nhắc
     * cả nhóm đó thì không ai phát hiện ra — mặc định an toàn là cứ nhắc.
     *
     * @return list<int>
     */
    private function dienPhaiNop(string $ngay): array
    {
        /** @var list<int> $ids */
        $ids = User::query()
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q
                ->whereNull('joined_at')
                ->orWhere('joined_at', '<=', $ngay))
            ->whereIn('id', $this->daTungCoViec())
            ->pluck('id')
            ->all();

        return $ids;
    }

    /**
     * Truy vấn con: những người đã từng được giao ít nhất một task.
     *
     * ## Vì sao là "ĐÃ TỪNG được giao", không phải "hôm nay có task"
     *
     * Điều kiện này chỉ làm đúng một việc: phân biệt người **đã thật sự bắt đầu
     * làm việc** với người vừa được tạo tài khoản. Nó không suy đoán hôm nay họ
     * bận gì — và không suy đoán thì không suy đoán sai được.
     *
     * Ràng buộc theo "hôm nay có task" nghe hợp lý hơn nhưng hỏng ở ba chỗ:
     * người đi gặp khách mà việc đó không được tạo thành task sẽ bị bỏ sót
     * (đúng lỗi mà bản này sinh ra để sửa); người vừa đóng hết task và đang chờ
     * giao tiếp cũng bị bỏ sót; và tệ nhất là nếu quản lý lười tạo task thì lời
     * nhắc **tự tắt dần mà không có gì báo**.
     *
     * ## `withTrashed()`, có chủ ý
     *
     * "Đã từng được giao việc" là một sự thật lịch sử. Một task bị xoá sau đó
     * không làm cho người ta chưa từng đi làm.
     *
     * @return Builder<Task>
     */
    private function daTungCoViec(): Builder
    {
        return Task::withTrashed()
            ->select('assignee_id')
            ->whereNotNull('assignee_id');
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
