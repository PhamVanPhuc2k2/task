<?php

declare(strict_types=1);

use App\Domain\Payroll\Actions\BuildPayslipAction;
use App\Domain\Payroll\Data\PayslipInput;

/*
|--------------------------------------------------------------------------
| Phép tính phiếu lương
|--------------------------------------------------------------------------
|
| Test THUẦN, không database và không người dùng. Với mã tính tiền thì đó không
| phải sự ngăn nắp mà là điều kiện để kiểm được: mọi trường hợp biên — kỳ toàn
| ngày lễ, lương giờ chia cho 0, người nghỉ không lương cả tháng — đều dựng
| được bằng một dòng, và con số kỳ vọng viết ra tay được.
|
| Mốc dùng xuyên suốt: kỳ 2026-08 có 21 ngày cả ngày (465 phút) và 5 ngày nửa
| buổi (225 phút) = 10.890 phút = 181,5 giờ chuẩn. Lương 10.000.000đ.
|
|     lương giờ = 10.000.000 ÷ 181,5 = 55.096,418732…đ
|
*/

/** Đầu vào mặc định — mỗi test chỉ đổi đúng thứ nó đang kiểm. */
function dauVao(
    int $standard = 10890,
    int $paidLeave = 0,
    int $unpaidLeave = 0,
    int $worked = 10890,
    int $shortfall = 0,
    array $overtime = [],
    string $base = '10000000.00',
    string $allowance = '0.00',
): PayslipInput {
    return new PayslipInput(
        period: '2026-08',
        standardMinutes: $standard,
        paidLeaveMinutes: $paidLeave,
        unpaidLeaveMinutes: $unpaidLeave,
        workedMinutes: $worked,
        shortfallMinutes: $shortfall,
        overtime: $overtime,
        baseSalary: $base,
        allowance: $allowance,
    );
}

function dungPhieu(PayslipInput $vao)
{
    return (new BuildPayslipAction)->execute($vao);
}

it('làm đủ giờ thì nhận đủ lương cộng phụ cấp', function (): void {
    $phieu = dungPhieu(dauVao(allowance: '500000.00'));

    expect($phieu->hourlyRate)->toBe('55096')
        ->and($phieu->shortfallDeduction)->toBe('0')
        ->and($phieu->netTotal)->toBe('10500000');
});

it('không đi làm ngày nào thì bị trừ đúng bằng lương tháng', function (): void {
    /*
    | Phép kiểm tỉnh táo của cả công thức.
    |
    | Thiếu trọn 181,5 giờ chuẩn thì khoản trừ phải bằng đúng lương tháng — nếu
    | không thì lương giờ đang sai. Còn lại đúng phần phụ cấp, vì phụ cấp là
    | khoản cố định không chia theo giờ công.
    */
    $phieu = dungPhieu(dauVao(worked: 0, shortfall: 10890, allowance: '500000.00'));

    expect($phieu->shortfallDeduction)->toBe('10000000')
        ->and($phieu->netTotal)->toBe('500000');
});

it('nghỉ phép năm không bị trừ, nghỉ không lương thì bị', function (): void {
    // Hai ngày cả ngày = 930 phút.
    $coLuong = dungPhieu(dauVao(paidLeave: 930, worked: 9960));
    $khongLuong = dungPhieu(dauVao(unpaidLeave: 930, worked: 9960));

    expect($coLuong->netTotal)->toBe('10000000')
        ->and($coLuong->requiredMinutes)->toBe(9960)
        // 930 phút = 15,5 giờ × 55.096,418732 = 853.994,49…
        ->and($khongLuong->unpaidLeaveDeduction)->toBe('853994')
        ->and($khongLuong->netTotal)->toBe('9146006');
});

it('làm thêm giờ cộng tiền theo hệ số, gom theo từng mức', function (): void {
    $phieu = dungPhieu(dauVao(overtime: [
        ['minutes' => 120, 'percent' => 150],
        ['minutes' => 60, 'percent' => 150],
        ['minutes' => 120, 'percent' => 300],
    ]));

    expect($phieu->overtimeLines)->toHaveCount(2)
        // Sắp từ hệ số thấp lên cao: phiếu của hai tháng phải đọc giống nhau.
        ->and($phieu->overtimeLines[0]->percent)->toBe(150)
        ->and($phieu->overtimeLines[0]->minutes)->toBe(180)
        // 3 giờ × 55.096,418732 × 1,5 = 247.933,88…
        ->and($phieu->overtimeLines[0]->amount)->toBe('247934')
        ->and($phieu->overtimeLines[1]->percent)->toBe(300)
        // 2 giờ × 55.096,418732 × 3 = 330.578,51…
        ->and($phieu->overtimeLines[1]->amount)->toBe('330579')
        ->and($phieu->overtimeMinutes)->toBe(300)
        ->and($phieu->overtimePay)->toBe('578513')
        ->and($phieu->netTotal)->toBe('10578513');
});

it('cộng tay các dòng trên phiếu ra đúng tổng', function (): void {
    /*
    | Người nhận lương sẽ cộng tay để đối chiếu. Nếu tổng được tính theo đường
    | khác — ở độ chính xác cao rồi mới làm tròn một lần — thì nó lệch vài đồng
    | so với phép cộng ấy, và vài đồng lệch trên phiếu lương là đủ để mất niềm
    | tin vào cả bảng.
    */
    $phieu = dungPhieu(dauVao(
        unpaidLeave: 465,
        worked: 9000,
        shortfall: 425,
        overtime: [['minutes' => 95, 'percent' => 200]],
        allowance: '1234567.00',
    ));

    $congTay = (int) $phieu->baseSalary
        + (int) $phieu->allowance
        - (int) $phieu->shortfallDeduction
        - (int) $phieu->unpaidLeaveDeduction
        + (int) $phieu->overtimePay;

    expect((int) $phieu->netTotal)->toBe($congTay);
});

it('kỳ không có ngày công nào thì không nổ, mọi khoản tiền ra 0', function (): void {
    // Chia cho 0 sẽ ném lỗi giữa lúc dựng bảng lương cho cả công ty, và một
    // người vào làm sau khi kỳ kết thúc là đủ để gặp.
    $phieu = dungPhieu(dauVao(standard: 0, worked: 0, shortfall: 0));

    expect($phieu->hourlyRate)->toBe('0')
        ->and($phieu->shortfallDeduction)->toBe('0')
        ->and($phieu->netTotal)->toBe('10000000');
});

it('người chưa được đặt lương thì phiếu ra 0 chứ không nổ', function (): void {
    $phieu = dungPhieu(dauVao(worked: 0, shortfall: 10890, base: '0', allowance: '0'));

    expect($phieu->netTotal)->toBe('0')
        ->and($phieu->shortfallMinutes)->toBe(10890);
});

it('làm tròn NỬA LÊN, không phải cắt cụt', function (): void {
    /*
    | `bcmath` cắt chứ không làm tròn: `bcdiv('20','3',2)` ra 6,66 chứ không
    | phải 6,67. Với một dòng thì lệch một đồng; với ba mươi người mười hai
    | tháng thì lệch một khoản có người sẽ hỏi.
    |
    | Lương 1.000.000 trên 100 giờ chuẩn = 10.000đ/giờ. Thiếu 91 phút =
    | 1,516666… giờ × 10.000 = 15.166,66…đ, làm tròn lên 15.167.
    */
    $phieu = dungPhieu(dauVao(
        standard: 6000,
        worked: 5909,
        shortfall: 91,
        base: '1000000.00',
    ));

    expect($phieu->shortfallDeduction)->toBe('15167');
});
