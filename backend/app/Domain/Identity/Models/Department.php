<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Support\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Database\Factories\Identity\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phòng ban, tổ chức thành cây qua `parent_id`.
 *
 * Cây này là nền của phân quyền theo phạm vi: một trưởng phòng xem được task
 * của phòng mình và mọi phòng trực thuộc bên dưới, ở mọi độ sâu.
 *
 * `@property` là bắt buộc theo quy ước dự án — xem README, "Khối @property trên
 * model". Không có nó thì Larastan phải suy kiểu từ migration, và khi bộ quét
 * migration hỏng thì mọi chỗ đọc `$phong->name` đều báo lỗi.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $code
 * @property string|null $description
 * @property bool $is_active
 * @property CarbonImmutable|null $deleted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * `withCount()` gắn thêm thuộc tính lúc chạy, không có cột nào trong migration
 * để Larastan suy ra — phải khai tay, nếu không mọi chỗ đọc chúng đều báo lỗi.
 * @property int|null $children_count
 * @property int|null $users_count
 */
#[Fillable(['parent_id', 'name', 'code', 'description', 'is_active'])]
final class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    use HasUuid;
    use SoftDeletes;

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Id của mọi phòng ban cấp dưới, ở mọi độ sâu. Không gồm chính nó.
     *
     * Nạp toàn bộ cây trong một truy vấn rồi duyệt trong PHP, thay vì đệ quy
     * bằng truy vấn (N+1) hoặc CTE đệ quy. Số phòng ban của một công ty là
     * hàng chục, không phải hàng triệu — cách này đọc dễ hơn hẳn mà vẫn nhanh.
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        /** @var array<int, list<int>> $childrenByParent */
        $childrenByParent = [];

        foreach (self::query()->get(['id', 'parent_id']) as $row) {
            $childrenByParent[(int) ($row->parent_id ?? 0)][] = (int) $row->id;
        }

        /*
        | `$daTham` không phải để tối ưu — nó là phanh hãm.
        |
        | Không có nó, một vòng trong cây (A là cha của B, rồi B được đặt làm
        | cha của A) khiến hàng đợi không bao giờ rỗng: A đẩy B, B đẩy A, mãi
        | mãi. `$found` phình tới khi hết bộ nhớ.
        |
        | Và nó KHÔNG hỏng kiểu dễ thấy: request treo, php-fpm giữ tiến trình
        | đó cho tới khi hết timeout, log không có dòng nào. Mà hàm này đỡ 13
        | chỗ phân quyền, nên một bản ghi hỏng làm chết cả chấm công lẫn nghỉ
        | phép lẫn báo cáo.
        |
        | `UpdateDepartmentAction` đã chặn không cho tạo vòng. Đây là lớp thứ
        | hai: dữ liệu vẫn có thể vào bằng đường khác — nhập tay bằng SQL, một
        | migration sau này, hay một lỗi ở đúng chỗ chặn kia.
        */
        $found = [];
        $daTham = [(int) $this->id => true];
        $queue = [(int) $this->id];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($childrenByParent[$current] ?? [] as $childId) {
                if (isset($daTham[$childId])) {
                    continue;
                }

                $daTham[$childId] = true;
                $found[] = $childId;
                $queue[] = $childId;
            }
        }

        return $found;
    }

    /**
     * Phạm vi quản lý: chính phòng ban này cộng toàn bộ cấp dưới.
     *
     * Không đặt tên `scopeIds()` — Eloquent coi mọi phương thức bắt đầu bằng
     * `scope` là query scope và sẽ gọi nó với tham số Builder.
     *
     * @return list<int>
     */
    public function subtreeIds(): array
    {
        return [(int) $this->id, ...$this->descendantIds()];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
