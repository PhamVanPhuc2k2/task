<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use RuntimeException;

/**
 * Lỗi nghiệp vụ có thể hiển thị cho người dùng.
 *
 * Tầng Domain ném lớp này; tầng Http bắt và dựng thành dạng lỗi thống nhất
 * đã chốt ở README mục 1.4:
 *
 *     { "message": "...", "code": "TASK_NOT_FOUND", "errors": { ... } }
 *
 * Lớp này nằm ở Support vì nó thuần kỹ thuật — không mang nghiệp vụ của miền
 * nào, và mọi miền đều dùng chung.
 */
abstract class DomainException extends RuntimeException
{
    /**
     * Mã lỗi nội bộ để frontend xử lý theo trường hợp.
     * Quy ước: CHỮ_HOA_CÓ_GẠCH_DƯỚI.
     */
    abstract public function errorCode(): string;

    /**
     * Mã HTTP tương ứng. Mặc định 422 — dữ liệu hợp lệ về mặt hình thức
     * nhưng vi phạm quy tắc nghiệp vụ.
     */
    public function httpStatus(): int
    {
        return 422;
    }

    /**
     * Lỗi theo từng field, nếu có. Dùng để hiển thị cạnh ô nhập.
     *
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return [];
    }
}
