<?php

declare(strict_types=1);

use App\Support\TemporaryPassword;

/*
|--------------------------------------------------------------------------
| Mật khẩu tạm phải chép tay được
|--------------------------------------------------------------------------
|
| Đây là chuỗi nhân sự đọc cho nhân viên mới qua điện thoại hoặc tin nhắn.
| Tiêu chí không phải "càng nhiều loại ký tự càng tốt" mà là chép lại không
| sai — gõ nhầm năm lần là bị khoá tài khoản năm phút vì chống dò mật khẩu.
|
*/

it('không chứa ký hiệu khó đọc qua điện thoại', function (): void {
    for ($i = 0; $i < 200; $i++) {
        expect(TemporaryPassword::generate())->toMatch('/^[A-Za-z0-9]+$/');
    }
});

it('không chứa ký tự dễ nhầm với nhau', function (): void {
    // 0/O/o, 1/l/I, 5/S, 2/Z, 8/B nhìn giống hệt nhau trên phần lớn phông chữ.
    $deNham = ['0', 'O', 'o', '1', 'l', 'I', '5', 'S', '2', 'Z', '8', 'B'];

    for ($i = 0; $i < 200; $i++) {
        $mk = TemporaryPassword::generate();

        foreach ($deNham as $kyTu) {
            expect($mk)->not->toContain($kyTu);
        }
    }
});

it('dài 16 ký tự', function (): void {
    expect(strlen(TemporaryPassword::generate()))->toBe(16);
});

it('không sinh trùng nhau', function (): void {
    $mk = [];

    for ($i = 0; $i < 500; $i++) {
        $mk[] = TemporaryPassword::generate();
    }

    expect(array_unique($mk))->toHaveCount(500);
});

it('dùng đủ dải ký tự, không kẹt ở một nhóm', function (): void {
    // Nếu bảng chữ bị cắt nhầm hoặc chỉ số lệch, chuỗi vẫn "chạy" nhưng độ
    // ngẫu nhiên tụt hẳn mà không có gì báo.
    $gop = '';

    for ($i = 0; $i < 200; $i++) {
        $gop .= TemporaryPassword::generate();
    }

    expect(count(array_unique(str_split($gop))))->toBeGreaterThanOrEqual(40);
});
