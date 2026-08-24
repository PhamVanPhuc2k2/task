<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Models;

use App\Domain\Identity\Models\User;
use App\Support\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một mức lương có hiệu lực trong một khoảng thời gian.
 *
 * Chỉ ghi thêm: tăng lương là tạo dòng mới và đóng dòng cũ, không sửa dòng cũ.
 * Nhờ vậy tính lại bảng lương của bất kỳ tháng nào trong quá khứ vẫn ra đúng
 * mức lương hiệu lực lúc đó.
 *
 * `@property` là bắt buộc theo quy ước dự án: thiếu thì Larastan suy kiểu từ
 * migration và hiểu sai mọi cột có cast (xem README mục 1.4).
 *
 * `base_salary` và `allowance` là **chuỗi**, không phải float — `decimal:2` giữ
 * nguyên độ chính xác của DECIMAL. Xem README, "Quy ước dữ liệu, thời gian &
 * tiền tệ".
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable|null $effective_to
 * @property string $base_salary
 * @property string $allowance
 * @property string $currency
 * @property string $reason
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'user_id', 'effective_from', 'effective_to', 'base_salary',
    'allowance', 'currency', 'reason', 'created_by',
])]
final class SalaryRecord extends Model
{
    use HasUuid;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Người đặt mức lương này. */
    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Mức đang hiệu lực — dòng chưa bị đóng.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNull('effective_to');
    }

    /**
     * Tổng thu nhập tháng theo mức này.
     *
     * `bcadd` chứ không phải `+`: cộng hai chuỗi tiền bằng toán tử số học của
     * PHP là ép cả hai sang float, và `12500000.10 + 2000000.20` ra
     * `14500000.299999999` — đúng cái sai số mà kiểu DECIMAL sinh ra để tránh.
     *
     * `numeric-string` cho phân tích tĩnh: cast `decimal:2` luôn trả chuỗi số,
     * nhưng kiểu trả về khai báo của Eloquent chỉ là `string`.
     */
    public function total(): string
    {
        /** @var numeric-string $goc */
        $goc = $this->base_salary;
        /** @var numeric-string $phuCap */
        $phuCap = $this->allowance;

        return bcadd($goc, $phuCap, 2);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'base_salary' => 'decimal:2',
            'allowance' => 'decimal:2',
        ];
    }
}
