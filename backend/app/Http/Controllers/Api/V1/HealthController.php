<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Support\Health\ComponentHealth;
use App\Support\Health\SystemHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Tình trạng hạ tầng, dành cho hệ thống giám sát và bộ cân bằng tải.
 *
 * **Không yêu cầu đăng nhập** — bộ giám sát không có tài khoản, và một phép
 * kiểm sức khoẻ chỉ chạy được khi đăng nhập được thì đã bỏ sót đúng tình huống
 * cần nó nhất: lúc database sập và không ai đăng nhập nổi.
 *
 * Đổi lại, phản hồi **không chứa gì nhạy cảm**: không thông điệp lỗi, không tên
 * máy chủ, không phiên bản, không tên sản phẩm. Chỉ ba từ mỗi thành phần —
 * tên chung chung, tình trạng, và mất bao nhiêu mili-giây.
 *
 * Mã trạng thái là thứ bộ cân bằng tải đọc: **503 khi và chỉ khi phần lõi
 * chết**. Kho ảnh hỏng vẫn trả 200 kèm `degraded`, vì rút cả máy chủ ra khỏi
 * vòng phục vụ chỉ vì không mở được ảnh là đổi một sự cố nhỏ lấy một sự cố lớn.
 *
 * Toàn bộ phép kiểm nằm ở `App\Support\Health\SystemHealth` — luật kiến trúc
 * của dự án cấm controller truy vấn database trực tiếp, và test kiến trúc chặn
 * merge nếu vi phạm.
 */
final class HealthController
{
    public function __invoke(SystemHealth $health): JsonResponse
    {
        $thanhPhan = $health->check();
        $chung = $health->overall($thanhPhan);

        return new JsonResponse(
            [
                'status' => $chung->value,
                'components' => array_map(
                    fn (ComponentHealth $tp): array => $tp->toArray(),
                    $thanhPhan,
                ),
            ],
            $chung->shouldFailRequest()
                ? Response::HTTP_SERVICE_UNAVAILABLE
                : Response::HTTP_OK,
        );
    }
}
