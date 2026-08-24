<?php

declare(strict_types=1);

namespace App\Domain\Identity\Data;

use App\Domain\Identity\Enums\Role;

final readonly class CreateUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $employeeCode,
        public Role $role,
        public ?string $phone = null,
        public ?int $departmentId = null,
        public ?int $positionId = null,
        public ?int $managerId = null,
        public ?string $joinedAt = null,
    ) {}
}
