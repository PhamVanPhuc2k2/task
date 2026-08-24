<?php

declare(strict_types=1);

namespace App\Support\Health;

use App\Support\Enums\HealthStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Kiểm tra các thành phần hạ tầng mà ứng dụng phụ thuộc.
 *
 * ## Vì sao không dùng `/up` có sẵn của Laravel
 *
 * `/up` chỉ trả lời "tiến trình PHP còn sống". Nó vẫn xanh khi database đã sập:
 * ứng dụng khởi động được, route trả 200, và bộ giám sát báo mọi thứ ổn trong
 * lúc không ai đăng nhập nổi. Hai đường tồn tại song song có chủ ý —
 * `/up` là *liveness* (có nên khởi động lại tiến trình không), đường này là
 * *readiness* (có nên gửi người dùng vào máy chủ này không).
 *
 * ## Mỗi phép kiểm phải thật sự chạm tới thành phần đó
 *
 * `DB::connection()` không mở kết nối — Laravel kết nối lười. Kiểm bằng cách
 * gọi hàm đó là tự lừa mình: nó xanh kể cả khi MySQL đã tắt. Phải chạy một câu
 * lệnh thật. Cache cũng vậy: phải ghi rồi đọc lại, vì một Redis còn sống nhưng
 * hết bộ nhớ sẽ nhận lệnh ghi rồi vứt đi.
 *
 * ## Tên thành phần cố ý chung chung
 *
 * `database`, `cache`, `storage` — không phải `mysql`, `redis`, `cloudflare-r2`.
 * Endpoint này mở công khai; nói rõ mình chạy gì và phiên bản nào là thông tin
 * miễn phí cho người dò tìm, mà chẳng giúp gì thêm cho bộ giám sát.
 */
final class SystemHealth
{
    /** Khoá tạm dùng để thử ghi/đọc cache. */
    private const string KHOA_THU = 'health:probe';

    /**
     * Timeout riêng cho phép kiểm kho tệp, tính bằng giây.
     *
     * Ổ R2 thật **không** đặt timeout: một tệp 10 MB tải lên qua đường truyền
     * chậm hoàn toàn có thể mất hơn thế. Nhưng phép kiểm sức khoẻ mà treo hai
     * phút thì bộ giám sát hết giờ chờ trước, và ta mất luôn thứ mình đang đo.
     * Nên chỗ này dựng một ổ riêng cùng cấu hình, chỉ khác ở hai con số này.
     */
    private const int TIMEOUT_KHO_TEP = 4;

    private const int TIMEOUT_KET_NOI_KHO_TEP = 2;

    /**
     * Timeout kết nối database cho phép kiểm, tính bằng giây.
     *
     * Không có nó thì một máy chủ database *treo* (gói tin bị tường lửa nuốt,
     * máy mất điện đột ngột) sẽ giữ một tiến trình PHP hàng chục giây mỗi lần
     * bộ giám sát hỏi — mà nó hỏi 30 giây một lần.
     *
     * Đo thật trong container:
     *
     *     database từ chối kết nối        0,16s   ← nhanh sẵn, không cần timeout
     *     địa chỉ không định tuyến được   2,00s   ← đúng bằng timeout này
     *     tên máy không phân giải được   ~17s     ← timeout KHÔNG bó được
     *
     * Dòng cuối là giới hạn thật và không vá được ở tầng PHP: `getaddrinfo()`
     * chặn ở tầng hệ điều hành, `PDO::ATTR_TIMEOUT` chỉ tính từ lúc đã có địa
     * chỉ IP. Nó chỉ xảy ra khi bản ghi DNS biến mất (đúng cảnh `docker compose
     * stop mysql`); ở production database nằm ở một máy có tên phân giải ổn
     * định, nên hai dòng đầu mới là tình huống thật. Hệ thống giám sát vẫn nên
     * tự đặt hạn chờ của nó — với nó thì "hết giờ chờ" và "503" đều là không
     * sẵn sàng.
     */
    private const int TIMEOUT_DATABASE = 2;

    /**
     * @return list<ComponentHealth>
     */
    public function check(): array
    {
        return [
            $this->do('database', fn (): bool => $this->kiemDatabase()),
            $this->do('cache', fn (): bool => $this->kiemCache()),
            $this->do('storage', fn (): bool => $this->kiemKhoTep(), HealthStatus::Degraded),
        ];
    }

