<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Identity\Actions\AnonymiseUserAction;
use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

/**
 * Xoá dữ liệu cá nhân của người đã nghỉ việc quá thời hạn lưu trữ.
 *
 * Thực thi phần kỹ thuật của chính sách lưu trữ dữ liệu theo Nghị định
 * 13/2023/NĐ-CP. Phần còn lại — **thời hạn bao lâu là hợp lý** — là quyết định
 * của công ty, không phải của mã nguồn, nên nó nằm ở cấu hình chứ không hằng số
 * trong đây.
 *
 * ## Vì sao KHÔNG chạy tự động theo lịch
 *
 * Đây là thao tác **không đảo ngược được**, và chạy nhầm không có đường sửa.
 * Một lịch chạy nền âm thầm xoá dữ liệu cá nhân mỗi đêm là loại tự động hoá mà
 * hậu quả của nó chỉ lộ ra khi đã quá muộn — ví dụ khi ai đó đặt sai
 * `terminated_at`, hoặc khi một nhân viên nghỉ rồi quay lại làm.
 *
 * Nên lệnh này chạy bằng tay, sau khi người có trách nhiệm đã xem danh sách.
 * `--dry-run` là chế độ mặc định của thói quen tốt: xem trước, rồi mới chạy.
 *
 * ```
 * php artisan users:anonymise --dry-run   # xem ai sẽ bị xoá
 * php artisan users:anonymise             # làm thật, hỏi xác nhận
 * php artisan users:anonymise --user=uuid # một người cụ thể, theo yêu cầu của họ
 * ```
 */
final class AnonymiseDepartedUsersCommand extends Command
{
    protected $signature = 'users:anonymise
                            {--user= : uuid của một người cụ thể, bỏ qua mốc thời hạn}
                            {--dry-run : Chỉ liệt kê, không đụng vào dữ liệu}';

    protected $description = 'Xoá dữ liệu cá nhân của người đã nghỉ việc quá hạn lưu trữ (Nghị định 13)';

    public function handle(AnonymiseUserAction $anDanh): int
    {
        $dsNguoi = $this->timNguoi();

        if ($dsNguoi->isEmpty()) {
            $this->info('Không có ai tới hạn xoá dữ liệu.');

            return self::SUCCESS;
        }

        $this->warn('Thao tác này KHÔNG đảo ngược được.');
        $this->newLine();
        $this->table(
            ['Tên', 'Email', 'Nghỉ việc từ'],
            $dsNguoi->map(fn (User $u): array => [
                $u->name,
                $u->email,
                $u->terminated_at?->format('d/m/Y') ?? '—',
            ])->all(),
        );

        if ($this->option('dry-run') === true) {
            $this->info(sprintf('Chạy thử — %d người SẼ bị xoá dữ liệu.', $dsNguoi->count()));

            return self::SUCCESS;
        }

        if (! $this->confirm(sprintf('Xoá dữ liệu cá nhân của %d người?', $dsNguoi->count()), false)) {
            $this->info('Đã huỷ.');

            return self::SUCCESS;
        }

        foreach ($dsNguoi as $u) {
            // Không truyền người thực hiện: lệnh chạy từ dòng lệnh nên không
            // có ai đăng nhập. Xem chú thích ở AnonymiseUserAction::execute().
            $anDanh->execute($u);
            $this->line("  ✔ {$u->name}");
        }

        $this->newLine();
        $this->info(sprintf('Đã xoá dữ liệu cá nhân của %d người.', $dsNguoi->count()));
        $this->warn('Bản sao lưu cũ VẪN chứa dữ liệu gốc cho tới khi hết hạn lưu.');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, User>
     */
    private function timNguoi(): Collection
    {
        $uuid = $this->option('user');

        // Một người cụ thể: bỏ qua mốc thời hạn. Đây là đường dùng khi chính
        // người đó yêu cầu xoá dữ liệu — quyền của họ theo Nghị định 13, không
        // phải chờ hết hạn lưu trữ của công ty.
        if (is_string($uuid) && $uuid !== '') {
            return User::query()
                ->where('uuid', $uuid)
                ->whereNull('anonymised_at')
                ->get();
        }

        $moc = CarbonImmutable::instance(Date::now())
            ->subMonths(config()->integer('identity.retention_months'));

        return User::query()
            ->where('is_active', false)
            ->whereNull('anonymised_at')
            ->whereNotNull('terminated_at')
            ->where('terminated_at', '<', $moc)
            ->orderBy('terminated_at')
            ->get();
    }
}
