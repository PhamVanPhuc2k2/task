<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskComment;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');

    // Đĩa giả: test không được ghi tệp thật vào storage của máy dev.
    Storage::fake(config()->string('media-library.disk_name'));
});

/**
 * Tệp có nội dung THẬT trên đĩa, để Symfony tự đoán kiểu MIME từ nội dung.
 *
 * `UploadedFile::fake()` gán sẵn kiểu MIME suy từ phần mở rộng, nên dùng nó để
 * kiểm luật `mimetypes` là tự lừa mình: test sẽ xanh vì chính con số mình vừa
 * bịa ra, chứ không vì mã nguồn đúng.
 */
function tepThat(string $ten, string $noiDung): UploadedFile
{
    $duong = (string) tempnam(sys_get_temp_dir(), 'dinh-kem');
    file_put_contents($duong, $noiDung);

    return new UploadedFile($duong, $ten, null, null, true);
}

/**
 * Bình luận của chính nhân viên, trên task họ được giao.
 *
 * @return array{User, TaskComment}
 */
function binhLuanCuaToi(): array
{
    [, $nhanVien] = sepVaNhanVien();
    $task = Task::factory()->for($nhanVien, 'assignee')->create();
    $comment = TaskComment::factory()->for($task)->for($nhanVien, 'author')->create();

    return [$nhanVien, $comment];
}

it('đính kèm được ảnh vào bình luận của mình', function (): void {
    [$nhanVien, $comment] = binhLuanCuaToi();

    $this->actingAs($nhanVien)
        ->post("/api/v1/comments/{$comment->uuid}/attachments", [
            'files' => [UploadedFile::fake()->image('ban-thiet-ke.jpg', 800, 600)],
        ])
        ->assertCreated()
        ->assertJsonPath('data.attachments.0.is_image', true)
        ->assertJsonPath('data.attachments.0.name', 'ban-thiet-ke');

    expect($comment->getMedia(TaskComment::DINH_KEM))->toHaveCount(1);
});

it('không giữ tên tệp gốc trên đĩa', function (): void {
    // Giữ tên gốc là mở đường cho traversal, ký tự điều khiển, và trùng tên
    // giữa hai người tải cùng lúc. Tên gốc chỉ giữ lại để hiển thị.
    [$nhanVien, $comment] = binhLuanCuaToi();

    $this->actingAs($nhanVien)
        ->post("/api/v1/comments/{$comment->uuid}/attachments", [
            'files' => [UploadedFile::fake()->image('../../etc/passwd.png')],
        ])
        ->assertCreated();

    $media = $comment->getMedia(TaskComment::DINH_KEM)->firstOrFail();

    expect($media->file_name)->toMatch('/^[0-9a-f-]{36}\.(png|jpg|jpeg|gif|webp)$/')
        ->and($media->file_name)->not->toContain('..')
        ->and($media->file_name)->not->toContain('/');
});

