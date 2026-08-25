<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Organization;

use App\Domain\Identity\Models\Department;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Danh sách phòng ban, dùng để vẽ ô chọn trong form nhân sự.
 *
 * Không phân trang và không có Policy riêng: cơ cấu tổ chức là thông tin ai
 * trong công ty cũng biết — nó nằm trên bảng tin, trong chữ ký email, trong
 * mọi cuộc họp. Giấu nó chỉ làm form không dùng được mà không thêm an toàn nào.
 *
 * Trả về dạng phẳng kèm `parent_id` để frontend tự dựng cây nếu cần. Trả về
 * cây lồng nhau sẵn thì mỗi chỗ dùng lại phải duyệt đệ quy chỉ để đổ vào một
 * thẻ `<select>`.
 */
final class DepartmentController
{
    public function index(Request $request): JsonResponse
    {
        $phongBan = Department::query()
            /*
            | Mặc định CHỈ trả phòng ban đang hoạt động — đây là hình dạng mà
            | các ô chọn cần. Trang quản lý cơ cấu tổ chức truyền
            | `include_inactive=1` để thấy cả phòng đã tắt, vì không thấy thì
            | không bật lại được.
            |
            | Phòng ban đã tắt vẫn nằm nguyên trong cây với
            | `Department::subtreeIds()` — tắt là ngừng nhận người mới, không
            | phải biến mất. Người đang ở trong đó vẫn hiện đủ trên bảng công và
            | báo cáo của cấp trên; nếu không thì "ngừng dùng một phòng ban" sẽ
            | âm thầm giấu luôn nhân sự của nó.
            */
            ->when(
                ! $request->boolean('include_inactive'),
                fn (Builder $q) => $q->where('is_active', true),
            )
            ->with('parent:id,uuid,name')
            // Đếm luôn, không đợi hỏi. Trang quản trị cần hai con số này để nói
            // trước "còn 3 nhân sự" thay vì để người dùng bấm Xoá rồi ăn lỗi.
            // Bảng có hàng chục dòng nên hai truy vấn con là không đáng kể, và
            // API luôn cùng một hình dạng — trường lúc có lúc không là nguồn
            // lỗi ở phía giao diện.
            ->withCount(['children', 'users'])
            ->orderBy('name')
            ->get();

        return new JsonResponse(['data' => $phongBan->map(fn (Department $p): array => [
            'id' => $p->uuid,
            'name' => $p->name,
            'code' => $p->code,
            'description' => $p->description,
            'is_active' => $p->is_active,
            'parent_id' => $p->parent?->uuid,
            'parent_name' => $p->parent?->name,
            'child_count' => (int) ($p->children_count ?? 0),
            'user_count' => (int) ($p->users_count ?? 0),
        ])->all()]);
    }
}
