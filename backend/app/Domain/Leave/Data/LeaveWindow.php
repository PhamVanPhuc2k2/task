<?php

declare(strict_types=1);

namespace App\Domain\Leave\Data;

use App\Support\Time\WorkDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

/**
 * Khoảng ngày còn nộp đơn nghỉ được.
 *
 * Cùng khuôn với `App\Domain\Report\Data\ReportWindow`, và cùng lý do: một luật
 * phải có hiệu lực ở hai chỗ — thông báo lỗi thân thiện ở `FormRequest`, và
 * ràng buộc thật ở Action. Viết hai lần thì sớm muộn một bên đổi mà bên kia
 * không, và bên không đổi luôn là bên thật sự chặn.
 *
 * Mốc lấy theo **giờ Việt Nam**, không theo `today` của Laravel: ứng dụng chạy
 * UTC, nên từ 00:00 tới 07:00 giờ Việt Nam mỗi ngày `today` vẫn còn là hôm qua.
 */
final readonly class LeaveWindow
{
    private function __construct(
        public string $earliest,
        public string $latest,
        public int $maxDays,
    ) {}

    public static function current(): self
    {
        $homNay = CarbonImmutable::parse(
            WorkDate::from(Date::now()),
            WorkDate::timezone(),
        );

        return new self(
            earliest: $homNay->subDays(config()->integer('leave.backdate_days'))->toDateString(),
            latest: $homNay->addDays(config()->integer('leave.future_days'))->toDateString(),
            maxDays: config()->integer('leave.max_days_per_request'),
        );
    }

    public function allows(string $tu, string $den): bool
    {
        return $tu >= $this->earliest && $den <= $this->latest;
    }

    /** Đơn có dài quá mức cho phép không — chặn lỗi gõ nhầm năm. */
    public function tooLong(string $tu, string $den): bool
    {
        $so = (int) CarbonImmutable::parse($tu)
            ->diffInDays(CarbonImmutable::parse($den)) + 1;

        return $so > $this->maxDays;
    }

    public function message(): string
    {
        return sprintf(
            'Chỉ nộp được đơn nghỉ cho ngày từ %s đến %s.',
            $this->doiSangNgayViet($this->earliest),
            $this->doiSangNgayViet($this->latest),
        );
    }

    public function tooLongMessage(): string
    {
        return sprintf('Một đơn nghỉ tối đa %d ngày.', $this->maxDays);
    }

    private function doiSangNgayViet(string $ngay): string
    {
        return CarbonImmutable::parse($ngay)->format('d/m/Y');
    }
}
