<?php

declare(strict_types=1);

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\SetupPayload;
use App\Domain\Identity\Contracts\TwoFactorProvider;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Str;

/**
 * Vòng đời xác thực hai lớp: thiết lập, xác nhận lần đầu, kiểm mã, gỡ bỏ.
 *
 * Không phụ thuộc kênh cụ thể — mọi thứ riêng của email hay TOTP nằm sau
 * `TwoFactorProvider`. Lớp này chỉ giữ phần chung: bất biến "chỉ coi là đã bật
 * khi `two_factor_confirmed_at` được đặt", và mã khôi phục.
 *
 * Ngoại lệ có chủ ý so với quy ước "một Action một việc": các thao tác ở đây
 * chia sẻ chung bất biến trên. Tách thành nhiều class thì bất biến nằm rải rác
 * và rất dễ có chỗ quên kiểm.
 */
final readonly class TwoFactorService
{
    private const int RECOVERY_CODE_COUNT = 8;

    public function __construct(private TwoFactorProvider $provider) {}

    /**
     * Bắt đầu thiết lập.
     *
     * CHƯA bật 2FA — chỉ bật sau khi người dùng nhập đúng mã đầu tiên ở
     * confirm(). Bật ngay từ đây mà họ không nhận được mã hoặc quét QR hỏng
     * thì họ bị khoá ngoài vĩnh viễn.
     */
    public function startSetup(User $user): SetupPayload
    {
        return $this->provider->startSetup($user);
    }

    /** Phát thử thách lúc đăng nhập. Email thì gửi mã, TOTP thì không làm gì. */
    public function challenge(User $user): void
    {
        $this->provider->challenge($user);
    }

    public function supportsResend(): bool
    {
        return $this->provider->supportsResend();
    }

    /**
     * Người dùng đã thiết lập xong xác thực hai lớp cho **kênh đang dùng** chưa.
     *
     * Chuyển thẳng xuống provider chứ không đọc `two_factor_confirmed_at` ở
     * đây: cột đó không biết người dùng đã xác nhận bằng kênh nào, và tin nó
     * sẽ khoá cả công ty ra ngoài khi đổi `TWO_FACTOR_DRIVER`. Xem
     * TwoFactorProvider::isEnrolled và DriverSwitchTest.
     */
    public function isEnrolled(User $user): bool
    {
        return $this->provider->isEnrolled($user);
    }

    /**
     * Xác nhận lần đầu. Trả về danh sách mã khôi phục, chỉ hiện đúng một lần.
     *
     * @return list<string>|null null nếu mã sai — 2FA giữ nguyên trạng thái tắt.
     */
    public function confirm(User $user, string $code): ?array
    {
        if (! $this->provider->verify($user, $code)) {
            return null;
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $recoveryCodes;
    }

    /** Kiểm mã lúc đăng nhập. */
    public function verifyCode(User $user, string $code): bool
    {
        /*
        | Hỏi PROVIDER, không hỏi cột `two_factor_confirmed_at`.
        |
        | Cùng lý do với LoginController: chỉ provider mới biết "sẵn sàng nhập
        | mã" nghĩa là gì ở kênh đang dùng. Với TOTP là đã quét mã QR; với
        | email là luôn luôn, vì không có gì để thiết lập.
        |
        | Hỏi cột kia thì kênh email tự khoá chính nó: người chưa từng xác nhận
        | được đưa tới màn nhập mã, mã tới hộp thư đàng hoàng, mà nhập vào lại
        | luôn sai — và không có thông báo nào nói vì sao.
        */
        if (! $this->provider->isEnrolled($user)) {
            return false;
        }

        if (! $this->provider->verify($user, $code)) {
            return false;
        }

        /*
        | Ghi mốc xác nhận lần đầu.
        |
        | Không còn dùng để quyết định luồng đăng nhập nữa, nhưng vẫn là dữ liệu
        | thật đáng giữ: nó trả lời "người này đã qua xác thực hai lớp lần đầu
        | lúc nào". Bỏ hẳn thì cột im lặng rỗng mãi mãi và không ai biết nó còn
        | nghĩa gì.
        */
        if ($user->two_factor_confirmed_at === null) {
            $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        }

        return true;
    }

    /**
     * Kiểm mã khôi phục và tiêu huỷ nó.
     *
     * Mỗi mã chỉ dùng được một lần — dùng xong là xoá khỏi danh sách ngay,
     * kể cả khi lần đăng nhập đó về sau có hỏng vì lý do khác.
     */
    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];

        $remaining = array_values(array_filter(
            $codes,
            static fn (string $stored): bool => ! hash_equals($stored, $code),
        ));

        if (count($remaining) === count($codes)) {
            return false;
        }

        $user->forceFill(['two_factor_recovery_codes' => $remaining])->save();

        return true;
    }

    /**
     * Gỡ 2FA — dùng khi nhân viên mất quyền truy cập hộp thư hoặc điện thoại.
     *
     * Sau khi gỡ, lần đăng nhập kế tiếp họ buộc phải thiết lập lại từ đầu, vì
     * hệ thống bắt buộc 2FA với mọi tài khoản.
     */
    public function reset(User $user): void
    {
        $this->provider->reset($user);

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    /** @return list<string> */
    private function generateRecoveryCodes(): array
    {
        return array_map(
            static fn (): string => Str::upper(Str::random(5).'-'.Str::random(5)),
            range(1, self::RECOVERY_CODE_COUNT),
        );
    }
}
