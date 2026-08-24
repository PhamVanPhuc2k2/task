<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Domain\Identity\Enums\NotificationChannel;
use App\Domain\Identity\Enums\NotificationType;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserNotificationSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Tuỳ chọn nhận thông báo của chính người đang đăng nhập.
 *
 * Luôn trả về đủ MỌI loại, kể cả loại người dùng chưa đụng tới — giao diện cần
 * vẽ đủ danh sách, và giá trị mặc định phải nhìn thấy được thì người ta mới
 * biết mình đang bật hay tắt cái gì.
 */
final class NotificationSettingController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $actor->loadMissing('notificationSettings');

        $muc = array_map(
            fn (NotificationType $type): array => [
                'type' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
                'in_app' => $actor->wantsNotification($type, NotificationChannel::InApp),
                'email' => $actor->wantsNotification($type, NotificationChannel::Email),
            ],
            NotificationType::cases(),
        );

        return new JsonResponse(['data' => $muc]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $duLieu = $request->validate([
            'type' => ['required', Rule::enum(NotificationType::class)],
            'in_app' => ['required', 'boolean'],
            'email' => ['required', 'boolean'],
        ]);

        // updateOrCreate: người dùng chỉnh loại nào thì mới sinh dòng cho loại
        // đó. Xem UserNotificationSetting để biết vì sao không tạo sẵn đủ dòng.
        UserNotificationSetting::query()->updateOrCreate(
            ['user_id' => $actor->id, 'type' => $duLieu['type']],
            ['in_app' => $duLieu['in_app'], 'email' => $duLieu['email']],
        );

        return $this->index($request);
    }
}
