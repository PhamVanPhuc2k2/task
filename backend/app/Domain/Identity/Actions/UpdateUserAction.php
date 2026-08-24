<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Data\UpdateUserData;
use App\Domain\Identity\Data\UpdateUserResult;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\UserActivityEvent;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\Position;
use App\Domain\Identity\Models\User;
use App\Support\Exceptions\CannotChangeOwnRoleException;
use App\Support\Exceptions\ManagerCycleException;
use Illuminate\Support\Facades\DB;

/**
 * Sửa hồ sơ một nhân viên đã có.
 *
 * Ba việc luôn đi cùng nhau, thiếu một thì tính năng này thành nguồn sinh lỗi:
 *
 *   1. **Ghi nhật ký.** Đổi phòng ban và đổi vai trò là thứ sáu tháng sau sẽ có
 *      người hỏi "ai làm, lúc nào".
 *   2. **Chặn tự hạ quyền.** Quản trị viên cuối cùng tự đổi vai trò của mình
 *      xuống Nhân viên là khoá cả công ty ra ngoài phần quản trị.
 *   3. **Chặn vòng lặp quản lý.** A quản lý B rồi đặt quản lý của A là B.
 *
 * **Đổi phòng ban KHÔNG cần sửa dữ liệu nào khác.** Phạm vi task nhìn thấy được
 * tính ngay lúc truy vấn từ `users.department_id` (`Task::scopeVisibleTo`), nên
 * đổi cột là đổi luôn phạm vi. Không có bảng phi chuẩn hoá nào phải cập nhật
 * theo — điều này đã kiểm chứng bằng test, vì nếu sau này ai đó thêm bộ nhớ đệm
 * phạm vi thì test đó sẽ đỏ và bắt phải xử lý ở đây.
 */
final class UpdateUserAction
{
    /** Các cột được coi là "hồ sơ", đổi thì ghi nhật ký. */
    private const array TRUONG_HO_SO = [
        'name', 'email', 'employee_code', 'phone', 'joined_at',
        'department_id', 'position_id', 'manager_id',
    ];

    public function __construct(
        private readonly RecordUserActivityAction $ghiNhatKy,
    ) {}

    public function execute(User $user, UpdateUserData $data, User $actor): UpdateUserResult
    {
        $vaiTroCu = $user->getRoleNames()->first();
        $doiVaiTro = $vaiTroCu !== $data->role->value;

        if ($doiVaiTro && $user->is($actor)) {
            throw new CannotChangeOwnRoleException;
        }

        $this->chanVongLapQuanLy($user, $data->managerId);

        $phongBanCu = $user->department_id;

        // Một giao dịch: hồ sơ, vai trò và nhật ký phải cùng thành công hoặc
        // cùng không. Ghi hồ sơ xong mà nhật ký hỏng thì còn tệ hơn không ghi —
        // vì lúc đó nhật ký nói dối chứ không chỉ là thiếu.
        return DB::transaction(function () use ($user, $data, $actor, $doiVaiTro, $vaiTroCu, $phongBanCu): UpdateUserResult {
            $truoc = $this->chupHoSo($user);

            $user->fill([
                'name' => $data->name,
                'email' => $data->email,
                'phone' => $data->phone,
                'department_id' => $data->departmentId,
                'position_id' => $data->positionId,
                'manager_id' => $data->managerId,
                'joined_at' => $data->joinedAt,
            ]);

            // `employee_code` không nằm trong Fillable của model — nó là mã định
            // danh, cố ý bắt phải gán tường minh để không bị ghi đè do một mảng
            // dữ liệu lọt vào từ chỗ khác.
            $user->employee_code = $data->employeeCode;

            $doiHoSo = $user->isDirty(self::TRUONG_HO_SO);
            $user->save();

            if ($doiHoSo) {
                $sau = $this->chupHoSo($user->refresh());

                $this->ghiNhatKy->execute(
                    user: $user,
                    event: UserActivityEvent::ProfileUpdated,
                    causer: $actor,
                    // Chỉ ghi những trường THẬT SỰ đổi. Ghi cả hồ sơ mỗi lần bấm
                    // lưu thì nhật ký đầy dòng vô nghĩa và không ai đọc nữa.
                    old: array_intersect_key($truoc, array_diff_assoc($sau, $truoc)),
                    new: array_diff_assoc($sau, $truoc),
                );
            }

            if ($doiVaiTro) {
                $user->syncRoles([$data->role->value]);

                $this->ghiNhatKy->execute(
                    user: $user,
                    event: UserActivityEvent::RoleChanged,
                    causer: $actor,
                    old: ['role' => $vaiTroCu],
                    new: ['role' => $data->role->value],
                );
            }

            return new UpdateUserResult(
                user: $user,
                warnings: $this->canhBao($user, $phongBanCu),
            );
        });
    }

