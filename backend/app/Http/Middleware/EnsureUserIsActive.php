<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\Models\User;
use App\Support\Exceptions\AccountDisabledException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chặn tài khoản đã bị vô hiệu hoá ngay giữa phiên làm việc.
 *
 * Kiểm tra lúc đăng nhập là chưa đủ: nhân viên nghỉ việc lúc 9h sáng mà đã đăng
 * nhập từ 8h thì phiên cũ vẫn dùng được tới khi hết hạn. Middleware này khoá
 * ngay ở request kế tiếp.
 */
final class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            if ($user->is_active !== true) {
                auth()->guard('web')->logout();

                throw new AccountDisabledException;
            }

            // Nạp sẵn vai trò và quyền một lần cho cả request.
            //
            // Mỗi lần gọi $user->can(...) đều đọc quan hệ roles/permissions.
            // Không nạp trước thì Model::preventLazyLoading() (bật ở môi trường
            // dev, xem AppServiceProvider) sẽ ném lỗi ngay — và trên production
            // thì thành N+1 âm thầm ở mọi endpoint có kiểm quyền.
            //
            // Trừ những route tự khai là không kiểm quyền — xem bên dưới.
            if ($this->canNapQuyen($request)) {
                $user->loadMissing(['roles.permissions', 'permissions']);
            }
        }

        return $next($request);
    }

    /**
     * Route này có cần biết quyền của người dùng không.
     *
     * Mặc định là **có** — quên khai thì vẫn chạy đúng, chỉ tốn ba truy vấn.
     * Ngược lại (mặc định không nạp) thì quên khai là ăn N+1 âm thầm hoặc lỗi
     * `preventLazyLoading` ở một endpoint bất kỳ. Sai theo hướng an toàn.
     *
     * Chỉ một route tự khai `false`: nhịp tim chấm công. Nó là đường được gọi
     * nhiều nhất cả hệ thống — hai trăm nhân sự × tám tiếng ≈ **96.000 lượt mỗi
     * ngày** — và nó không kiểm quyền nào cả, ai đăng nhập cũng gửi được nhịp
     * của chính mình. Ba truy vấn nạp vai trò ở đó là ba truy vấn cho một câu
     * hỏi không ai đặt ra.
     *
     * Cơ chế tự bảo vệ: nếu sau này ai đó thêm một phép kiểm quyền vào route
     * đã khai `false`, `preventLazyLoading` sẽ ném lỗi ngay ở môi trường dev
     * thay vì để lọt thành N+1 trên production.
     */
    private function canNapQuyen(Request $request): bool
    {
        $khai = $request->route()?->defaults['preload_permissions'] ?? true;

        return $khai !== false;
    }
}
