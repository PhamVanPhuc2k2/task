<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Bản kết xuất schema cho Larastan phải còn khớp với database
|--------------------------------------------------------------------------
|
| ## Vì sao có file đó
|
| Bộ phân tích migration của Larastan sập khi tổng độ dài tên các file migration
| vượt một ngưỡng: nó **âm thầm** bỏ qua những file đầu tiên, làm mất schema của
| `users`, `tasks`… rồi sinh ra hàng trăm lỗi giả ở khắp nơi TRỪ chỗ thật sự có
| vấn đề. Đã nổ hai lần — 19/08 và 25/08.
|
| Cách sửa là cho Larastan đọc `database/larastan-schema/mysql-schema.sql` thay
| vì tự phân tích 30 file migration. Điểm quan trọng: file nằm ở
| `larastan-schema`, KHÔNG ở `database/schema` — nên Laravel không đọc nó, và
| hành vi migrate cùng cách test dựng lại database không đổi một chút nào.
|
| ## Vì sao cần test này
|
| File kết xuất là ảnh chụp, nên nó **mục dần**. Thêm một migration mà quên tạo
| lại thì Larastan không biết bảng mới, và mọi chỗ đọc cột của bảng đó báo "cột
| không tồn tại" — lại đúng loại lỗi im lặng mà cả cách sửa này sinh ra để bỏ.
|
| Test này biến việc quên thành ĐỎ, có kèm câu lệnh phải chạy.
|
*/

it('bản kết xuất schema phủ đủ mọi bảng trong database', function (): void {
    $duong = database_path('larastan-schema/mysql-schema.sql');

    expect(file_exists($duong))->toBeTrue(
        "Thiếu {$duong}. Tạo lại bằng: composer larastan:schema",
    );

    $sql = (string) file_get_contents($duong);

    // Bảng thật trong database test — đã migrate đầy đủ nhờ RefreshDatabase.
    $thuc = array_map(
        static fn (object $r): string => (string) array_values((array) $r)[0],
        DB::select('SHOW TABLES'),
    );

    $thieu = array_values(array_filter(
        $thuc,
        static fn (string $bang): bool => ! str_contains($sql, "CREATE TABLE `{$bang}`"),
    ));

    expect($thieu)->toBe([], sprintf(
        "Bản kết xuất schema cho Larastan thiếu %d bảng:\n  - %s\n\n".
        'Chạy lại: composer larastan:schema',
        count($thieu),
        implode("\n  - ", $thieu),
    ));
});

it('không còn giữ bảng đã bị xoá khỏi database', function (): void {
    /*
    | Chiều ngược lại, và cũng cần: bảng đã xoá mà còn trong file thì Larastan
    | vẫn cho phép đọc cột của nó. Mã trỏ tới một bảng không còn tồn tại sẽ qua
    | được phân tích tĩnh, rồi nổ lúc chạy thật.
    */
    $sql = (string) file_get_contents(
        database_path('larastan-schema/mysql-schema.sql'),
    );

    preg_match_all('/CREATE TABLE `([a-z0-9_]+)`/i', $sql, $khop);

    $thuc = array_map(
        static fn (object $r): string => (string) array_values((array) $r)[0],
        DB::select('SHOW TABLES'),
    );

    $thua = array_values(array_diff($khop[1], $thuc));

    expect($thua)->toBe([], sprintf(
        "Bản kết xuất còn giữ %d bảng không còn trong database:\n  - %s\n\n".
        'Chạy lại: composer larastan:schema',
        count($thua),
        implode("\n  - ", $thua),
    ));
});
