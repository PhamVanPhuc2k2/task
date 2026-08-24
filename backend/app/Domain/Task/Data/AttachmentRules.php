<?php

declare(strict_types=1);

namespace App\Domain\Task\Data;

/**
 * Luật cho tệp đính kèm của bình luận.
 *
 * Gom vào một chỗ vì cùng bộ luật này phải áp ở ba nơi: Form Request kiểm lúc
 * nhận, media collection của Spatie kiểm lúc lưu, và giao diện dùng để đặt
 * `accept` cho ô chọn tệp. Ba chỗ khai báo riêng thì sớm muộn cũng lệch nhau,
 * và chỗ lỏng nhất chính là lỗ hổng.
 *
 * Đây là **danh sách trắng**: không có trong danh sách thì bị từ chối. Danh
 * sách đen luôn thiếu — người tấn công chỉ cần một phần mở rộng mà mình chưa
 * nghĩ tới.
 */
final class AttachmentRules
{
    /** 10 MB. Phải khớp với `media-library.max_file_size` và `client_max_body_size` của Nginx. */
    public const int MAX_BYTES = 10 * 1024 * 1024;

    /** Số tệp tối đa cho một lượt tải lên. */
    public const int MAX_PER_REQUEST = 5;

    /**
     * Kiểu MIME được nhận.
     *
     * KHÔNG có `image/svg+xml`: tệp SVG chứa được JavaScript và chạy trong ngữ
     * cảnh tên miền của mình khi mở trực tiếp — tức là XSS lưu trữ. Muốn nhận
     * SVG thì phải phục vụ dưới Content-Type vô hại, việc đó để mục 1.9.
     *
     * @return list<string>
     */
    public static function mimeTypes(): array
    {
        return [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/csv',
            'application/zip',
        ];
    }

    /**
     * Kiểu ảnh — quyết định có sinh thumbnail hay không.
     *
     * @return list<string>
     */
    public static function imageMimeTypes(): array
    {
        return ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    }

    public static function isImage(string $mimeType): bool
    {
        return in_array($mimeType, self::imageMimeTypes(), strict: true);
    }
}
