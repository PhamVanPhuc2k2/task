<?php

declare(strict_types=1);

use App\Domain\Task\Jobs\NotifyUpcomingDeadlinesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Lịch chạy nền
|--------------------------------------------------------------------------
|
| Container `scheduler` chạy `schedule:work`, xem docker-compose.yml.
|
*/

/*
 * Quét deadline mỗi giờ, chỉ trong giờ hành chính và chỉ ngày làm việc.
 *
 * Không quét cả ngày đêm: một thông báo "việc sắp tới hạn" gửi lúc 2 giờ sáng
 * không giúp ai làm gì, chỉ làm phiền — và người dùng sẽ tắt thông báo chứ
 * không đổi giờ ngủ.
 *
 * `timezone()` là bắt buộc. Ứng dụng chạy UTC theo quy ước dữ liệu, nên không
 * khai múi giờ thì "8 giờ sáng" thành 15 giờ chiều giờ Việt Nam.
 *
 * `withoutOverlapping()`: nếu một lượt quét chạy lâu hơn một giờ, lượt sau
 * không được chồng lên — hai lượt cùng đọc `due_soon_notified_at IS NULL` sẽ
 * gửi thông báo hai lần cho cùng một task.
 */
Schedule::job(new NotifyUpcomingDeadlinesJob, 'notifications')
    ->hourly()
    ->weekdays()
    ->between('8:00', '18:00')
    ->timezone(config()->string('app.display_timezone'))
    ->withoutOverlapping()
    ->name('quet-deadline')
    ->description('Nhắc task sắp tới hạn và task đã quá hạn');

/*
 * Nhắc nộp báo cáo ngày — chạy MỖI NGÀY, lệnh tự bỏ qua ngày không phải ngày làm.
 *
 * Từng khai `weekdays()` ở đây, và đó là một bản sao thứ hai của lịch làm việc
 * công ty. Khi công ty chuyển sang làm sáng thứ bảy, bản sao đó không ai nhớ
 * sửa — lời nhắc thứ bảy sẽ im lặng không bao giờ bắn, và không có gì báo.
 *
 * Giờ lịch tuần chỉ nằm ở `attendance.work_days_*`, và lệnh tự hỏi nó. Ngày lễ
 * cũng lọc trong lệnh, cùng lý do.
 *
 * `timezone()` bắt buộc, cùng lý do với lịch quét deadline phía trên: ứng dụng
 * chạy UTC, không khai múi giờ thì "17:30" thành 00:30 sáng hôm sau.
 */
/*
 * Dọn thông báo cũ, mỗi tuần một lần vào lúc vắng người.
 *
 * Hằng tuần chứ không hằng ngày: bảng lớn dần theo tuần chứ không theo giờ, và
 * mỗi lần chạy là một chuỗi lệnh xoá chạm vào bảng bị đọc ở mọi lần tải trang.
 * 03:15 sáng Chủ nhật — không trùng giờ nào có người dùng thật.
 */
Schedule::command('notifications:prune')
    ->weeklyOn(0, '03:15')
    ->timezone(config()->string('app.display_timezone'))
    ->withoutOverlapping()
    ->name('don-thong-bao-cu')
    ->description('Xoá thông báo đã cũ khỏi bảng notifications');

Schedule::command('reports:remind')
    ->dailyAt(config()->string('reports.reminder.at'))
    ->timezone(config()->string('app.display_timezone'))
    ->withoutOverlapping()
    ->skip(fn (): bool => ! config()->boolean('reports.reminder.enabled'))
    ->name('nhac-bao-cao-ngay')
    ->description('Nhắc người có giờ làm hôm nay mà chưa nộp báo cáo');
