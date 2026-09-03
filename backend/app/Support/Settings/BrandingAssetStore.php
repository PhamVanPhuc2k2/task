<?php

declare(strict_types=1);

namespace App\Support\Settings;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Lưu, xoá và đọc các tệp ảnh nhận diện — logo và biểu tượng.
 *
 * ## Vì sao tách khỏi controller
 *
 * Logo và biểu tượng có cùng vòng đời tệp: ghi tệp mới, trỏ cài đặt sang tệp
 * mới, rồi mới xoá tệp cũ. **Thứ tự đó là chỗ dễ hỏng nhất** và nó không hiển
 * nhiên — đảo lại thì một lần ghi thất bại sẽ để cài đặt trỏ tới tệp vừa bị
 * xoá, và trang đăng nhập hiện ảnh vỡ cho tới khi có người tải lên lại.
 *
 * Chép logic đó sang controller thứ hai là chép luôn một cái bẫy. Ở đây nó
 * được viết đúng một lần.
 *
 * ## Vì sao ở ổ CÔNG KHAI, không phải R2
 *
 * Cả hai đều hiện **trước khi có ai đăng nhập** — logo trên trang đăng nhập,
 * biểu tượng trên tab trình duyệt. R2 của dự án là bucket riêng tư, mọi đường
 * dẫn đều được ký hạn 30 phút; không có phiên nào để xin chữ ký đó.
 *
 * Đây cũng là loại dữ liệu duy nhất trong hệ thống **đáng** để công khai: nó là
 * nhận diện thương hiệu, in trên danh thiếp.
 */
final class BrandingAssetStore
{
    /** Một thư mục cho cả hai — không tích tệp cũ lại. */
    private const THU_MUC = 'branding';

    public function __construct(private readonly SiteSettings $settings) {}

    /**
     * Ghi tệp mới và trả về đường dẫn công khai của nó.
     *
     * @throws RuntimeException khi ổ đĩa từ chối ghi.
     */
    public function luu(UploadedFile $tep, SettingKey $khoa, ?int $nguoiSua): string
    {
        $cu = $this->settings->get($khoa);

        $duong = $tep->store(self::THU_MUC, 'public');

        if (! is_string($duong)) {
            throw new RuntimeException("Không ghi được tệp nhận diện cho khoá {$khoa->value}.");
        }

        $this->settings->set($khoa, $duong, $nguoiSua);

        // Xoá tệp cũ SAU khi đã ghi đường dẫn mới. Đảo thứ tự thì lưu thất bại
        // sẽ để lại một cài đặt trỏ tới tệp vừa bị xoá.
        $this->xoaTep($cu, $duong);

        return Storage::disk('public')->url($duong);
    }

    /** Quay về ảnh mặc định vẽ tay: xoá cài đặt trước, xoá tệp sau. */
    public function xoa(SettingKey $khoa): void
    {
        $cu = $this->settings->get($khoa);

        $this->settings->forget($khoa);

        $this->xoaTep($cu, null);
    }

    /**
     * Đường dẫn công khai của tệp đã đặt, hoặc `null` khi chưa ai đặt.
     *
     * Trả `null` chứ không trả chuỗi rỗng: chuỗi rỗng làm `<img src="">` và
     * trình duyệt tải lại chính trang hiện tại.
     */
    public function url(SettingKey $khoa): ?string
    {
        $duong = $this->settings->get($khoa);

        return is_string($duong) && $duong !== ''
            ? Storage::disk('public')->url($duong)
            : null;
    }

    private function xoaTep(string|int|bool|null $cu, ?string $moi): void
    {
        if (is_string($cu) && $cu !== '' && $cu !== $moi) {
            Storage::disk('public')->delete($cu);
        }
    }
}
