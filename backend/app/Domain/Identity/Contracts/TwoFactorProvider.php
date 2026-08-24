<?php

declare(strict_types=1);

namespace App\Domain\Identity\Contracts;

use App\Domain\Identity\Models\User;

/**
 * Kênh xác thực hai lớp.
 *
 * Hai cài đặt hiện có:
 *   - `EmailOtpProvider` — gửi mã 6 số tới hộp thư (mặc định)
 *   - `TotpProvider`     — ứng dụng xác thực trên điện thoại
 *
 * Đổi kênh bằng biến `TWO_FACTOR_DRIVER`, không phải sửa mã nguồn.
 *
 * Interface nhận `User` chứ không nhận chuỗi secret: TOTP có secret cố định
 * nhưng email OTP thì không — nó sinh mã mới mỗi lần và tra trong bảng
 * `two_factor_codes`. Ký theo secret sẽ chỉ vừa với TOTP.
 */
interface TwoFactorProvider
{
    /**
     * Chuẩn bị cho người dùng thiết lập lần đầu.
     *
     * TOTP: sinh secret và trả mã QR để quét.
     * Email: gửi ngay mã đầu tiên tới hộp thư.
     */
    public function startSetup(User $user): SetupPayload;

    /**
     * Phát thử thách lúc đăng nhập, sau khi mật khẩu đã đúng.
     *
     * TOTP: không làm gì, mã đã có sẵn trên điện thoại.
     * Email: gửi mã mới và vô hiệu hoá mã cũ.
     */
    public function challenge(User $user): void;

    /** Kiểm mã người dùng nhập vào. */
    public function verify(User $user, string $code): bool;

    /** Xoá mọi dấu vết khi gỡ xác thực hai lớp. */
    public function reset(User $user): void;

    /**
     * Người dùng này đã thiết lập xong xác thực hai lớp **cho kênh NÀY** chưa.
     *
     * Không thể trả lời bằng mỗi cột `two_factor_confirmed_at`, và đó là lý do
     * phương thức này tồn tại: mỗi kênh cần thứ khác nhau để hoạt động. Email
     * OTP không lưu gì cả (sinh mã mới mỗi lần), còn TOTP bắt buộc phải có
     * `two_factor_secret`.
     *
     * Hệ quả nếu bỏ qua: đổi `TWO_FACTOR_DRIVER` từ email sang totp trên một hệ
     * thống đang chạy sẽ **khoá toàn bộ nhân sự ra ngoài**. Ai cũng có
     * `confirmed_at` nên bị đẩy sang màn nhập mã TOTP, mà không ai có secret nên
     * không mã nào đúng — kể cả quản trị viên, nên không còn ai vào được để
     * sửa. Có test khoá lại ở DriverSwitchTest.
     */
    public function isEnrolled(User $user): bool;

    /** Người dùng có tự gửi lại mã được không. TOTP thì không. */
    public function supportsResend(): bool;
}
