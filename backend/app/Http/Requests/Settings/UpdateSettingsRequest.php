<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Support\Settings\SettingKey;
use App\Support\Settings\SettingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Kiểm dữ liệu cài đặt trang.
 *
 * Phần kiểm ở đây quan trọng hơn phần lưu. Một ca làm vô lý — tan làm trước giờ
 * vào làm — **không làm hệ thống báo lỗi**: nó chỉ khiến mọi phép tính giờ ra
 * số âm hoặc số 0, im lặng, cho tới khi có người thắc mắc "sao tháng này ai
 * cũng 0 giờ".
 */
final class UpdateSettingsRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $luat = [
            'values' => ['required', 'array', 'min:1'],
        ];

        foreach (SettingKey::cases() as $k) {
            if (self::laTepAnh($k)) {
                continue;
            }

            $luat["values.{$k->value}"] = ['sometimes', ...$this->luatCuaKhoa($k)];
        }

        return $luat;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $this->kiemCaLam($v);
            $this->kiemLichTuan($v);
            $this->kiemKhoaLa($v);
        });
    }

    /**
     * Trả `null` về chuỗi rỗng cho hai danh sách thứ trong tuần.
     *
     * Middleware `ConvertEmptyStringsToNull` của Laravel biến mọi chuỗi rỗng
     * trong request thành `null` — mà "công ty không làm nửa buổi ngày nào" đúng
     * là một danh sách rỗng, và người dùng bỏ trống ô đó là đang nói điều ấy.
     *
     * Để nguyên `null` thì hỏng ở hai tầng cùng lúc: luật `string` từ chối nó,
     * và nếu lọt qua thì `SiteSettings::apDungVaoConfig()` **bỏ qua** giá trị
     * null — nghĩa là config giữ nguyên giá trị CŨ. Giám đốc bấm lưu, thấy báo
     * thành công, và thứ bảy vẫn là ngày làm việc.
     */
    protected function prepareForValidation(): void
    {
        /** @var array<string, mixed> $gui */
        $gui = $this->input('values', []);

        foreach ([SettingKey::WorkDaysFull, SettingKey::WorkDaysHalf] as $k) {
            if (array_key_exists($k->value, $gui) && $gui[$k->value] === null) {
                $gui[$k->value] = '';
            }
        }

        $this->merge(['values' => $gui]);
    }

    /**
     * Khoá trỏ tới một TỆP, không phải một giá trị gõ tay.
     *
     * Logo và biểu tượng đi qua đường upload riêng. Form này không nhận chúng —
     * và `kiemKhoaLa()` bên dưới từ chối hẳn, chứ không chỉ bỏ qua.
     *
     * Vì sao phải từ chối hẳn: `values` có luật của chính nó (`array`), nên
     * `validated()` trả về **cả mảng**, kể cả những khoá không có luật riêng.
     * Chỉ `continue` ở vòng lặp trên thì một `values.logo_path` gửi kèm vẫn đi
     * thẳng xuống `setRaw()` và ghi đè đường dẫn tệp bằng chuỗi tuỳ ý.
     */
    private static function laTepAnh(SettingKey $k): bool
    {
        return $k === SettingKey::LogoPath || $k === SettingKey::IconPath;
    }

    /**
     * @return list<mixed>
     */
    private function luatCuaKhoa(SettingKey $k): array
    {
        /*
        | Danh sách thứ trong tuần: chuỗi rỗng LÀ hợp lệ.
        |
        | Dùng `present` chứ không `required` vì "không có ngày nửa buổi nào" là
        | một câu trả lời đúng, mà `required` từ chối chuỗi rỗng. Đây là chỗ dễ
        | sai vì nhìn thì `required` có vẻ chặt hơn.
        */
        if ($k === SettingKey::WorkDaysFull || $k === SettingKey::WorkDaysHalf) {
            return ['present', 'string', 'max:20', 'regex:/^$|^[0-6](,[0-6])*$/'];
        }

        // Giờ trên đồng hồ: `H:i` chứ không `date`, vì đây là mốc trong ngày
        // chứ không phải một thời điểm.
        if (str_starts_with($k->value, 'shift_') && $k->type() === SettingType::Text) {
            return ['required', 'date_format:H:i'];
        }

        if ($k === SettingKey::ReportReminderAt) {
            return ['required', 'date_format:H:i'];
        }

        return match ($k->type()) {
            SettingType::Integer => ['required', 'integer', ...$this->khoangSo($k)],
            SettingType::Boolean => ['required', 'boolean'],
            SettingType::Text => ['required', 'string', 'max:120'],
        };
    }

    /**
     * Khoảng cho phép của từng con số.
     *
     * Có mốc trên chứ không chỉ `min:0`: `leave_max_days` để 100000 thì một đơn
     * gõ nhầm năm sẽ miễn chấm công cho hai trăm năm, và `report_backfill_days`
     * quá lớn thì báo cáo của kỳ đã chốt sửa lại được.
     *
     * @return list<string>
     */
    private function khoangSo(SettingKey $k): array
    {
        return match ($k) {
            SettingKey::ShiftGraceMinutes => ['min:0', 'max:120'],
            SettingKey::MaxDailyMinutesHalf => ['min:0', 'max:1440'],
            SettingKey::MinWorkedMinutes => ['min:1', 'max:480'],
            SettingKey::ReportBackfillDays => ['min:0', 'max:30'],
            SettingKey::LeaveBackdateDays => ['min:0', 'max:365'],
            SettingKey::LeaveFutureDays => ['min:1', 'max:730'],
            SettingKey::LeaveMaxDays => ['min:1', 'max:90'],
            default => ['min:0'],
        };
    }

    /**
     * Bốn mốc của ca làm phải tăng dần: vào làm < nghỉ trưa < hết trưa < tan làm.
     *
     * Kiểm trên giá trị **sau khi trộn** với cài đặt hiện tại, không chỉ trên
     * phần vừa gửi: sửa mỗi `shift_end` thành 07:00 mà giữ nguyên giờ vào làm
     * 08:15 vẫn là một ca vô lý, và một form chỉ gửi một trường thì không có
     * cách nào tự phát hiện.
     */
    private function kiemCaLam(Validator $v): void
    {
        $moc = [
            SettingKey::ShiftMorningStart,
            SettingKey::ShiftLunchStart,
            SettingKey::ShiftLunchEnd,
            SettingKey::ShiftEnd,
        ];

        /** @var array<string, mixed> $gui */
        $gui = $this->input('values', []);

        $gio = [];

        foreach ($moc as $k) {
            $gt = $gui[$k->value] ?? config($k->configPath() ?? '');

            if (! is_string($gt) || preg_match('/^\d{2}:\d{2}$/', $gt) !== 1) {
                return; // Sai định dạng đã có luật khác báo; đừng báo hai lần.
            }

            $gio[$k->value] = $gt;
        }

        $thuTu = array_values($gio);
        $khoa = array_keys($gio);

        for ($i = 1; $i < count($thuTu); $i++) {
            if ($thuTu[$i] > $thuTu[$i - 1]) {
                continue;
            }

            // Gắn lỗi vào trường NGƯỜI DÙNG VỪA SỬA nếu có, để câu lỗi hiện
            // cạnh đúng ô họ đang gõ. Không có thì gắn vào mốc sau.
            $oLoi = isset($gui[$khoa[$i]]) ? $khoa[$i] : $khoa[$i - 1];

            $v->errors()->add(
                "values.{$oLoi}",
                sprintf(
                    'Ca làm phải theo thứ tự tăng dần: vào làm (%s) < nghỉ trưa (%s) < hết trưa (%s) < tan làm (%s).',
                    ...$thuTu,
                ),
            );

            return;
        }
    }

    /**
     * Lịch tuần phải nói được điều gì đó có nghĩa.
     *
     * Hai luật, và cả hai đều chặn một cách hỏng IM LẶNG:
     *
     * Một thứ nằm ở cả hai danh sách thì `WorkWeek` lấy nhánh "cả ngày" trước —
     * đúng theo mã, nhưng người đặt cấu hình tưởng mình khai nửa buổi. Không có
     * gì báo, và họ chỉ biết khi nhân viên thắc mắc sao thứ bảy vẫn tính muộn
     * tới 17h30.
     *
     * Không có ngày làm việc nào thì cả công ty không bao giờ bị tính đi muộn,
     * không bao giờ được nhắc nộp báo cáo, và mọi ngày đều là ngày nghỉ. Hệ
     * thống vẫn chạy, vẫn xanh, chỉ là ngừng làm việc của nó.
     *
     * Kiểm trên giá trị SAU KHI TRỘN với cài đặt hiện tại, cùng lý do với
     * `kiemCaLam()`: một form chỉ gửi một trường thì không tự phát hiện được.
     */
    private function kiemLichTuan(Validator $v): void
    {
        /** @var array<string, mixed> $gui */
        $gui = $this->input('values', []);

        $doc = function (SettingKey $k) use ($gui): array {
            $tho = $gui[$k->value] ?? config($k->configPath() ?? '');

            if (! is_string($tho)) {
                return [];
            }

            return array_values(array_filter(
                array_map(trim(...), explode(',', $tho)),
                fn (string $x): bool => preg_match('/^[0-6]$/', $x) === 1,
            ));
        };

        /*
        | Ca nửa buổi cũng phải theo thứ tự, cùng lý do với ca cả ngày: tan
        | trước giờ vào làm không báo lỗi gì, nó chỉ làm `expectedMinutes()` ra
        | số âm và mọi phép tính sau đó vô nghĩa, im lặng.
        */
        $gioVao = $gui[SettingKey::ShiftMorningStart->value]
            ?? config(SettingKey::ShiftMorningStart->configPath() ?? '');
        $gioTanNua = $gui[SettingKey::ShiftHalfEnd->value]
            ?? config(SettingKey::ShiftHalfEnd->configPath() ?? '');

        if (is_string($gioVao) && is_string($gioTanNua)
            && preg_match('/^\d{2}:\d{2}$/', $gioVao) === 1
            && preg_match('/^\d{2}:\d{2}$/', $gioTanNua) === 1
            && $gioTanNua <= $gioVao
        ) {
            $v->errors()->add(
                'values.'.SettingKey::ShiftHalfEnd->value,
                sprintf('Giờ tan ngày nửa buổi (%s) phải sau giờ vào làm (%s).', $gioTanNua, $gioVao),
            );
        }

        $caNgay = $doc(SettingKey::WorkDaysFull);
        $nuaBuoi = $doc(SettingKey::WorkDaysHalf);

        $trung = array_intersect($caNgay, $nuaBuoi);

        if ($trung !== []) {
            $v->errors()->add(
                'values.'.SettingKey::WorkDaysHalf->value,
                sprintf(
                    'Một ngày không thể vừa làm cả ngày vừa làm nửa buổi. Trùng: %s.',
                    implode(', ', array_map($this->tenThu(...), $trung)),
                ),
            );

            return;
        }

        if ($caNgay === [] && $nuaBuoi === []) {
            $v->errors()->add(
                'values.'.SettingKey::WorkDaysFull->value,
                'Phải có ít nhất một ngày làm việc trong tuần.',
            );
        }
    }

    private function tenThu(string $thu): string
    {
        return match ($thu) {
            '0' => 'Chủ nhật',
            '1' => 'Thứ hai',
            '2' => 'Thứ ba',
            '3' => 'Thứ tư',
            '4' => 'Thứ năm',
            '5' => 'Thứ sáu',
            default => 'Thứ bảy',
        };
    }

    /** Khoá không có trong danh mục thì báo ngay, đừng ghi vào database. */
    private function kiemKhoaLa(Validator $v): void
    {
        /** @var array<string, mixed> $gui */
        $gui = $this->input('values', []);

        foreach (array_keys($gui) as $khoa) {
            $k = SettingKey::tryFrom((string) $khoa);

            if ($k === null) {
                $v->errors()->add("values.{$khoa}", 'Không có cài đặt nào tên này.');

                continue;
            }

            if (self::laTepAnh($k)) {
                $v->errors()->add(
                    "values.{$khoa}",
                    'Ảnh nhận diện phải tải lên qua ô chọn tệp, không đặt được ở đây.',
                );
            }
        }
    }
}
