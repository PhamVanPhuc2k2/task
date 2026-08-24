<?php

declare(strict_types=1);

use App\Support\Enums\HealthStatus;
use App\Support\Health\SystemHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Endpoint tình trạng hạ tầng
|--------------------------------------------------------------------------
|
| Phần đắt giá nhất của bộ này không phải "gọi ra 200", mà là hai điều dễ làm
| sai và không tự lộ ra:
|
|   1. Endpoint mở công khai, nên nó KHÔNG được để lọt thông tin nội bộ.
|   2. Kho tệp hỏng KHÔNG được kéo cả máy chủ ra khỏi vòng phục vụ.
|
*/

it('trả về tình trạng mà không cần đăng nhập', function (): void {
    // Bộ giám sát không có tài khoản. Một phép kiểm chỉ chạy được khi đăng
    // nhập được thì vô dụng đúng lúc database sập.
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonStructure([
            'status',
            'components' => [['name', 'status', 'duration_ms']],
        ]);
});

it('kiểm đủ database, cache và kho tệp', function (): void {
    $ten = collect($this->getJson('/api/v1/health')->json('components'))
        ->pluck('name')
        ->all();

    expect($ten)->toBe(['database', 'cache', 'storage']);
});

it('không để lọt bất cứ thông tin nội bộ nào', function (): void {
    /*
    | Đây là test quan trọng nhất của file. Cách hỏng tự nhiên nhất của một
    | endpoint health check là ai đó thêm `'error' => $e->getMessage()` cho dễ
    | gỡ lỗi — và thông điệp lỗi của driver database kèm sẵn tên máy chủ, tên
    | database, tên tài khoản. Endpoint này mở cho cả Internet.
    */
    $raw = $this->getJson('/api/v1/health')->getContent();

    expect($raw)->toBeString();

    $cam = [
        config()->string('database.connections.mysql.host'),
        config()->string('database.connections.mysql.database'),
        config()->string('database.connections.mysql.username'),
        'mysql', 'redis', 'password', 'exception', 'SQLSTATE',
    ];

    foreach ($cam as $tu) {
        if ($tu === '') {
            continue;
        }

        expect(mb_strtolower((string) $raw))->not->toContain(mb_strtolower($tu));
    }
});

it('bỏ qua kho tệp khi chưa bật R2', function (): void {
    // Báo đỏ một thành phần chưa được dùng tới là cách nhanh nhất để người trực
    // ban quen với màu đỏ rồi thôi nhìn nó.
    config()->set('filesystems.default', 'local');
    config()->set('media-library.disk_name', 'public');

    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('components.2.status', 'skipped');
});

it('kho tệp hỏng chỉ là suy giảm, vẫn trả 200', function (): void {
    /*
    | Rút cả máy chủ ra khỏi vòng phục vụ chỉ vì không mở được ảnh là đổi một
    | sự cố nhỏ lấy một sự cố lớn: mọi người mất luôn giao việc, chấm công và
    | báo cáo, trong khi thứ hỏng chỉ là tệp đính kèm.
    */
    config()->set('media-library.disk_name', 'r2');
    config()->set('filesystems.disks.r2', [
        'driver' => 's3',
        'key' => 'khoa-gia',
        'secret' => 'bi-mat-gia',
        'region' => 'auto',
        'bucket' => 'khong-ton-tai',
        'url' => null,
        // Cổng không ai lắng nghe — kết nối bị từ chối ngay, không phải chờ.
        'endpoint' => 'http://127.0.0.1:9',
        'use_path_style_endpoint' => true,
        'throw' => true,
    ]);

    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('components.2.status', 'degraded');
});

it('trả 503 khi cache chết', function (): void {
    // Không tắt được MySQL từ trong test, nên lấy cache làm đại diện cho nhóm
    // "thành phần lõi": cùng đường xử lý, cùng mức độ hỏng.
    Cache::shouldReceive('put')->andThrow(new RuntimeException('mất kết nối'));

    $this->getJson('/api/v1/health')
        ->assertStatus(503)
        ->assertJsonPath('status', 'down')
        ->assertJsonPath('components.1.status', 'down');
});

/*
|--------------------------------------------------------------------------
| Luật gộp trạng thái
|--------------------------------------------------------------------------
*/

it('trạng thái chung lấy mức nặng nhất', function (): void {
    expect(HealthStatus::Ok->worseOf(HealthStatus::Degraded))->toBe(HealthStatus::Degraded)
        ->and(HealthStatus::Degraded->worseOf(HealthStatus::Down))->toBe(HealthStatus::Down)
        ->and(HealthStatus::Down->worseOf(HealthStatus::Ok))->toBe(HealthStatus::Down);
});

it('thành phần bị bỏ qua không kéo tình trạng chung xuống', function (): void {
    expect(HealthStatus::Ok->worseOf(HealthStatus::Skipped))->toBe(HealthStatus::Ok)
        ->and(HealthStatus::Skipped->worseOf(HealthStatus::Ok))->toBe(HealthStatus::Ok);
});

it('chỉ mức Down mới làm request thất bại', function (): void {
    expect(HealthStatus::Down->shouldFailRequest())->toBeTrue()
        ->and(HealthStatus::Degraded->shouldFailRequest())->toBeFalse()
        ->and(HealthStatus::Ok->shouldFailRequest())->toBeFalse()
        ->and(HealthStatus::Skipped->shouldFailRequest())->toBeFalse();
});

it('phát hiện được cache ghi vào rồi mất', function (): void {
    /*
    | Redis hết bộ nhớ sẽ NHẬN lệnh ghi rồi lặng lẽ vứt đi. Một phép kiểm chỉ
    | gọi `put()` rồi coi là xong sẽ báo xanh suốt trong tình huống đó — nên
    | phép kiểm thật phải đọc lại và so.
    */
    Cache::shouldReceive('put')->andReturnTrue();
    Cache::shouldReceive('get')->andReturnNull();

    $ketQua = app(SystemHealth::class)->check();

    expect($ketQua[1]->name)->toBe('cache')
        ->and($ketQua[1]->status)->toBe(HealthStatus::Down);
});

it('không tạo rác trên đĩa khi kiểm kho tệp', function (): void {
    // Phép kiểm chạy 30 giây một lần, mãi mãi. Ghi một tệp mỗi lần là sau một
    // năm có một triệu tệp rác phải trả tiền lưu trữ.
    Storage::fake('r2');
    config()->set('media-library.disk_name', 'r2');

    app(SystemHealth::class)->check();

    expect(Storage::disk('r2')->allFiles())->toBeEmpty();
});
