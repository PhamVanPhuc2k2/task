<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Identity\Contracts\TwoFactorProvider;
use App\Domain\Identity\Models\Department;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\EmailOtpProvider;
use App\Domain\Identity\Services\TotpProvider;
use App\Policies\DepartmentPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use PragmaRX\Google2FA\Google2FA;

/**
 * Miền Identity: người dùng, phòng ban, đội nhóm, chức vụ, vai trò, quyền.
 *
 * Đây là "shared kernel" — mọi miền nghiệp vụ khác đều được phép tham chiếu
 * tới nó (assignee_id, manager_id, department_id...). Đó là ngoại lệ duy nhất
 * của quy tắc "Domain A không gọi thẳng Domain B", và được ghi rõ trong
 * deptrac.yaml lẫn bộ kiểm thử kiến trúc.
 */
final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Kênh xác thực hai lớp, chọn bằng biến TWO_FACTOR_DRIVER.
        // Luồng đăng nhập hai bước, mã khôi phục và quyền gỡ 2FA của quản trị
        // viên đều không phụ thuộc lựa chọn này.
        $this->app->singleton(TwoFactorProvider::class, function (): TwoFactorProvider {
            return match (config('two-factor.driver')) {
                'totp' => new TotpProvider(new Google2FA, (string) config('app.name')),
                default => new EmailOtpProvider(
                    (int) config('two-factor.email_code_lifetime_minutes'),
                ),
            };
        });
    }

    public function boot(): void
    {
        // Laravel tự dò policy theo quy ước App\Models\X → App\Policies\XPolicy.
        // Model của dự án nằm ở App\Domain\{Miền}\Models nên phải khai báo tay.
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);

        $this->configurePasswordRules();
    }

    /**
     * Chính sách mật khẩu — yêu cầu ở README mục 1.9 Bảo mật.
     *
     * `uncompromised()` gọi API HaveIBeenPwned để kiểm mật khẩu đã lộ trong các
     * vụ rò rỉ. Tắt ở môi trường test để test không phụ thuộc mạng và không tự
     * dưng đỏ khi API đó chậm.
     */
    private function configurePasswordRules(): void
    {
        Password::defaults(function (): Password {
            $rule = Password::min(12)->letters()->numbers();

            return $this->app->runningUnitTests()
                ? $rule
                : $rule->symbols()->uncompromised();
        });
    }
}
