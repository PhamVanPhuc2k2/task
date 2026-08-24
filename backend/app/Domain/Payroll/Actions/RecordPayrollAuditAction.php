<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Payroll\Enums\PayrollAuditEvent;
use App\Domain\Payroll\Models\PayrollAudit;

/**
 * Ghi một dòng vào nhật ký lương.
 *
 * Gọi tường minh từ mỗi chỗ đọc và ghi lương, không dùng Observer: Observer chỉ
 * thấy được thao tác GHI, mà thứ cần ghi lại ở đây gồm cả thao tác ĐỌC.
 */
final class RecordPayrollAuditAction
{
    /**
     * @param  array<string, mixed>|null  $context
     */
    public function execute(
        PayrollAuditEvent $event,
        ?User $actor,
        ?User $subject = null,
        ?array $context = null,
    ): PayrollAudit {
        return PayrollAudit::query()->create([
            'event' => $event,
            'actor_id' => $actor?->id,
            'subject_id' => $subject?->id,
            'context' => $context,
        ]);
    }
}