    /**
     * Ảnh chụp các trường hồ sơ, dạng người đọc được.
     *
     * Lưu TÊN phòng ban và chức vụ chứ không lưu khoá ngoại: nhật ký phải đọc
     * được một mình nó. Lưu id thì một năm sau, khi phòng ban đã đổi tên hoặc bị
     * gộp, dòng "department_id: 3 → 7" không còn nói lên điều gì.
     *
     * @return array<string, string|null>
     */
    private function chupHoSo(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'employee_code' => $user->employee_code,
            'phone' => $user->phone,
            'joined_at' => $user->joined_at?->toDateString(),
            'department' => $user->department_id === null
                ? null
                : Department::query()->whereKey($user->department_id)->value('name'),
            'position' => $user->position_id === null
                ? null
                : Position::query()->whereKey($user->position_id)->value('name'),
            'manager' => $user->manager_id === null
                ? null
                : User::query()->whereKey($user->manager_id)->value('name'),
        ];
    }

    /**
     * Người được chọn làm quản lý có nằm dưới quyền người đang sửa không.
     *
     * Đi ngược lên theo `manager_id` từ người quản lý mới; gặp lại chính người
     * đang sửa là có vòng lặp. `$daQua` chặn lặp vô hạn phòng khi dữ liệu cũ
     * trong database đã sẵn một vòng — không có nó thì hàm này treo, và treo ở
     * chỗ này nghĩa là treo cả request.
     */
    private function chanVongLapQuanLy(User $user, ?int $managerId): void
    {
        if ($managerId === null) {
            return;
        }

        if ($managerId === $user->id) {
            throw new ManagerCycleException($user->name);
        }

        $daQua = [];
        $hienTai = $managerId;

        while ($hienTai !== null && ! isset($daQua[$hienTai])) {
            $daQua[$hienTai] = true;

            /** @var int|null $tiepTheo */
            $tiepTheo = User::query()->whereKey($hienTai)->value('manager_id');

            if ($tiepTheo === $user->id) {
                throw new ManagerCycleException(
                    (string) User::query()->whereKey($managerId)->value('name'),
                );
            }

            $hienTai = $tiepTheo === null ? null : (int) $tiepTheo;
        }
    }

    /**
     * Hệ quả đúng nhưng dễ bất ngờ, cần nói cho người vừa bấm lưu.
     *
     * @return list<string>
     */
    private function canhBao(User $user, ?int $phongBanCu): array
    {
        if ($user->department_id === $phongBanCu) {
            return [];
        }

        $canhBao = [];

        if ($user->can(Permission::ViewTeamTasks->value)) {
            $canhBao[] = 'Đổi phòng ban làm đổi luôn phạm vi công việc người này nhìn thấy: từ giờ họ xem được việc của phòng mới và không còn xem được việc của phòng cũ.';
        }

        $soCapDuoi = $user->subordinates()->where('is_active', true)->count();

        if ($soCapDuoi > 0) {
            $canhBao[] = sprintf(
                'Vẫn còn %d nhân viên khai người này là quản lý trực tiếp. Nếu họ ở lại phòng cũ, cần đổi quản lý cho từng người.',
                $soCapDuoi,
            );
        }

        return $canhBao;
    }
}
