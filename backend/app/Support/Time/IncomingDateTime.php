<?php

declare(strict_types=1);

namespace App\Support\Time;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

/**
 * Chuẩn hoá mốc thời gian nhận từ client về UTC.
 *
 * **Vì sao phải có lớp này.** Cast `datetime` của Eloquent lưu chuỗi bằng
 * `Carbon::parse($value)->format('Y-m-d H:i:s')` — nó KHÔNG đổi về UTC. Hậu quả
 * là hai lỗi cùng một gốc:
 *
 * 1. Client gửi `2026-08-07T20:00:00+07:00` → Carbon hiểu đúng là 13:00 UTC,
 *    nhưng `format()` in ra giờ địa phương `20:00` và đúng chuỗi đó rơi vào
 *    cột. Offset bị nuốt mất, và mốc lưu lại lệch 7 tiếng.
 * 2. Client gửi `2026-08-07T20:00` (ô `datetime-local` của trình duyệt không
 *    kèm offset) → Carbon hiểu là 20:00 **UTC**, tức 03:00 sáng hôm sau giờ
 *    Việt Nam. Người dùng đặt hạn 8 giờ tối, hệ thống hiểu thành 3 giờ sáng.
 *
 * Lỗi loại này không có gì báo: dữ liệu vẫn lưu, vẫn đọc ra được, chỉ sai giờ.
 * Nó chỉ lộ khi có người đọc một mốc cụ thể và thấy lệch — mà lúc đó bảng chấm
 * công và chỉ số đúng hạn đã sai từ lâu.
 *
 * **Quy ước.** Chuỗi có offset thì tin offset đó. Chuỗi không có offset thì
 * hiểu theo giờ hiển thị của công ty (`app.display_timezone`), vì toàn bộ giao
 * diện nói chuyện với người dùng bằng giờ Việt Nam. Kết quả luôn là UTC — xem
 * README, "Quy ước dữ liệu, thời gian & tiền tệ".
 */
final class IncomingDateTime
{
    /** Nhận biết offset ở cuối chuỗi: `Z`, `+07:00`, `+0700`, `-05`. */
    private const string CO_OFFSET = '/(?:Z|[+-]\d{2}(?::?\d{2})?)$/i';

    public static function toUtc(?string $value): ?CarbonImmutable
    {
        $value = $value === null ? null : trim($value);

        if ($value === null || $value === '') {
            return null;
        }

        $mui = preg_match(self::CO_OFFSET, $value) === 1
            ? null
            : config()->string('app.display_timezone');

        return CarbonImmutable::parse(Date::parse($value, $mui))->utc();
    }
}
