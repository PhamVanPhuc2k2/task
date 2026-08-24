<?php

declare(strict_types=1);

use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskComment;
use App\Support\Media\MediaUrl;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Đường dẫn xem tệp đính kèm phải đổi kiểu theo ổ đĩa đang giữ tệp.
 *
 * Ký một đường dẫn S3 là **phép tính thuần cục bộ** — HMAC trên chuỗi yêu cầu,
 * không gọi mạng. Nên bộ test này ép ổ `r2` bằng khoá giả và vẫn kiểm được chữ
 * ký thật, không cần bucket, không cần Internet, chạy được trong CI.
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('public');

    // Khoá giả, endpoint giả. Đủ để bộ ký của AWS làm việc.
    config()->set('filesystems.disks.r2', [
        'driver' => 's3',
        'key' => 'khoa-gia',
        'secret' => 'bi-mat-gia',
        'region' => 'auto',
        'bucket' => 'explus-media',
        'url' => null,
        'endpoint' => 'https://tai-khoan-gia.r2.cloudflarestorage.com',
        'use_path_style_endpoint' => true,
        'visibility' => null,
        'throw' => true,
    ]);
    Storage::forgetDisk('r2');
});

function anhDinhKem(): Media
{
    $task = Task::factory()->create();
    $comment = TaskComment::factory()->for($task)->create();

    return $comment
        ->addMedia(UploadedFile::fake()->image('so-do.jpg', 400, 300))
        ->toMediaCollection(TaskComment::DINH_KEM, 'public');
}

it('trả đường dẫn thẳng khi ổ đĩa có địa chỉ công khai', function (): void {
    $media = anhDinhKem();

    $url = MediaUrl::for($media);

    expect($url)
        ->toContain($media->file_name)
        // Không có chữ ký: đường dẫn ổn định nên trình duyệt cache lại được.
        ->not->toContain('X-Amz-Signature');
});

it('ký đường dẫn có hạn khi ổ đĩa riêng tư', function (): void {
    $media = anhDinhKem();
    // Giả cảnh sau khi đổi MEDIA_DISK sang r2: bản ghi trỏ về ổ riêng tư.
    $media->disk = 'r2';

    $url = MediaUrl::for($media);

    expect($url)
        ->toStartWith('https://tai-khoan-gia.r2.cloudflarestorage.com/explus-media/')
        ->toContain('X-Amz-Signature=')
        ->toContain('X-Amz-Credential=khoa-gia');

    // Hạn ~30 phút, đúng MEDIA_TEMPORARY_URL_DEFAULT_LIFETIME.
    //
    // Không khớp đúng 1800: bộ ký của AWS lấy mốc bắt đầu từ `time()` của PHP
    // chứ không từ Carbon, nên vài mili-giây trôi giữa lúc tính hạn và lúc ký
    // là con số rơi xuống 1799. Khoá cứng 1800 thì test đỏ ngẫu nhiên.
    expect((int) preg_replace('/^.*X-Amz-Expires=(\d+).*$/s', '$1', $url))
        ->toBeGreaterThan(1700)
        ->toBeLessThanOrEqual(1800);
});

/**
 * Đây là lỗi mà `MediaUrl` sinh ra để chặn: gọi thẳng `getUrl()` trên ổ R2
 * riêng tư vẫn cho ra một chuỗi trông như URL, không ném lỗi, không có gì đỏ —
 * chỉ là mở lên thì R2 trả 403. Test này khoá lại sự khác biệt đó.
 */
it('khác hẳn getUrl() trên cùng một tệp ở ổ riêng tư', function (): void {
    $media = anhDinhKem();
    $media->disk = 'r2';

    expect($media->getUrl())->not->toContain('X-Amz-Signature')
        ->and(MediaUrl::for($media))->toContain('X-Amz-Signature');
});

it('chọn ổ theo bản ghi chứ không theo cấu hình mặc định', function (): void {
    // Ứng dụng đã chuyển sang R2, nhưng tệp cũ vẫn nằm ở đĩa public.
    config()->set('media-library.disk_name', 'r2');
    $media = anhDinhKem();

    expect(MediaUrl::for($media))->not->toContain('X-Amz-Signature');
});

it('ký cả bản thu nhỏ theo ổ của bản thu nhỏ', function (): void {
    $media = anhDinhKem();
    $media->conversions_disk = 'r2';

    expect(MediaUrl::for($media, 'thumb'))->toContain('X-Amz-Signature');
    // Bản gốc vẫn ở ổ công khai nên không bị ký lây.
    expect(MediaUrl::for($media))->not->toContain('X-Amz-Signature');
});

it('coi ổ đĩa có url RỖNG là riêng tư, không phải công khai', function (): void {
    /*
    | Bẫy thật, đã mắc khi bật R2 lần đầu.
    |
    | `R2_PUBLIC_URL=` trong tệp .env cho ra **chuỗi rỗng**, không phải `null`.
    | Phép kiểm `!== null` vì thế trả về true, và cả hệ thống kết luận bucket
    | riêng tư kia là công khai.
    |
    | Hậu quả im lặng đúng kiểu tệ nhất: tệp vẫn tải lên được, `getUrl()` vẫn
    | trả về một chuỗi, không có ngoại lệ nào. Chỉ là chuỗi đó là đường dẫn
    | tương đối `/3/tep.txt` — hỏng với mọi người xem, và không ai biết cho tới
    | khi có người bấm vào.
    |
    | Bỏ hẳn dòng đó khỏi .env cũng sửa được, nhưng để mã tự chống là đúng hơn:
    | một dòng env bỏ trống là thứ quá dễ xảy ra để phụ thuộc vào trí nhớ.
    */
    Config::set('filesystems.disks.o-rong', [
        'driver' => 's3',
        'key' => 'k',
        'secret' => 's',
        'region' => 'auto',
        'bucket' => 'b',
        'endpoint' => 'https://vi-du.r2.cloudflarestorage.com',
        'use_path_style_endpoint' => true,
        // Đúng thứ `R2_PUBLIC_URL=` sinh ra.
        'url' => '',
    ]);

    $media = anhDinhKem();
    $media->disk = 'o-rong';

    expect(MediaUrl::for($media))->toContain('X-Amz-Signature');
});
