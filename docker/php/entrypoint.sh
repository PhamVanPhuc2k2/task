#!/bin/sh
set -e

# Bind mount trên Windows và macOS gắn thư mục vào container dưới quyền
# root:root 755, trong khi php-fpm chạy dưới user www-data. Không nới quyền thì
# Blade không ghi được view đã biên dịch, và PHP 8.4 biến chuyện đó thành lỗi
# khó đoán: tempnam() rơi về /tmp rồi phát E_WARNING, Laravel ở chế độ debug
# nâng thành exception, và MỌI request đều trả 500 — kể cả những trang không
# liên quan gì tới ghi file.
#
# Chỉ nới đúng hai thư mục Laravel cần ghi. Không đụng tới mã nguồn.
for dir in storage bootstrap/cache; do
    if [ -d "$dir" ]; then
        chmod -R a+rwX "$dir" 2>/dev/null || true
    fi
done

# ── Chế độ OPcache ───────────────────────────────────
#
# Mặc định OPcache kiểm dấu thời gian của MỌI file đã nạp ở mỗi request để biết
# có file nào vừa sửa. Với ~670 file mỗi request Laravel, chi phí đó phụ thuộc
# hoàn toàn vào việc mã nguồn nằm ở đâu. Đo trên máy dev Windows:
#
#     stat 670 file, mã nguồn trên bind mount Windows :  ~900 ms
#     stat 670 file, mã nguồn trên ổ đĩa Linux        :     1 ms
#
# Gần 900 lần. Đó là toàn bộ lý do mỗi lần F5 phải chờ 1,5–3 giây.
#
# OPCACHE_VALIDATE_TIMESTAMPS=0 tắt hẳn việc kiểm đó: request xuống còn ~100ms.
# Đổi lại, sửa mã PHP xong phải nạp lại php-fpm thì thay đổi mới có hiệu lực:
#
#     docker compose exec app kill -USR2 1
#
# Xem README, "Vì sao backend chậm khi dev trên Windows", để biết cách xử lý
# triệt để (chuyển mã nguồn vào hệ tệp WSL2) mà không phải đánh đổi gì.
: "${OPCACHE_VALIDATE_TIMESTAMPS:=1}"

cat > /usr/local/etc/php/conf.d/98-opcache-mode.ini <<INI
opcache.validate_timestamps = ${OPCACHE_VALIDATE_TIMESTAMPS}
INI

if [ "$OPCACHE_VALIDATE_TIMESTAMPS" = "0" ]; then
    echo "[entrypoint] OPcache: KHONG kiem dau thoi gian — nhanh, nhung sua ma PHP xong phai chay: docker compose exec app kill -USR2 1"
fi

# Nối tiếp entrypoint gốc của image php:fpm thay vì thay thế nó.
exec docker-php-entrypoint "$@"
