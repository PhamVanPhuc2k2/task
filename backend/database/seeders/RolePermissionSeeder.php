<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Enums\Permission as PermissionEnum;
use App\Domain\Identity\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Vai trò và quyền khởi đầu.
 *
 * Chạy được nhiều lần: dùng firstOrCreate và syncPermissions nên không nhân đôi.
 * Chạy cả trên production lúc go-live.
 *
 * Bộ quyền ở đây chỉ là điểm khởi đầu. Sau khi cài đặt, quản trị viên chỉnh lại
 * trong giao diện mà không cần sửa mã nguồn — nên seeder KHÔNG ghi đè quyền của
 * vai trò đã tồn tại, tránh xoá mất tuỳ chỉnh của công ty mỗi lần deploy.
 */
final class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        /**
         * Quyền vừa được tạo lần đầu trong lượt chạy này.
         *
         * Cần biết chính xác cái nào là mới, vì hai luật dưới đây kéo ngược
         * nhau: không ghi đè tuỳ chỉnh của công ty, nhưng cũng không được để
         * quyền mới thêm ở bản phát hành sau nằm chết không thuộc vai trò nào.
         *
         * @var list<string> $quyenMoi
         */
        $quyenMoi = [];

        foreach (PermissionEnum::cases() as $permission) {
            $daCo = Permission::query()
                ->where('name', $permission->value)
                ->where('guard_name', 'web')
                ->exists();

            if (! $daCo) {
                $quyenMoi[] = $permission->value;
            }

            Permission::findOrCreate($permission->value, 'web');
        }

        foreach (RoleEnum::cases() as $roleEnum) {
            $macDinh = array_column($roleEnum->defaultPermissions(), 'value');
            $role = Role::query()->where('name', $roleEnum->value)->first();

            // Vai trò chưa có: gán trọn bộ quyền mặc định.
            if (! $role instanceof Role) {
                Role::findOrCreate($roleEnum->value, 'web')->syncPermissions($macDinh);

                continue;
            }

            /*
             * Vai trò đã có: CHỈ cấp thêm những quyền vừa mới ra đời trong lượt
             * này và nằm trong bộ mặc định của vai trò đó.
             *
             * Không dùng `syncPermissions` — nó xoá sạch tuỳ chỉnh mà quản trị
             * viên đã đặt. Cũng không bỏ qua hẳn như bản trước: bản trước thoát
             * ngay khi thấy vai trò đã tồn tại, nên quyền chấm công thêm ở đợt
             * 3 sẽ không tới được vai trò nào trên hệ thống đang chạy, và không
             * có gì báo — tính năng chỉ đơn giản là không ai vào được.
             */
            $themVao = array_values(array_intersect($quyenMoi, $macDinh));

            if ($themVao !== []) {
                $role->givePermissionTo($themVao);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
