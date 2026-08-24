#!/bin/bash
#
# Sao lưu MySQL, mã hoá, và dọn bản cũ.
#
# ── Vì sao mã hoá ─────────────────────────────────────────────────────────────
#
# Bản backup là bản sao ĐẦY ĐỦ của mọi thứ: lương từng người, số điện thoại,
# nội dung bình luận, nhật ký nhân sự. Nó nằm ngoài mọi lớp phân quyền của ứng
# dụng, và thường được chép sang chỗ khác (ổ ngoài, cloud) — tức là chỗ dễ lộ
# nhất lại đang giữ dữ liệu nhạy cảm nhất.
#
# Đây cũng chính là lớp bảo vệ đã chốt cho cột lương thay cho `encrypted` cast.
# Xem README, "Vì sao cột lương không mã hoá ở tầng ứng dụng".
#
# ── Vì sao dùng age chứ không GPG ────────────────────────────────────────────
#
# `age` chỉ có một cách dùng đúng và không có cờ nào để tự bắn vào chân mình.
# GPG mạnh hơn nhưng bề mặt cấu hình rộng tới mức người vận hành rất dễ tạo ra
# một bản mã hoá bằng khoá đã hết hạn mà không nhận ra.
#
# ── Điều quan trọng nhất ─────────────────────────────────────────────────────
#
# Backup chưa từng phục hồi thử thì coi như CHƯA CÓ. Xem restore-drill.sh —
# script đó phục hồi bản mới nhất vào một database riêng rồi đếm số dòng, và nó
# phải được chạy theo lịch chứ không phải chạy khi có sự cố.
#
# `pipefail` là dòng quan trọng nhất của file này.
#
# `mysqldump ... | gzip > file` trả về mã thoát của **gzip**, không phải của
# mysqldump. Nghĩa là dump thất bại giữa chừng — mất kết nối, hết quyền, hết
# đĩa — mà gzip vẫn nén xong phần nhận được và thoát 0. Script báo thành công,
# file có kích thước hợp lý, và không ai biết cho tới lúc cần phục hồi.
#
# Đây là lý do dùng bash chứ không phải sh: `set -o pipefail` không có trong
# POSIX sh.
set -euo pipefail

THU_MUC="${BACKUP_DIR:-/backups}"
GIU_NGAY="${BACKUP_RETENTION_DAYS:-30}"
MOC="$(date -u +%Y%m%dT%H%M%SZ)"
TEN="explus-${MOC}.sql.gz"

if [ -z "${DB_HOST:-}" ] || [ -z "${DB_DATABASE:-}" ]; then
    echo "THIẾU cấu hình database (DB_HOST, DB_DATABASE)." >&2
    exit 1
fi

mkdir -p "$THU_MUC"

echo "→ Đang dump ${DB_DATABASE} từ ${DB_HOST}..."

# --single-transaction: chụp một ảnh nhất quán mà KHÔNG khoá bảng. Thiếu cờ này
#   thì backup lúc 3 giờ sáng vẫn khoá toàn bộ bảng và mọi request đang chạy
#   phải xếp hàng chờ.
# --routines --triggers --events: thiếu thì phục hồi ra một database trông có vẻ
#   đầy đủ nhưng mất hết logic phía server — và không có gì báo.
# --no-tablespaces: mysqldump 8 mặc định cố đọc thông tin tablespace, việc đó
#   đòi quyền PROCESS trên toàn server — quyền mà tài khoản ứng dụng không nên
#   có. Không có cờ này thì mỗi lần chạy in một dòng "Access denied" giữa output
#   thành công, và người vận hành quen dần với việc bỏ qua nó.
mysqldump \
    --host="$DB_HOST" \
    --user="$DB_USERNAME" \
    --password="$DB_PASSWORD" \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    --no-tablespaces \
    --default-character-set=utf8mb4 \
    "$DB_DATABASE" \
    | gzip -9 > "${THU_MUC}/${TEN}"

KICH_THUOC=$(wc -c < "${THU_MUC}/${TEN}")

# Một file dump rỗng vẫn là một file. Không kiểm kích thước thì lịch backup chạy
# xanh mỗi đêm trong khi thư mục đầy những file 20 byte.
if [ "$KICH_THUOC" -lt 1024 ]; then
    echo "BẢN DUMP QUÁ NHỎ (${KICH_THUOC} byte) — gần như chắc chắn đã hỏng." >&2
    rm -f "${THU_MUC}/${TEN}"
    exit 1
fi

if [ -n "${BACKUP_AGE_PUBLIC_KEY:-}" ]; then
    echo "→ Đang mã hoá..."
    age -r "$BACKUP_AGE_PUBLIC_KEY" -o "${THU_MUC}/${TEN}.age" "${THU_MUC}/${TEN}"
    rm -f "${THU_MUC}/${TEN}"
    TEN="${TEN}.age"
else
    # Cảnh báo to, không im lặng bỏ qua: một bản backup không mã hoá trông
    # giống hệt một bản đã mã hoá cho tới lúc nó rơi vào tay người khác.
    echo "⚠️  BACKUP_AGE_PUBLIC_KEY trống — bản sao lưu KHÔNG được mã hoá." >&2
fi

echo "→ Dọn bản cũ hơn ${GIU_NGAY} ngày..."
find "$THU_MUC" -name 'explus-*.sql.gz*' -type f -mtime "+${GIU_NGAY}" -delete

echo "✔ Xong: ${TEN} ($(du -h "${THU_MUC}/${TEN}" | cut -f1))"
echo "  Còn giữ $(find "$THU_MUC" -name 'explus-*.sql.gz*' -type f | wc -l) bản."