it('chặn tệp thực thi dù đã đổi phần mở rộng thành .jpg', function (): void {
    // Đây là lý do dùng luật `mimetypes` chứ không phải `mimes`: luật kia chỉ
    // nhìn phần mở rộng, đổi tên là qua được.
    [$nhanVien, $comment] = binhLuanCuaToi();

    $tep = tepThat('anh-vo-hai.jpg', "<?php system(\$_GET['cmd']); ?>\n");

    $this->actingAs($nhanVien)
        ->post("/api/v1/comments/{$comment->uuid}/attachments", ['files' => [$tep]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('files.0');

    expect($comment->getMedia(TaskComment::DINH_KEM))->toBeEmpty();
});

it('chặn tệp SVG', function (): void {
    // SVG chứa được JavaScript và chạy trong ngữ cảnh tên miền của mình khi mở
    // trực tiếp — tức là XSS lưu trữ.
    [$nhanVien, $comment] = binhLuanCuaToi();

    $svg = tepThat(
        'logo.svg',
        '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
    );

    $this->actingAs($nhanVien)
        ->post("/api/v1/comments/{$comment->uuid}/attachments", ['files' => [$svg]])
        ->assertStatus(422);
});

it('chặn tệp vượt quá 10 MB', function (): void {
    [$nhanVien, $comment] = binhLuanCuaToi();

    $this->actingAs($nhanVien)
        ->post("/api/v1/comments/{$comment->uuid}/attachments", [
            'files' => [UploadedFile::fake()->create('to-qua.pdf', 11 * 1024)],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('files.0');
});

it('chặn tải quá 5 tệp một lượt', function (): void {
    [$nhanVien, $comment] = binhLuanCuaToi();

    $nhieu = array_map(
        fn (int $i) => UploadedFile::fake()->image("anh-{$i}.png"),
        range(1, 6),
    );

    $this->actingAs($nhanVien)
        ->post("/api/v1/comments/{$comment->uuid}/attachments", ['files' => $nhieu])
        ->assertStatus(422)
        ->assertJsonValidationErrors('files');
});

it('không đính kèm được vào bình luận của người khác', function (): void {
    [$sep] = sepVaNhanVien();
    [, $comment] = binhLuanCuaToi();

    $this->actingAs($sep)
        ->post("/api/v1/comments/{$comment->uuid}/attachments", [
            'files' => [UploadedFile::fake()->image('chen-ngang.png')],
        ])
        ->assertStatus(403);
});

it('gỡ được tệp đính kèm của mình', function (): void {
    [$nhanVien, $comment] = binhLuanCuaToi();

    $this->actingAs($nhanVien)->post("/api/v1/comments/{$comment->uuid}/attachments", [
        'files' => [UploadedFile::fake()->image('nham.png')],
    ])->assertCreated();

    $mediaUuid = $comment->refresh()->getMedia(TaskComment::DINH_KEM)->firstOrFail()->uuid;

    $this->actingAs($nhanVien)
        ->deleteJson("/api/v1/comments/{$comment->uuid}/attachments/{$mediaUuid}")
        ->assertNoContent();

    expect($comment->refresh()->getMedia(TaskComment::DINH_KEM))->toBeEmpty();
});

it('không gỡ được tệp không thuộc bình luận đang thao tác', function (): void {
    // Không ràng buộc vào đúng bình luận thì đoán uuid là xoá được tệp bất kỳ.
    [$nhanVien, $comment] = binhLuanCuaToi();
    [$nguoiKhac, $binhLuanKhac] = binhLuanCuaToi();

    $this->actingAs($nguoiKhac)->post("/api/v1/comments/{$binhLuanKhac->uuid}/attachments", [
        'files' => [UploadedFile::fake()->image('cua-nguoi-khac.png')],
    ])->assertCreated();

    $mediaUuid = $binhLuanKhac->refresh()
        ->getMedia(TaskComment::DINH_KEM)->firstOrFail()->uuid;

    $this->actingAs($nhanVien)
        ->deleteJson("/api/v1/comments/{$comment->uuid}/attachments/{$mediaUuid}")
        ->assertStatus(404);

    expect($binhLuanKhac->refresh()->getMedia(TaskComment::DINH_KEM))->toHaveCount(1);
});

it('trả về tệp đính kèm khi đọc danh sách bình luận', function (): void {
    [$nhanVien, $comment] = binhLuanCuaToi();

    $this->actingAs($nhanVien)->post("/api/v1/comments/{$comment->uuid}/attachments", [
        'files' => [tepThat('bao-gia.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n")],
    ])->assertCreated();

    $this->actingAs($nhanVien)
        ->getJson("/api/v1/tasks/{$comment->task->uuid}/comments")
        ->assertOk()
        ->assertJsonPath('data.0.attachments.0.name', 'bao-gia')
        ->assertJsonPath('data.0.attachments.0.is_image', false);
});
