<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\UserActivityEvent;
use App\Domain\Identity\Events\UserAnonymised;
use App\Domain\Identity\Models\User;
use App\Support\Exceptions\CannotAnonymiseActiveUserException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Xoá dữ liệu cá nhân của một người đã nghỉ việc, giữ nguyên lịch sử công việc.
 *
 * ## Vì sao ẩn danh chứ không xoá
 *
 * Nghị định 13/2023/NĐ-CP cho chủ thể dữ liệu quyền yêu cầu xoá dữ liệu cá
 * nhân của mình. Nhưng xoá thẳng dòng `users` là **phá huỷ dữ liệu của công
 * ty**, không phải bảo vệ dữ liệu của cá nhân:
 *
 *   - Task họ từng làm mất người thực hiện
 *   - Báo cáo ngày mất tác giả
 *   - Nhật ký kiểm toán mất người thực hiện hành vi — tức là nhật ký hết giá trị
 *   - Bảng công, bảng lương của các kỳ đã chốt trở nên vô nghĩa
 *
 * Ẩn danh giải quyết đúng phần cần giải quyết: **thông tin nhận dạng cá nhân
 * biến mất, còn dấu vết công việc thì ở lại** dưới một cái tên vô danh. Đây là
 * cách được chấp nhận rộng rãi khi có nghĩa vụ lưu trữ chồng lên quyền được
 * xoá — mà ở đây có thật: chứng từ lương và bảng công phải giữ theo luật kế
 * toán, dài hơn nhiều so với thời điểm nhân viên nghỉ.
 *
 * ## Không đảo ngược được
 *
 * Sau khi chạy, không có cách nào biết dòng này từng là ai. Đó là mục đích.
 * Nên Action bắt buộc người gọi phải xác nhận, và **từ chối** người còn đang
 * làm việc.
 *
 * ## Cái nó KHÔNG chạm tới, có chủ ý
 *
 * - **Nội dung task, bình luận, báo cáo** — đó là tài sản công việc của công
 *   ty, không phải dữ liệu cá nhân. Nếu trong đó có ai viết tên người khác thì
 *   phải xử lý riêng, không quét mù.
 *
 *   Lý do xin nghỉ thì KHÁC, và có xoá: nó do chính người đó viết về hoàn cảnh
 *   riêng, rất thường là thông tin sức khoẻ — thuộc nhóm dữ liệu cá nhân
 *   *nhạy cảm* của Nghị định 13, mức bảo vệ cao hơn. Xoá qua event, xem
 *   App\Domain\Leave\Listeners\ScrubLeaveReasons.
 * - **Nhật ký kiểm toán** (`user_activities`, `payroll_audits`) — giữ nguyên,
 *   chỉ còn trỏ tới một dòng đã ẩn danh. Xoá nhật ký để bảo vệ quyền riêng tư
 *   là xoá đúng thứ dùng để chứng minh quyền riêng tư đã được tôn trọng.
 * - **Bản sao lưu cũ** — chúng vẫn chứa dữ liệu gốc cho tới khi hết hạn lưu
 *   (mặc định 30 ngày). Đây là giới hạn thật, phải nói ra trong chính sách chứ
 *   không giấu.
 */
final class AnonymiseUserAction
{
    public function __construct(private readonly RecordUserActivityAction $ghiNhatKy) {}

    /**
     * @param  User|null  $actor  `null` = chạy từ dòng lệnh, không có ai đăng
     *                            nhập. Ghi thẳng null vào nhật ký còn trung
     *                            thực hơn gán bừa cho một quản trị viên nào đó
     *                            — sáu tháng sau sẽ có người đọc dòng đó và
     *                            tưởng chính người kia đã bấm nút.
     */
    public function execute(User $user, ?User $actor = null): User
    {
        // Chỉ ẩn danh người đã nghỉ. Người đang làm mà bị ẩn danh thì họ mất
        // luôn tài khoản giữa ngày làm việc, và không ai khôi phục lại được.
        if ($user->is_active) {
            throw new CannotAnonymiseActiveUserException;
        }

        return DB::transaction(function () use ($user, $actor): User {
            // Ghi nhật ký TRƯỚC khi xoá: sau khi ẩn danh thì không còn tên để
            // ghi vào, và dòng nhật ký "đã ẩn danh ai đó" là vô dụng.
            $this->ghiNhatKy->execute(
                user: $user,
                event: UserActivityEvent::Anonymised,
                causer: $actor,
                old: ['name' => $user->name, 'email' => $user->email],
                new: null,
            );

            // Giữ email cũ lại: sau `forceFill` bên dưới thì nó không còn, mà
            // token đặt lại mật khẩu được đánh chỉ mục theo email chứ không
            // theo user_id. Xoá theo email MỚI sẽ không khớp dòng nào, và một
            // token còn hiệu lực của địa chỉ cũ vẫn nằm đó — im lặng.
            $emailCu = $user->email;

            $maAn = Str::upper(Str::random(6));

            $user->forceFill([
                'name' => "Nhân sự đã ẩn danh ({$maAn})",
                // Email phải là duy nhất và phải là email hợp lệ về hình thức —
                // dùng tên miền `.invalid` theo RFC 2606, tên miền được dành
                // riêng để bảo đảm không bao giờ phân giải được. Thư gửi tới
                // đây không thể vô tình tới tay ai.
                'email' => "an-danh-{$user->uuid}@explus.invalid",
                'phone' => null,
                // Mã nhân viên cũng là một định danh cá nhân. Bảng lương và
                // bảng công nối theo `user_id`, không nối theo mã này, nên xoá
                // đi không làm hỏng chứng từ nào.
                'employee_code' => null,
                // Mật khẩu ngẫu nhiên không ai biết: tài khoản thành không thể
                // đăng nhập, kể cả bằng luồng quên mật khẩu (email không tới
                // được đâu cả).
                'password' => Hash::make(Str::random(64)),
                'remember_token' => Str::random(60),
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'anonymised_at' => now(),
            ])->save();

            // Thu hồi mọi đường vào còn lại.
            $user->tokens()->delete();
            DB::table('password_reset_tokens')->where('email', $emailCu)->delete();
            $user->notifications()->delete();

            /*
            | Báo cho các miền khác tự dọn phần dữ liệu cá nhân của họ.
            |
            | Identity không được gọi thẳng sang miền khác (README, "Quy tắc
            | phụ thuộc"), nên bắn event là đường duy nhất đúng. Hiện có miền
            | Leave nghe — lý do xin nghỉ thường là thông tin sức khoẻ, thuộc
            | nhóm dữ liệu cá nhân NHẠY CẢM của Nghị định 13.
            |
            | Bắn TRONG giao dịch, listener chạy đồng bộ: nếu một miền dọn
            | không xong thì cả thao tác ẩn danh phải quay lại, chứ không được
            | ghi nhật ký "đã xoá" trong khi dữ liệu còn nằm nguyên.
            */
            event(new UserAnonymised($user));

            return $user->refresh();
        });
    }
}
