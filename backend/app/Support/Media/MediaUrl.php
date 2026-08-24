<?php

declare(strict_types=1);

namespace App\Support\Media;

use DateTimeInterface;
use Illuminate\Support\Facades\Config;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Sinh đường dẫn xem tệp đính kèm.
 *
 * Tồn tại vì **hai kiểu ổ đĩa cho ra hai kiểu URL khác nhau**, và gọi thẳng
 * `getUrl()` ở mọi nơi thì chỉ đúng với một kiểu:
 *
 *   - Ổ có `url` (đĩa `public` khi dev, hoặc R2 đã gắn custom domain): tệp đọc
 *     được công khai, `getUrl()` cho một đường dẫn ổn định, trình duyệt cache
 *     lại được.
 *   - Ổ không có `url` (bucket R2 riêng tư — mặc định của dự án): `getUrl()`
 *     ghép ra `{endpoint}/{bucket}/{path}`, và địa chỉ đó luôn trả 403 vì R2
 *     không mở cho người lạ. Phải ký một đường dẫn có hạn.
 *
 * Chọn theo cấu hình chứ không theo tên ổ: gắn custom domain vào R2 là đổi một
 * biến môi trường, không phải sửa code.
 *
 * Đánh đổi đã biết của đường dẫn ký: **ai cầm được đường dẫn là xem được tệp**,
 * không cần đăng nhập, cho tới khi hết hạn. Chuyển tiếp một link ảnh báo cáo ra
 * ngoài công ty là nó dùng được. Đây là lý do hạn để ngắn (mặc định 30 phút)
 * thay vì vài ngày. Muốn chặn hẳn thì phải cho ảnh đi qua một endpoint của mình
 * và kiểm tra Policy từng lần tải — ghi ở README mục 1.9 là việc còn để ngỏ.
 *
 * Đánh đổi thứ hai: đường dẫn ký **đổi sau mỗi lần gọi API** (chữ ký gắn mốc
 * thời gian), nên trình duyệt tải lại ảnh mỗi lần danh sách bình luận làm mới.
 * Chấp nhận được với ảnh nội bộ cỡ nhỏ; nếu sau này thành vấn đề thì lời giải
 * là endpoint chuyển tiếp nói trên, không phải kéo dài hạn.
 */
final class MediaUrl
{
    /**
     * @param  string  $conversion  Tên bản chuyển đổi, rỗng là bản gốc.
     */
    public static function for(Media $media, string $conversion = ''): string
    {
        return self::congKhai($media, $conversion)
            ? $media->getUrl($conversion)
            : $media->getTemporaryUrl(self::hetHan(), $conversion);
    }

    /**
     * Ổ đĩa đang giữ tệp này có địa chỉ công khai không.
     *
     * Đọc theo đúng ổ ghi trên bản ghi (`disk` / `conversions_disk`) chứ không
     * đọc ổ mặc định trong cấu hình. Hai lý do:
     *
     *   - Tệp tải lên hồi còn để `MEDIA_DISK=public` vẫn nằm ở đĩa cũ sau khi
     *     đổi sang R2, và chúng vẫn phải xem được.
     *   - Bản gốc và bản thu nhỏ được phép nằm ở hai ổ khác nhau
     *     (`MEDIA_CONVERSIONS_DISK`).
     */
    private static function congKhai(Media $media, string $conversion): bool
    {
        $disk = $conversion === ''
            ? $media->disk
            : ($media->conversions_disk ?? $media->disk);

        /*
        | Chuỗi RỖNG cũng là "không công khai", không chỉ `null`.
        |
        | Bẫy thật, đã mắc khi bật R2 lần đầu: dòng `R2_PUBLIC_URL=` bỏ trống
        | trong .env cho ra chuỗi rỗng chứ không phải null, nên phép kiểm
        | `!== null` kết luận một bucket riêng tư là công khai.
        |
        | Hỏng im lặng đúng kiểu tệ nhất: tệp vẫn tải lên được, `getUrl()` vẫn
        | trả về một chuỗi, không ngoại lệ nào. Chỉ là chuỗi đó là đường dẫn
        | tương đối `/3/tep.txt` — hỏng với mọi người xem, và không ai biết cho
        | tới khi có người bấm vào.
        |
        | Một dòng env bỏ trống là thứ quá dễ xảy ra để phụ thuộc vào việc
        | người cấu hình nhớ xoá hẳn dòng đó.
        */
        $url = Config::get("filesystems.disks.{$disk}.url");

        return is_string($url) && trim($url) !== '';
    }

    private static function hetHan(): DateTimeInterface
    {
        return now()->addMinutes(
            Config::integer('media-library.temporary_url_default_lifetime'),
        );
    }
}
