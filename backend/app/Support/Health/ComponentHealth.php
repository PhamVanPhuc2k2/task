<?php

declare(strict_types=1);

namespace App\Support\Health;

use App\Support\Enums\HealthStatus;

/**
 * Kết quả kiểm tra một thành phần.
 *
 * **Không có trường nào chứa thông điệp lỗi gốc.** Endpoint health check phải
 * mở cho hệ thống giám sát gọi được mà không cần đăng nhập, nên mọi thứ ở đây
 * đều là công khai. Thông điệp lỗi của driver database thường kèm tên máy chủ,
 * tên database và tên tài khoản — đó là bản đồ tặng không cho người dò tìm.
 * Lỗi gốc đi vào log, không đi ra phản hồi.
 */
final readonly class ComponentHealth
{
    public function __construct(
        public string $name,
        public HealthStatus $status,
        public int $durationMs,
    ) {}

    /**
     * @return array<string, string|int>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status->value,
            'duration_ms' => $this->durationMs,
        ];
    }
}
