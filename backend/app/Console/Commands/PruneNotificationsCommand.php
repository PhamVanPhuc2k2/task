<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Xoá thông báo cũ khỏi bảng `notifications`.
 *
 * Bảng này **chỉ có lớn lên** và trước lệnh này thì không có gì dọn nó. Hai
 * trăm người × vài thông báo mỗi ngày × nhiều năm — riêng lời nhắc báo cáo đã
 * là ~53.000 dòng một năm. Không ai đọc lại thông báo của quý trước, nhưng
 * chúng vẫn nằm đó làm chậm mọi truy vấn chạm tới bảng, kể cả cái đếm số chưa
 * đọc chạy trên mỗi lần tải trang.
 *
 * **Không phải nhật ký kiểm toán.** Ai làm gì với nhân sự nằm ở
 * `user_activities`, ai đụng vào lương nằm ở `payroll_audits` — hai bảng đó
 * không bị lệnh này chạm tới, và không được phép có lệnh nào dọn chúng.
 *
 * ## Xoá theo lô
 *
 * `DELETE` một phát trên vài trăm nghìn dòng khoá bảng đủ lâu để mọi request
 * đang chạy phải xếp hàng chờ — và bảng này bị chạm ở mỗi lần tải trang. Xoá
 * từng lô 1.000 dòng thì mỗi lần khoá chỉ vài mili-giây.
 */
final class PruneNotificationsCommand extends Command
{
    private const int LO = 1000;

    protected $signature = 'notifications:prune {--dry-run : Chỉ đếm, không xoá}';

    protected $description = 'Xoá thông báo đã cũ (đã đọc và chưa đọc có mốc khác nhau)';

    public function handle(): int
    {
        $bayGio = Date::now();

        $mocDaDoc = $bayGio->copy()->subDays(
            config()->integer('notifications.prune.read_after_days'),
        );

        $mocChuaDoc = $bayGio->copy()->subDays(
            config()->integer('notifications.prune.unread_after_days'),
        );

        $daDoc = $this->xoa(
            fn () => DB::table('notifications')
                ->whereNotNull('read_at')
                ->where('created_at', '<', $mocDaDoc),
        );

        $chuaDoc = $this->xoa(
            fn () => DB::table('notifications')
                ->whereNull('read_at')
                ->where('created_at', '<', $mocChuaDoc),
        );

        $this->info(sprintf(
            '%s %d thông báo đã đọc (cũ hơn %s) và %d chưa đọc (cũ hơn %s).',
            $this->option('dry-run') === true ? 'Sẽ xoá' : 'Đã xoá',
            $daDoc,
            $mocDaDoc->format('d/m/Y'),
            $chuaDoc,
            $mocChuaDoc->format('d/m/Y'),
        ));

        return self::SUCCESS;
    }

    /**
     * @param  callable(): Builder  $truyVan
     */
    private function xoa(callable $truyVan): int
    {
        if ($this->option('dry-run') === true) {
            return $truyVan()->count();
        }

        $tong = 0;

        // Lặp cho tới khi lô cuối trả về ít hơn kích thước lô — lúc đó là hết.
        do {
            $so = $truyVan()->limit(self::LO)->delete();
            $tong += $so;
        } while ($so === self::LO);

        return $tong;
    }
}
