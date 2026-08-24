#!/bin/bash
#
# Khởi động container production.
#
# Ba việc, theo đúng thứ tự này, và thứ tự có lý do.
set -euo pipefail

cd /var/www/html

echo "→ Dọn cache cũ..."
# Image có thể mang theo cache của lần build trước. Cache cấu hình cũ mà chứa
# giá trị biến môi trường của máy build là loại lỗi tệ nhất: ứng dụng chạy bình
# thường nhưng nói chuyện với database sai.
php artisan config:clear --no-interaction || true

echo "→ Nạp cache cấu hình theo môi trường HIỆN TẠI..."
# Làm ở đây chứ không ở Dockerfile: một image build ra chạy được ở staging lẫn
# production với hai bộ biến môi trường khác nhau.
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan event:cache --no-interaction

# KHÔNG cache view: dự án là API thuần, view chỉ có mấy mẫu email.

echo "→ Nối public/storage vào volume..."
# Bắt buộc, và phải làm Ở ĐÂY chứ không ở Dockerfile.
#
# nginx phục vụ tệp công khai (logo công ty, ảnh đính kèm ở ổ `public`) từ
# đường dẫn `public/storage`, mà đó chỉ là một symlink trỏ vào
# `storage/app/public`. Thư mục `storage` nằm trên volume và chỉ tồn tại lúc
# CHẠY — làm ở Dockerfile thì symlink trỏ vào chỗ trống.
#
# Không có bước này thì deploy vẫn "thành công", ứng dụng vẫn chạy, tải ảnh lên
# vẫn được — chỉ là mọi đường dẫn ảnh trả về **404**. Hỏng im lặng, và chỉ lộ ra
# khi có người nhìn vào chỗ đáng lẽ là logo.
mkdir -p storage/app/public
php artisan storage:link --force --no-interaction

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "→ Chạy migration..."
    # `--isolated` dùng khoá trên cache để nhiều container khởi động cùng lúc
    # vẫn chỉ có MỘT chạy migration. Không có cờ này thì lúc mở rộng số bản sao,
    # hai container cùng chạy `ALTER TABLE` trên một bảng.
    php artisan migrate --force --isolated --no-interaction
fi

echo "✔ Sẵn sàng."

exec "$@"
