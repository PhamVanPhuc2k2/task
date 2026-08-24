<?php

declare(strict_types=1);

namespace App\Domain\Identity\Contracts;

/**
 * Thông tin trả về ở bước thiết lập xác thực hai lớp.
 *
 * Chỉ một trong hai nhóm trường có giá trị, tuỳ kênh đang dùng:
 *   - Email: `sentTo` là địa chỉ đã gửi mã tới; `qrCodeSvg` và `secret` là null
 *   - TOTP:  `qrCodeSvg` và `secret` để quét hoặc nhập tay; `sentTo` là null
 */
final readonly class SetupPayload
{
    public function __construct(
        public string $instructions,
        public ?string $secret = null,
        public ?string $qrCodeSvg = null,
        public ?string $sentTo = null,
    ) {}
}
