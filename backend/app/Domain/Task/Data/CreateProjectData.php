<?php

declare(strict_types=1);

namespace App\Domain\Task\Data;

use App\Domain\Task\Enums\ProjectStatus;

final readonly class CreateProjectData
{
    public function __construct(
        public string $name,
        public ?string $code = null,
        public ?string $description = null,
        public ?int $ownerId = null,
        public ?int $departmentId = null,
        public ProjectStatus $status = ProjectStatus::Planning,
        public ?string $startDate = null,
        public ?string $endDate = null,
    ) {}
}
