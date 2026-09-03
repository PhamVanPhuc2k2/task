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
            $this->kiemKhoaLa($v);
        });
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
