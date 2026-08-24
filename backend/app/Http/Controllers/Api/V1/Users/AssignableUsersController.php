<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Users;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Danh bạ rút gọn để chọn người thực hiện, người duyệt, người nhận bàn giao.
 *
 * Tách khỏi `GET /users` vì hai đường này phục vụ hai việc khác hẳn nhau.
 * `GET /users` là màn hình quản trị nhân sự và đòi quyền `user.manage`, trong
 * khi một trưởng phòng có quyền giao việc nhưng KHÔNG có quyền quản trị người
 * dùng — nếu dùng chung một đường thì họ không mở nổi ô chọn người thực hiện.
 *
 * Trả về đúng những trường cần để vẽ ô chọn: không kèm vai trò, quyền hay số
 * điện thoại. Danh bạ nội bộ thì ai cũng xem được, hồ sơ nhân sự thì không.
 */
final class AssignableUsersController
{
    /**
     * Số người tối đa trả về một lượt.
     *
     * Ô chọn có hơn trăm dòng thì cuộn tìm đã vô nghĩa — người dùng gõ để lọc.
     * Con số này là giới hạn kỹ thuật, và phần bị cắt luôn được báo qua `meta`.
     */
    private const int GIOI_HAN = 100;

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $query = User::query()
            ->where('is_active', true)
            ->with('department');

        // Không thấy được task toàn công ty thì cũng chỉ giao việc trong phạm vi
        // phòng mình và các phòng trực thuộc — cộng thêm chính mình, để người
        // chưa được xếp phòng ban vẫn tự tạo việc cho mình được.
        if (! $actor->can(Permission::ViewAllTasks->value)) {
            $phamVi = $actor->department?->subtreeIds() ?? [];

            $query->where(fn (Builder $q) => $q
                ->whereIn('department_id', $phamVi)
                ->orWhere('id', $actor->id));
        }

        $query->when($request->filled('search'), function (Builder $q) use ($request): void {
            $tuKhoa = '%'.$request->string('search').'%';

            $q->where(fn (Builder $s) => $s
                ->where('name', 'like', $tuKhoa)
                ->orWhere('email', 'like', $tuKhoa)
                ->orWhere('employee_code', 'like', $tuKhoa));
        });

        /*
        | Vẫn cắt ở 100 người, nhưng KHÔNG cắt im lặng nữa.
        |
        | Bản trước chỉ có `limit(100)`. Công ty quá 100 người thì một số nhân
        | viên không bao giờ xuất hiện trong ô chọn, và không có gì báo — người
        | dùng chỉ thấy đồng nghiệp của mình "không có trong danh sách" mà không
        | hiểu vì sao, rồi kết luận hệ thống hỏng.
        |
        | Đếm tổng trước rồi mới cắt, trả `meta.total` và `meta.truncated` để
        | giao diện nói rõ "đang hiện 100 trên 240 — gõ để tìm".
        |
        | Không phân trang: ô chọn người dùng cách tìm bằng cách gõ, không bằng
        | cách lật trang.
        */
        $tong = (clone $query)->count();
        $users = $query->orderBy('name')->limit(self::GIOI_HAN)->get();

        return new JsonResponse([
            'data' => $users->map(fn (User $user): array => [
                'id' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'employee_code' => $user->employee_code,
                'department' => $user->department?->name,
            ])->all(),
            'meta' => [
                'total' => $tong,
                'returned' => $users->count(),
                'truncated' => $tong > $users->count(),
            ],
        ]);
    }
}
