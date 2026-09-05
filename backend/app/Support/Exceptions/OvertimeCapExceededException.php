<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use App\Support\Time\HumanTime;

/**
 * Vượt một trong ba trần làm thêm giờ của Điều 107 Bộ luật Lao động 2019.
 *
 * ## Câu chữ phải nói ra TRẦN NÀO, và còn bao nhiêu
 *
 * Ba trần chồng lên nhau — ngày, tháng, năm — nên "vượt trần làm thêm giờ" là
 * câu vô dụng: người nộp không biết nên rút ngắn hôm nay hay dời sang tháng
 * sau. Mỗi thông báo gọi tên trần của nó và nói cả ba con số.
 *
 * ## Đây là trần của LUẬT, không phải chính sách công ty
 *
 * Nói rõ điều đó trong câu chữ: người ta hay đi hỏi quản lý xin nới, mà quản lý
 * cũng không nới được.
 */
final class OvertimeCapExceededException extends DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function trongNgay(string $ngay, int $daDung, int $tran, int $canThem): self
    {
        return new self(sprintf(
            'Ngày %s đã đăng ký %s làm thêm, xin thêm %s là vượt trần %s mỗi ngày theo Bộ luật Lao động.',
            HumanTime::ngay($ngay),
            HumanTime::gioPhut($daDung),
            HumanTime::gioPhut($canThem),
            HumanTime::gioPhut($tran),
        ));
    }

    public static function trongThang(string $ky, int $daDung, int $tran, int $canThem): self
    {
        // "Trong %s" chứ không "Tháng %s": `HumanTime::ky()` đã trả về "tháng
        // 09/2026", nên ghép thêm chữ Tháng ra "Tháng tháng 09/2026".
        return new self(sprintf(
            'Trong %s đã đăng ký %s làm thêm, xin thêm %s là vượt trần %s mỗi tháng theo Bộ luật Lao động.',
            HumanTime::ky($ky),
            HumanTime::gioPhut($daDung),
            HumanTime::gioPhut($canThem),
            HumanTime::gioPhut($tran),
        ));
    }

    public static function trongNam(int $nam, int $daDung, int $tran, int $canThem): self
    {
        return new self(sprintf(
            'Năm %d đã đăng ký %s làm thêm, xin thêm %s là vượt trần %s mỗi năm theo Bộ luật Lao động.',
            $nam,
            HumanTime::gioPhut($daDung),
            HumanTime::gioPhut($canThem),
            HumanTime::gioPhut($tran),
        ));
    }

    public function errorCode(): string
    {
        return 'OVERTIME_CAP_EXCEEDED';
    }

    /**
     * @return array<string, list<string>>
     */
    public function fieldErrors(): array
    {
        return ['start_time' => [$this->getMessage()]];
    }
}
