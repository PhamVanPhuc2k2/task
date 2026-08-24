<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\NotificationChannel;
use App\Domain\Identity\Enums\NotificationType;
use App\Domain\Identity\Notifications\ResetPasswordNotification;
use App\Support\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Database\Factories\Identity\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Người dùng hệ thống.
 *
 * Model chỉ khai báo quan hệ, cast và scope. Mọi nghiệp vụ nằm ở Action —
 * xem README, mục "Quy tắc phụ thuộc".
 *
 * Đây là nguồn sự thật duy nhất về nhân sự: hệ thống tự quản lý hoàn toàn,
 * không đọc và không đồng bộ từ hệ thống nào khác. Vì không có nguồn dữ liệu
 * bên ngoài nên cũng không có interface bọc quanh — chỉ là model Eloquent.
 *
 * Khối `@property` liệt kê ĐỦ mọi cột, không chỉ những cột có cast lạ.
 *
 * Bản trước chỉ khai sáu dòng cho phần Larastan suy sai từ migration, và để nó
 * tự suy phần còn lại. Cách đó chạy được cho tới ngày bộ quét migration của
 * Larastan hỏng — khi ấy `$user->name` cũng thành "thuộc tính không tồn tại",
 * và 717 lỗi đổ ra ở khắp nơi trừ chỗ thật sự có vấn đề.
 *
 * README đã ghi quy tắc này từ đầu ("Khối @property trên model — **bắt buộc**,
 * liệt kê đủ mọi cột"). Đây là chỗ dự án không theo luật của chính mình, và cái
 * giá đến muộn nhưng đến thật.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property int|null $department_id
 * @property int|null $position_id
 * @property int|null $manager_id
 * @property string|null $employee_code
 * @property string|null $phone
 * @property bool $is_active
 * @property string|null $remember_token
 * @property CarbonImmutable|null $deleted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * `joined_at` cast là 'date' nên ra CarbonImmutable, không phải string như
 * migration gợi ý:
 * @property CarbonImmutable|null $joined_at
 * @property CarbonImmutable|null $terminated_at
 * @property CarbonImmutable|null $anonymised_at
 * @property string|null $two_factor_secret
 * @property list<string>|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 */
#[Fillable([
    'name', 'email', 'password', 'department_id', 'position_id', 'manager_id',
    'employee_code', 'phone', 'joined_at', 'is_active', 'terminated_at',
])]
/*
 * Không bao giờ được lọt ra ngoài khi model bị serialize.
 *
 * `UserResource` không trả về mấy trường này, nhưng đó là một chỗ — còn
 * `toArray()` thì gọi được từ bất kỳ đâu: một `dd()` lúc gỡ lỗi, một payload
 * job đưa vào Redis, một dòng log của thư viện bên thứ ba. Khai ở model là
 * chặn ở nguồn, không phụ thuộc mỗi chỗ dùng đều nhớ.
 *
 * `two_factor_secret` cho phép sinh mã OTP hợp lệ; `two_factor_recovery_codes`
 * cho phép bỏ qua hẳn lớp xác thực thứ hai. Cả hai đều đã mã hoá ở database
 * nhưng `toArray()` trả về bản đã giải mã.
 */
#[Hidden([
    'password',
    'remember_token',
    'two_factor_secret',
    'two_factor_recovery_codes',
])]
class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use HasUuid;
    use Notifiable;
    use SoftDeletes;

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<Position, $this> */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /** @return BelongsTo<self, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    /** @return HasMany<self, $this> */
    public function subordinates(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    /** @return BelongsToMany<Team, $this> */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withTimestamps();
    }

    /**
     * Tuỳ chọn nhận thông báo mà người này đã tự chỉnh.
     *
     * Chỉ có dòng cho loại đã chỉnh; loại chưa chỉnh dùng mặc định của enum.
     *
     * @return HasMany<UserNotificationSetting, $this>
     */
    public function notificationSettings(): HasMany
    {
        return $this->hasMany(UserNotificationSetting::class);
    }

    /**
     * Người này có muốn nhận loại thông báo đó qua kênh đó không.
     *
     * Đọc từ quan hệ đã nạp sẵn nếu có, để gửi thông báo cho một danh sách
     * người không sinh ra mỗi người một truy vấn.
     */
    public function wantsNotification(NotificationType $type, NotificationChannel $channel): bool
    {
        // `loadMissing` chứ không đọc thẳng thuộc tính: `preventLazyLoading`
        // đang bật ngoài production, và job gửi thông báo dựng lại model từ
        // database nên quan hệ chưa bao giờ được nạp sẵn.
        $tuyChon = $this->loadMissing('notificationSettings')
            ->notificationSettings
            ->firstWhere('type', $type);

        if (! $tuyChon instanceof UserNotificationSetting) {
            return match ($channel) {
                NotificationChannel::InApp => $type->defaultInApp(),
                NotificationChannel::Email => $type->defaultEmail(),
            };
        }

        return match ($channel) {
            NotificationChannel::InApp => $tuyChon->in_app,
            NotificationChannel::Email => $tuyChon->email,
        };
    }

    /**
     * Gửi link đặt lại mật khẩu bằng thông báo của dự án, không phải của Laravel.
     *
     * Mặc định của Laravel gửi một email tiếng Anh trỏ về một route `web` không
     * tồn tại trong dự án này — đây là API thuần, giao diện nằm ở Next.js. Ghi
     * đè ở đây là chỗ duy nhất Laravel cho phép đổi.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Chỉ nhân viên đang làm việc.
     *
     * Người nghỉ việc không bị xoá — task họ từng làm, báo cáo họ từng nộp và
     * bảng công của họ phải còn nguyên. Họ chỉ biến mất khỏi các danh sách
     * "ai đang làm việc", ví dụ ô chọn người nhận task.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Đã bật xác thực hai lớp và đã xác nhận bằng mã đầu tiên.
     *
     * Chỉ dựa vào `two_factor_confirmed_at`, không dựa vào `two_factor_secret`:
     * kênh email không có secret cố định, mã sinh mới mỗi lần đăng nhập.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'joined_at' => 'date',
            'terminated_at' => 'datetime',
            'anonymised_at' => 'datetime',
            'is_active' => 'boolean',

            // Mã hoá ở tầng ứng dụng: ai đọc được dump database cũng không dựng
            // lại được mã OTP. Secret lộ thì lớp thứ hai coi như không tồn tại.
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
