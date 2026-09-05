<?php

declare(strict_types=1);

namespace App\Domain\Leave\Models;

use App\Domain\Identity\Models\User;
use App\Support\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phần con người can thiệp vào quỹ phép năm của một người.
 *
 * Bảng thưa: không có dòng nghĩa là *"để hệ thống tự tính"* — xem migration để
 * biết vì sao không sinh sẵn dòng cho mọi người mỗi năm.
 *
 * ## Ba cột số là `float`, không phải `decimal:1`
 *
 * Quy ước của dự án là cast tiền sang `decimal` để không bao giờ làm phép cộng
 * trên số thực. Ngày phép **không phải tiền**: mọi giá trị đều là bội của 0,5,
 * mà 0,5 biểu diễn chính xác được bằng số thực nhị phân. Cast sang `decimal`
 * cho ra chuỗi, và cộng chuỗi thì mỗi chỗ dùng lại phải tự ép kiểu — đó mới là
 * chỗ sinh lỗi.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $year
 * @property float|null $entitled_days_override
 * @property float $carried_over_days
 * @property float $adjustment_days
 * @property string|null $note
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'user_id', 'year', 'entitled_days_override', 'carried_over_days',
    'adjustment_days', 'note', 'updated_by',
])]
final class LeaveBalance extends Model
{
    use HasUuid;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Dòng này có nói điều gì khác mặc định không.
     *
     * Dùng để dọn: một dòng toàn số 0 và không ghi chú thì y hệt không có dòng
     * nào, mà lại làm màn hình hiện "đã điều chỉnh" cho một người chưa ai động
     * tới.
     */
    public function isNoop(): bool
    {
        return $this->entitled_days_override === null
            && $this->carried_over_days === 0.0
            && $this->adjustment_days === 0.0
            && ($this->note === null || $this->note === '');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            // Cố ý là float — xem chú thích đầu lớp.
            'entitled_days_override' => 'float',
            'carried_over_days' => 'float',
            'adjustment_days' => 'float',
        ];
    }
}
