<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Domain\Identity\Models\User;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Trung tâm thông báo của chính người đang đăng nhập.
 *
 * Không có Policy: mọi truy vấn đều xuất phát từ `$request->user()->notifications`
 * nên về bản chất không có cách nào chạm tới thông báo của người khác. Thêm một
 * lớp Policy ở đây chỉ tạo cảm giác an toàn giả — thứ cần kiểm là truy vấn có
 * đi từ người dùng hiện tại hay không, và điều đó thấy ngay trong mã.
 */
final class NotificationController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $actor */
        $actor = $request->user();

        $notifications = $actor->notifications()
            ->when(
                $request->boolean('unread'),
                fn ($q) => $q->whereNull('read_at'),
            )
            ->latest()
            ->paginate(perPage: min((int) $request->integer('per_page', 20), 100))
            ->withQueryString();

        return NotificationResource::collection($notifications);
    }

    /** Số chưa đọc — chuông gọi đường này, nên phải rẻ. */
    public function show(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return new JsonResponse([
            'data' => ['unread' => $actor->unreadNotifications()->count()],
        ]);
    }

    /** Đánh dấu đã đọc một thông báo. */
    public function update(Request $request, string $notification): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $ban = $actor->notifications()->whereKey($notification)->first();

        abort_if(! $ban instanceof DatabaseNotification, Response::HTTP_NOT_FOUND);

        $ban->markAsRead();

        return new JsonResponse(['data' => ['read' => true]]);
    }

    /** Đánh dấu đã đọc tất cả. */
    public function store(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $soLuong = $actor->unreadNotifications()->update(['read_at' => now()]);

        return new JsonResponse(['data' => ['marked' => $soLuong]]);
    }
}
