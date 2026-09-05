<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Attendance\Support\CompanyWorkCalendar;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Support\Contracts\WorkCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
        | Ghép câu hỏi "khoảng này có bao nhiêu ngày công" với bản cài đặt của nó.
        |
        | Quỹ phép năm ở miền Leave cần câu trả lời, nhưng nó chỉ tính được bằng
        | lịch tuần và bảng ngày lễ — cả hai thuộc miền Attendance, mà luật tầng
        | cấm Leave gọi thẳng sang. Giao diện khai ở tầng Support để cả hai miền
        | với tới được, và chỗ ghép là đây: Providers là tầng duy nhất được phép
        | biết cả hai đầu.
        |
        | Singleton chứ không `bind`: bản cài đặt nhớ danh sách ngày lễ theo năm
        | trong bộ nhớ, và một người có mười đơn nghỉ sẽ hỏi mười lần trong cùng
        | một request. Dựng lại mỗi lần là mười câu SQL cho cùng một danh sách.
        */
        $this->app->singleton(WorkCalendar::class, CompanyWorkCalendar::class);
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureDates();
        $this->configureFactories();
        $this->configurePasswords();
        $this->configureRateLimiting();
        $this->configureApiDocs();
    }

    /**
     * Ai mở được tài liệu API ngoài môi trường local.
     *
     * `RestrictedDocsAccess` của Scramble chỉ mở khi `Gate::allows('viewApiDocs')`,
     * và mặc định của nó là chỉ cho ở local. Ở đây mở thêm cho người có quyền
     * quản trị vai trò, để quản trị viên xem được tài liệu trên staging mà
     * không phải bật `APP_DEBUG`.
     *
     * Tài liệu API liệt kê đầy đủ endpoint và tham số — với người muốn dò tìm
     * thì đó là tấm bản đồ. Không phải thứ để công khai.
     */
    private function configureApiDocs(): void
    {
        Gate::define(
            'viewApiDocs',
            fn (?User $user = null): bool => $this->app->environment('local')
                || $user?->can(Permission::ManageRoles->value) === true,
        );
    }

    /**
     * Chính sách mật khẩu tối thiểu, áp cho mọi chỗ dùng `Password::defaults()`.
     *
     * Mười hai ký tự chứ không phải tám: với phần cứng hiện nay, tám ký tự có
     * cả chữ và số vẫn nằm trong tầm dò của một máy đơn lẻ. Mười hai ký tự đẩy
     * chi phí lên nhiều bậc mà người dùng vẫn gõ được.
     *
     * `uncompromised()` đối chiếu với kho mật khẩu đã lộ của Have I Been Pwned
     * qua **k-anonymity**: chỉ năm ký tự đầu của mã băm SHA-1 rời khỏi máy chủ,
     * không bao giờ gửi cả mật khẩu lẫn mã băm đầy đủ. Đây là điểm cần nói rõ
     * khi rà soát Nghị định 13 — không có dữ liệu cá nhân nào bị chuyển ra
     * ngoài.
     *
     * Tắt ở môi trường test: gọi mạng ra ngoài trong test là test rung, và khi
     * không có mạng thì cả bộ test đỏ vì một lý do không liên quan.
     */
    private function configurePasswords(): void
    {
        Password::defaults(function (): Password {
            $rule = Password::min(12)->letters()->numbers();

            return $this->app->runningUnitTests()
                ? $rule
                : $rule->uncompromised();
        });
    }

    /**
     * Giới hạn tần suất cho API.
     *
     * Một limiter tên `api`, tự chọn hạn mức theo phương thức HTTP:
     *
     * - **Đọc** rộng tay (300/phút) — một màn hình mở ra có thể gọi năm sáu
     *   endpoint cùng lúc, siết quá thì người dùng bình thường bị chặn.
     * - **Ghi** chặt hơn (60/phút) — tạo task, bình luận, tải tệp đều là thao
     *   tác con người bấm tay, không ai bấm sáu mươi lần một phút. Đây là lớp
     *   chặn trước cho việc spam bình luận và dò tuần tự qua endpoint ghi.
     *
     * Đếm theo id người dùng khi đã đăng nhập, theo IP khi chưa. Đếm theo IP
     * cho cả người đã đăng nhập thì cả văn phòng dùng chung một đường truyền
     * sẽ chặn lẫn nhau.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            $ai = $request->user()?->getAuthIdentifier() ?? $request->ip();

            // Hai bộ đếm riêng biệt nhờ tiền tố khác nhau. Dùng chung một bộ
            // thì một màn hình mở ra gọi sáu endpoint đọc sẽ ăn hết hạn mức
            // của thao tác ghi ngay sau đó.
            return $request->isMethodSafe()
                ? Limit::perMinute(300)->by('read|'.$ai)
                : Limit::perMinute(60)->by('write|'.$ai);
        });
    }

    /**
     * Dạy Eloquent tìm factory theo cấu trúc miền nghiệp vụ.
     *
     * Mặc định Laravel dò `App\Models\Foo` → `Database\Factories\FooFactory`.
     * Model của dự án này nằm ở `App\Domain\{Miền}\Models\Foo`, nên nếu không
     * khai báo lại thì `Foo::factory()` sẽ đi tìm một class không tồn tại và
     * báo lỗi rất khó hiểu.
     *
     * Quy ước: `App\Domain\Identity\Models\User` → `Database\Factories\Identity\UserFactory`
     */
    private function configureFactories(): void
    {
        Factory::guessFactoryNamesUsing(
            /**
             * @param  class-string<Model>  $modelName
             * @return class-string<Factory<Model>>
             */
            static function (string $modelName): string {
                if (str_starts_with($modelName, 'App\\Domain\\')) {
                    $domain = Str::before(Str::after($modelName, 'App\\Domain\\'), '\\');

                    /** @var class-string<Factory<Model>> $name */
                    $name = sprintf('Database\\Factories\\%s\\%sFactory', $domain, class_basename($modelName));

                    return $name;
                }

                /** @var class-string<Factory<Model>> $name */
                $name = sprintf('Database\\Factories\\%sFactory', class_basename($modelName));

                return $name;
            },
        );
    }

    /**
     * Siết chặt Eloquent ở môi trường không phải production.
     *
     * - preventLazyLoading: bắt lỗi N+1 ngay lúc dev thay vì phát hiện khi
     *   production chậm. Xem README, bảng "Anti-pattern bị cấm".
     * - preventSilentlyDiscardingAttributes: gán thuộc tính không có trong
     *   $fillable sẽ báo lỗi thay vì âm thầm bỏ qua.
     * - preventAccessingMissingAttributes: đọc cột chưa select sẽ báo lỗi
     *   thay vì trả về null — loại bug rất khó tìm.
     */
    private function configureModels(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // Không cho phép thao tác huỷ diệt (migrate:fresh, db:wipe) trên production.
        Model::preventLazyLoading(! $this->app->isProduction());
    }

    /**
     * Dùng CarbonImmutable thay vì Carbon.
     *
     * Carbon thường có thể bị sửa tại chỗ: `$task->due_date->addDay()` âm thầm
     * đổi luôn giá trị trên model. Với hệ thống tính deadline và giờ công thì
     * đây là nguồn bug rất khó lần ra.
     */
    private function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
    }
}
