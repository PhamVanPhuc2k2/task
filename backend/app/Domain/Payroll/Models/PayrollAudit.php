<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Payroll\Enums\PayrollAuditEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một dòng nhật ký lương: ai làm gì, với ai, lúc nào.
 *
 * Chỉ ghi thêm. Không có `updated_at` vì không có gì để cập nhật.
 *
 * **Không chứa số tiền.** Nhật ký kiểm toán mang theo dữ liệu nhạy cảm thì bản
 * thân nó thành chỗ rò rỉ thứ hai — ai đọc được nhật ký sẽ biết lương cả công
 * ty mà không cần quyền xem lương. Cùng nguyên tắc với nhật ký đặt lại mật khẩu
 * ở phần nhân sự.
 *
 * @property int $id
 * @property PayrollAuditEvent $event
 * @property int|null $actor_id
 * @property int|null $subject_id
 * @property array<string, mixed>|null $context
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['event', 'actor_id', 'subject_id', 'context'])]
final class PayrollAudit extends Model
{
    public const UPDATED_AT = null;

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => PayrollAuditEvent::class,
            'context' => 'array',
        ];
    }
}
