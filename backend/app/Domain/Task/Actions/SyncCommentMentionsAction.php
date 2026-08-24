<?php

declare(strict_types=1);

namespace App\Domain\Task\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Task\Models\TaskComment;

/**
 * Dò người được nhắc tên trong nội dung bình luận và ghi lại.
 *
 * **Định dạng là `@[Tên hiển thị](uuid)`**, do ô soạn thảo chèn vào khi người
 * dùng chọn trong danh sách gợi ý. Không dò `@tên` gõ tay: tên người Việt
 * trùng nhau rất nhiều, và đoán sai người trong một hệ thống giao việc thì
 * người bị nhắc nhầm sẽ nhận thông báo về việc không phải của mình.
 *
 * Kết quả lưu vào bảng riêng chứ không dò lại mỗi lần đọc: nội dung sửa được,
 * và dò lại sau khi sửa sẽ làm mất dấu ai đã từng được nhắc.
 *
 * **Nhắc tên đồng nghĩa với chia sẻ.** Người được nhắc sẽ được thêm vào danh
 * sách theo dõi task, tức là thấy được task đó dù không thuộc phòng ban liên
 * quan. Đây là chủ ý: kéo đồng nghiệp vào một cuộc trao đổi mà họ không mở
 * được task thì tính năng vô nghĩa. Đổi lại, mọi lần nhắc đều lưu vết nên
 * luôn tra được ai đã kéo ai vào.
 */
final class SyncCommentMentionsAction
{
    /** `@[Tên hiển thị](uuid)` — uuid theo đúng khuôn 8-4-4-4-12. */
    private const string KHUON = '/@\[[^\]]{1,255}\]\(([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})\)/';

    /**
     * @return list<int> id của những người thực sự được nhắc
     */
    public function execute(TaskComment $comment): array
    {
        $uuid = $this->uuidTrongNoiDung($comment->body);

        if ($uuid === []) {
            $comment->mentions()->sync([]);

            return [];
        }

        // Chỉ nhận tài khoản còn hoạt động: nhắc người đã nghỉ việc chỉ tạo ra
        // một thông báo không ai đọc.
        $nguoi = User::query()
            ->whereIn('uuid', $uuid)
            ->where('is_active', true)
            // Tự nhắc mình thì bỏ qua — không ai cần thông báo về chính mình.
            ->when(
                $comment->user_id !== null,
                fn ($q) => $q->whereKeyNot($comment->user_id),
            )
            ->pluck('id')
            ->all();

        /** @var list<int> $nguoi */
        $comment->mentions()->sync($nguoi);

        return $nguoi;
    }

    /**
     * @return list<string>
     */
    private function uuidTrongNoiDung(string $body): array
    {
        preg_match_all(self::KHUON, $body, $khop);

        return array_values(array_unique($khop[1]));
    }
}
