<?php

declare(strict_types=1);

namespace App\Support;

use Random\RandomException;

/**
 * Sinh mật khẩu tạm để **người đọc cho người khác chép tay**.
 *
 * Khác hẳn mật khẩu người dùng tự đặt: chuỗi này tồn tại đúng một lần, đi qua
 * miệng hoặc một tin nhắn, rồi bị thay ngay. Nên tiêu chí không phải "càng
 * nhiều loại ký tự càng tốt" mà là **chép lại không sai**.
 *
 * `Str::password()` của Laravel sinh cả ký hiệu: một mật khẩu như
 * `1DIqbw\a]ML3En.i` có dấu chéo ngược, ngoặc vuông, và cặp `1`/`I` nhìn giống
 * hệt nhau trên phần lớn phông chữ. Nhân sự đọc chuỗi đó qua điện thoại cho
 * nhân viên mới là cầm chắc phải đọc lại ba lần — hoặc tệ hơn, người kia gõ
 * sai năm lần rồi bị khoá tài khoản năm phút vì chống dò mật khẩu.
 *
 * Bảng chữ ở đây bỏ hết ký hiệu và mọi ký tự dễ nhầm: `0 O o`, `1 l I`, `5 S`,
 * `2 Z`, `8 B`. Còn lại 49 ký tự; 16 ký tự cho ra khoảng 90 bit ngẫu nhiên —
 * nhiều hơn hẳn mức cần cho một chuỗi sống vài phút.
 */
final class TemporaryPassword
{
    /** Không có 0 O o 1 l I 5 S 2 Z 8 B — những cặp nhìn giống nhau. */
    private const string BANG_CHU = 'ACDEFGHJKLMNPQRTUVWXYacdefghijkmnpqrtuvwxy34679';

    private const int DO_DAI = 16;

    /**
     * @throws RandomException khi hệ điều hành không cấp được số ngẫu nhiên an toàn
     */
    public static function generate(): string
    {
        $bang = self::BANG_CHU;
        $can = strlen($bang) - 1;

        $ra = '';

        for ($i = 0; $i < self::DO_DAI; $i++) {
            // random_int chứ không phải rand/mt_rand: hai hàm kia dự đoán được
            // và không dùng cho bất cứ thứ gì liên quan tới bảo mật.
            $ra .= $bang[random_int(0, $can)];
        }

        return $ra;
    }
}
