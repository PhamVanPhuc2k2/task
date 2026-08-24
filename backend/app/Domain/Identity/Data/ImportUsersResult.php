<?php

declare(strict_types=1);

namespace App\Domain\Identity\Data;

/**
 * Kết quả một lần nhập danh sách nhân viên.
 *
 * @param  list<string>  $errors  Mô tả từng dòng bị bỏ qua, kèm số dòng.
 */
final readonly class ImportUsersResult
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $skipped = 0,
        public array $errors = [],
    ) {}
}