    /**
     * @param  list<ComponentHealth>  $thanhPhan
     */
    public function overall(array $thanhPhan): HealthStatus
    {
        $chung = HealthStatus::Skipped;

        foreach ($thanhPhan as $tp) {
            $chung = $chung->worseOf($tp->status);
        }

        // Không có gì để kiểm thì coi như ổn, không phải "chưa biết".
        return $chung === HealthStatus::Skipped ? HealthStatus::Ok : $chung;
    }

    /**
     * Chạy một phép kiểm và đo thời gian.
     *
     * `$mucDoHong` cho phép mỗi thành phần tự khai hỏng thì nặng tới đâu — kho
     * tệp hỏng chỉ là `Degraded`, database hỏng là `Down`.
     *
     * @param  callable(): bool  $phep  Trả về false nghĩa là bỏ qua, không kiểm.
     */
    private function do(
        string $ten,
        callable $phep,
        HealthStatus $mucDoHong = HealthStatus::Down,
    ): ComponentHealth {
        $batDau = hrtime(true);

        try {
            $trangThai = $phep() ? HealthStatus::Ok : HealthStatus::Skipped;
        } catch (Throwable $e) {
            // Lỗi gốc vào log, không vào phản hồi. Xem chú thích ở
            // ComponentHealth để biết vì sao.
            Log::warning("Health check '{$ten}' thất bại", [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            $trangThai = $mucDoHong;
        }

        return new ComponentHealth(
            name: $ten,
            status: $trangThai,
            durationMs: (int) round((hrtime(true) - $batDau) / 1_000_000),
        );
    }

    private function kiemDatabase(): bool
    {
        /** @var array<string, mixed> $cauHinh */
        $cauHinh = Config::array(
            'database.connections.'.Config::string('database.default'),
        );

        /** @var array<int, mixed> $tuyChon */
        $tuyChon = is_array($cauHinh['options'] ?? null) ? $cauHinh['options'] : [];

        // Kết nối riêng, chỉ khác kết nối thường ở chỗ có timeout. Không sửa
        // kết nối chung: hai giây là quá ngắn cho một truy vấn báo cáo nặng.
        $ketNoi = DB::build([
            ...$cauHinh,
            'name' => 'health',
            'options' => $tuyChon + [PDO::ATTR_TIMEOUT => self::TIMEOUT_DATABASE],
        ]);

        // Câu lệnh thật, không phải `getPdo()`. Kết nối của Laravel là lười,
        // nên chỉ lấy đối tượng kết nối thì MySQL tắt vẫn không ai biết.
        $ketNoi->select('select 1');

        // Đóng lại ngay. Bỏ dòng này thì mỗi lần bộ giám sát hỏi lại để rơi
        // một kết nối mở, và MySQL có trần `max_connections`.
        DB::purge('health');

        return true;
    }

    private function kiemCache(): bool
    {
        $moc = (string) hrtime(true);

        Cache::put(self::KHOA_THU, $moc, 10);
        $doc = Cache::get(self::KHOA_THU);

        if ($doc !== $moc) {
            // Ghi được mà đọc ra thứ khác là hỏng thật: Redis hết bộ nhớ sẽ
            // nhận lệnh ghi rồi lặng lẽ vứt đi, và một phép kiểm chỉ-ghi sẽ
            // báo xanh suốt.
            throw new RuntimeException('Cache ghi được nhưng đọc lại không khớp.');
        }

        Cache::forget(self::KHOA_THU);

        return true;
    }

    /**
     * Kho tệp ngoài (Cloudflare R2).
     *
     * Trả `false` — tức là "bỏ qua" — khi chưa có ổ nào dùng R2. Báo đỏ một
     * thành phần chưa được bật là cách nhanh nhất để người trực ban quen với
     * màu đỏ và thôi nhìn nó.
     */
    private function kiemKhoTep(): bool
    {
        if (! $this->dangDungR2()) {
            return false;
        }

        /** @var array<string, mixed> $cauHinh */
        $cauHinh = Config::array('filesystems.disks.r2');

        Storage::build([
            ...$cauHinh,
            'http' => [
                'timeout' => self::TIMEOUT_KHO_TEP,
                'connect_timeout' => self::TIMEOUT_KET_NOI_KHO_TEP,
            ],
        ])->files('/');

        return true;
    }

    /**
     * Có ổ nào đang thật sự dùng R2 không.
     *
     * Kiểm cả hai biến vì tệp đính kèm (`media.disk_name`) và tệp chung
     * (`filesystems.default`) được phép nằm ở hai nơi khác nhau.
     */
    private function dangDungR2(): bool
    {
        return Config::string('filesystems.default') === 'r2'
            || Config::string('media-library.disk_name') === 'r2';
    }
}
