<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Script vận hành không được chứa `\n` dạng hai ký tự
|--------------------------------------------------------------------------
|
| ## Chuyện đã xảy ra
|
| Một lần sửa file bằng công cụ thay chuỗi ghi ra `\` rồi `n` — hai ký tự —
| thay vì một dấu xuống dòng. Dòng đó thành:
|
|     APP_IMAGE="$IMAGE" FRONTEND_IMAGE="$FRONTEND_IMAGE" \n    docker compose up -d …
|
| Bash đọc `\n` là chữ `n` đã thoát, tức là **một lệnh tên `n`**. Lệnh đó không
| tồn tại, `set -e` cho script thoát ngay, và cả `docker compose up -d` không
| bao giờ chạy.
|
| ## Vì sao `bash -n` không cứu được
|
| Về cú pháp dòng đó hoàn toàn hợp lệ — chỉ là gọi một lệnh không có thật. Kiểm
| cú pháp báo xanh, và mọi con mắt đọc lướt qua đều thấy một dấu tiếp nối bình
| thường. Chỉ có lúc chạy thật mới lộ.
|
| ## Vì sao lỗi này thuộc loại tệ nhất
|
| Nó xảy ra ở BƯỚC 4 của deploy: sao lưu xong, image build xong, migration chạy
| xong — rồi dừng. Container vẫn chạy image CŨ. Máy chủ khoẻ mạnh, health check
| xanh, log sạch, và bản vá vừa build ra không hề được đưa vào dùng. Người vận
| hành thấy dòng lỗi lạ ở cuối một output dài toàn dấu tích.
|
| Test này biến chuyện đó thành ĐỎ ngay trên máy dev.
*/

/**
 * Mọi script shell của dự án, kể cả script nằm trong image Docker.
 *
 * @return list<string> đường dẫn tuyệt đối
 */
function scriptsShell(): array
{
    $goc = dirname(base_path());
    $ket = [];

    foreach ([$goc.'/scripts', $goc.'/docker'] as $thuMuc) {
        if (! is_dir($thuMuc)) {
            continue;
        }

        /** @var iterable<SplFileInfo> $duyet */
        $duyet = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($thuMuc, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($duyet as $tep) {
            if ($tep->isFile() && $tep->getExtension() === 'sh') {
                $ket[] = $tep->getPathname();
            }
        }
    }

    sort($ket);

    return $ket;
}

it('tìm thấy script để kiểm', function (): void {
    // Không có dòng này thì test dưới vẫn xanh khi đường dẫn sai và danh sách
    // rỗng — đúng kiểu "máy dò báo sạch vì máy dò không chạy".
    expect(scriptsShell())->not->toBeEmpty();
});

it('không có script nào chứa `\n` dạng hai ký tự', function (): void {
    $loi = [];

    foreach (scriptsShell() as $duong) {
        $noiDung = file_get_contents($duong);

        if ($noiDung === false) {
            continue;
        }

        foreach (explode("\n", $noiDung) as $so => $dong) {
            // Bỏ qua chỗ `\n` là chủ ý: chuỗi in ra, printf, sed, awk.
            if (preg_match('/\b(echo|printf|sed|awk|tr)\b/', $dong) === 1) {
                continue;
            }

            if (str_contains($dong, '\n')) {
                $loi[] = sprintf('%s:%d — %s', basename($duong), $so + 1, trim($dong));
            }
        }
    }

    expect($loi)->toBe([], implode("\n", array_merge(
        ['`\n` hai ký tự trong script shell. Bash đọc nó là lệnh tên `n`:'],
        $loi,
        ['', 'Thay bằng dấu xuống dòng thật, hoặc `\` cuối dòng để tiếp nối.'],
    )));
});
