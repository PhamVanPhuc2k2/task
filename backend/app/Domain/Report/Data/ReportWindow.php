<?php

declare(strict_types=1);

namespace App\Domain\Report\Data;

use App\Support\Time\WorkDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

/**
 * Khoảng ngày mà một người còn được nộp báo cáo.
 *
 * Tồn tại vì **cùng một luật phải có hiệu lực ở hai chỗ**: thông báo lỗi thân
 * thiện ở `FormRequest`, và ràng buộc thật ở `SaveDailyReportAction`. Viết hai
 * lần thì sớm muộn một bên đổi mà bên kia không — và bên không đổi luôn là bên
 * thật sự chặn.
 *
 * ## Vì sao phải chặn
 *
 * Bản trước không chặn gì cả: `date_format:Y-m-d` là toàn bộ luật. Gọi thẳng
 * API thì nộp được báo cáo cho **năm 2027**, và nộp bù cả tháng trước bằng vài
 * request. Giao diện đã có `max={hôm nay}` từ đầu — tức là ý định vốn có sẵn,
 * chỉ thiếu ở chỗ có hiệu lực. Một luật chỉ nằm ở trình duyệt thì không phải là
 * luật.
 *
 * Hệ quả cụ thể: con số "số ngày thiếu báo cáo" ở trang Chấm công gian lận được
 * bằng một vòng lặp curl sát kỳ đánh giá.
 *
 * ## Ranh giới ngày là giờ Việt Nam
 *
 * Không dùng `today` của Laravel: ứng dụng chạy UTC, nên từ 00:00 tới 07:00 giờ
 * Việt Nam mỗi ngày, `today` của Laravel vẫn là hôm qua. Người làm ca sáng sớm
 * sẽ không nộp được báo cáo của chính hôm đó. Xem App\Support\Time\WorkDate.
 */
final readonly class ReportWindow
{
    private function __construct(
        public string $earliest,
        public string $latest,
    ) {}

    public static function current(): self
    {
        $homNay = WorkDate::from(Date::now());

        return new self(
            earliest: CarbonImmutable::parse($homNay, WorkDate::timezone())
                ->subDays(config()->integer('reports.backfill_days'))
                ->toDateString(),
            latest: $homNay,
        );
    }

    public function allows(string $reportDate): bool
    {
        return $reportDate >= $this->earliest && $reportDate <= $this->latest;
    }

    /**
     * Câu giải thích cho người dùng, dùng chung ở cả lỗi validate lẫn lỗi
     * nghiệp vụ để hai đường không nói hai kiểu khác nhau.
     */
    public function message(): string
    {
        return sprintf(
            'Chỉ nộp được báo cáo cho ngày từ %s đến %s. Cần bổ sung ngày cũ hơn thì trao đổi với quản lý.',
            $this->doiSangNgayViet($this->earliest),
            $this->doiSangNgayViet($this->latest),
        );
    }

    private function doiSangNgayViet(string $ngay): string
    {
        return CarbonImmutable::parse($ngay)->format('d/m/Y');
    }
}
