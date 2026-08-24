<?php

declare(strict_types=1);

namespace App\Domain\Leave\Listeners;

use App\Domain\Identity\Events\UserAnonymised;
use App\Domain\Leave\Models\LateArrivalRequest;
use App\Domain\Leave\Models\LeaveRequest;

/**
 * Xoá phần chữ tự do trong đơn nghỉ của người đã được ẩn danh.
 *
 * ## Vì sao lý do nghỉ KHÁC nội dung task và báo cáo
 *
 * `AnonymiseUserAction` cố ý không đụng tới nội dung task, bình luận và báo cáo
 * ngày — đó là **tài sản công việc của công ty**, người viết chỉ là tác giả.
 *
 * Lý do xin nghỉ không thuộc loại đó. Nó do chính người đó viết về hoàn cảnh
 * riêng, và rất thường là **thông tin sức khoẻ**: "nghỉ ốm", "đi khám", "về quê
 * chăm mẹ". Nghị định 13/2023/NĐ-CP xếp dữ liệu sức khoẻ vào nhóm **dữ liệu cá
 * nhân nhạy cảm** — mức bảo vệ cao hơn dữ liệu cá nhân thông thường.
 *
 * ## Vì sao chỉ xoá phần chữ, giữ nguyên dòng
 *
 * Đơn nghỉ còn là **chứng từ lao động**: ngày nào được nghỉ, loại gì, ai duyệt.
 * Xoá cả dòng là phá chứng từ. Nhưng chức năng chứng từ đó **không cần đến câu
 * chữ tự do** — nên xoá đúng phần nhạy cảm và giữ lại phần có nghĩa pháp lý.
 *
 * `review_note` cũng xoá: nó do quản lý viết nhưng thường nhắc lại chính nội
 * dung của lý do.
 *
 * ## Đơn xin đi muộn cũng vậy, và dễ quên hơn
 *
 * Nó nằm ở bảng khác nên rất dễ bị bỏ sót khi rà. Nhưng lý do của nó thường là
 * thông tin sức khoẻ của **người khác trong gia đình** — "đưa con đi khám",
 * "đưa mẹ đi viện" — và vẫn thuộc nhóm dữ liệu nhạy cảm. Ngày và giờ đã duyệt
 * giữ nguyên: đó là phần bảng công cần để giải thích một ô trong quá khứ.
 */
final class ScrubLeaveReasons
{
    private const DA_XOA = '(đã xoá theo yêu cầu bảo vệ dữ liệu cá nhân)';

    public function handle(UserAnonymised $event): void
    {
        $thayThe = [
            'reason' => self::DA_XOA,
            'review_note' => null,
        ];

        LeaveRequest::query()
            ->where('user_id', $event->user->id)
            ->update($thayThe);

        LateArrivalRequest::query()
            ->where('user_id', $event->user->id)
            ->update($thayThe);
    }
}
