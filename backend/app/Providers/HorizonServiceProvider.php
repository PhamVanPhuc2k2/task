<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Ai được mở giao diện Horizon ngoài môi trường local.
     *
     * Giao diện Horizon hiện **thân của mọi job đang chạy** — với hệ thống này
     * nghĩa là email nhân viên, nội dung bình luận, và về sau là dữ liệu lương.
     * Nó là một màn hình đọc dữ liệu nhạy cảm, không phải một trang trạng thái.
     *
     * Mặc định của Laravel là một danh sách email rỗng, tức khoá sạch — an
     * toàn nhưng phải sửa mã nguồn mỗi lần đổi người quản trị. Ở đây gắn vào
     * quyền `role.manage`: cùng một quyền cho phép cấp quyền cho người khác,
     * nên không mở rộng phạm vi tin cậy nào mới.
     */
    protected function gate(): void
    {
        Gate::define(
            'viewHorizon',
            fn (?User $user = null): bool => $user?->can(Permission::ManageRoles->value) === true,
        );
    }
}
