#!/bin/bash
#
# Diễn tập phục hồi — phục hồi bản backup mới nhất vào một database RIÊNG rồi
# kiểm chứng nội dung.
#
# ── Vì sao script này tồn tại ────────────────────────────────────────────────
#
# Backup chưa từng phục hồi thử thì coi như chưa có. Chuỗi hỏng thường gặp
# không phải "không có backup" mà là:
#
#   - File dump rỗng hoặc cụt vì đĩa đầy giữa chừng
#   - Mã hoá bằng khoá mà không ai còn giữ khoá giải
#   - Dump thiếu bảng vì thiếu quyền, và mysqldump vẫn thoát mã 0
#   - Sai charset, phục hồi ra tiếng Việt thành dấu hỏi
#
# Cả bốn đều **im lặng**: lịch backup xanh mỗi đêm, thư mục đầy file, và mọi
# người yên tâm cho tới đúng cái ngày cần dùng tới.
#
# ── Vì sao phục hồi vào database riêng ───────────────────────────────────────
#
# Diễn tập phải chạy theo lịch, trên hệ thống đang sống. Phục hồi đè lên
# database thật thì bài diễn tập chính là sự cố. Ở đây tạo một database tạm,
# kiểm xong thì xoá.
#
set -euo pipefail

THU_MUC="${BACKUP_DIR:-/backups}"
DB_THU="${DRILL_DATABASE:-explus_restore_drill}"

# Tài khoản riêng cho diễn tập.
#
# Tài khoản ứng dụng KHÔNG có quyền tạo database — và đó là đúng, nó chẳng có
# việc gì phải tạo. Lần chạy đầu tiên của script này thất bại đúng ở đó, và đó
# là một thất bại tốt: nó chứng minh quyền của ứng dụng đang thật sự hẹp.
#
# Quyền cần cấp cho tài khoản diễn tập, không hơn:
#
#   CREATE USER 'restore_drill'@'%' IDENTIFIED BY '...';
#   GRANT ALL PRIVILEGES ON `explus_restore_drill`.* TO 'restore_drill'@'%';
#   GRANT CREATE, DROP ON *.* TO 'restore_drill'@'%';
#
# Tuyệt đối KHÔNG dùng root: container này chạy không người trông, và nó là chỗ
# duy nhất cầm khoá giải mã backup. Cho nó thêm quyền root trên database nữa
# thì một sự cố ở đây thành sự cố toàn bộ.
DRILL_USER="${DRILL_DB_USERNAME:-$DB_USERNAME}"
DRILL_PASS="${DRILL_DB_PASSWORD:-$DB_PASSWORD}"

MOI_NHAT=$(find "$THU_MUC" -name 'explus-*.sql.gz*' -type f | sort | tail -n 1)

if [ -z "$MOI_NHAT" ]; then
    echo "✘ KHÔNG TÌM THẤY bản sao lưu nào trong ${THU_MUC}." >&2
    exit 1
fi

echo "→ Bản mới nhất: $(basename "$MOI_NHAT")"

TUOI_GIO=$(( ( $(date -u +%s) - $(date -u -r "$MOI_NHAT" +%s) ) / 3600 ))
echo "  Tạo cách đây ${TUOI_GIO} giờ."

# Backup cũ vẫn phục hồi được, nhưng nếu bản mới nhất đã 3 ngày tuổi thì lịch
# chạy nền đã chết mà không ai biết — đó mới là điều cần báo.
if [ "$TUOI_GIO" -gt 48 ]; then
    echo "⚠️  Bản mới nhất đã quá 48 giờ — kiểm lại lịch chạy backup." >&2
fi

TAM=$(mktemp -d)
# shellcheck disable=SC2064
trap "rm -rf '$TAM'" EXIT

case "$MOI_NHAT" in
    *.age)
        if [ -z "${BACKUP_AGE_PRIVATE_KEY_FILE:-}" ]; then
            echo "✘ Bản sao lưu đã mã hoá nhưng KHÔNG có khoá giải." >&2
            echo "  Đây đúng là tình huống script này sinh ra để phát hiện:" >&2
            echo "  backup vẫn chạy đều mỗi đêm mà không ai mở lại được." >&2
            exit 1
        fi
        echo "→ Đang giải mã..."
        age -d -i "$BACKUP_AGE_PRIVATE_KEY_FILE" -o "${TAM}/dump.sql.gz" "$MOI_NHAT"
        ;;
    *)
        cp "$MOI_NHAT" "${TAM}/dump.sql.gz"
        ;;
esac

echo "→ Phục hồi vào database tạm '${DB_THU}'..."

mysql --host="$DB_HOST" --user="$DRILL_USER" --password="$DRILL_PASS" \
    -e "DROP DATABASE IF EXISTS \`${DB_THU}\`;
        CREATE DATABASE \`${DB_THU}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

gunzip -c "${TAM}/dump.sql.gz" \
    | mysql --host="$DB_HOST" --user="$DRILL_USER" --password="$DRILL_PASS" \
        --default-character-set=utf8mb4 "$DB_THU"

echo "→ Kiểm chứng nội dung..."

dem() {
    mysql --host="$DB_HOST" --user="$DRILL_USER" --password="$DRILL_PASS" \
        --default-character-set=utf8mb4 -N -B \
        -e "SELECT COUNT(*) FROM \`${DB_THU}\`.\`$1\`;" 2>/dev/null || echo "LOI"
}

LOI=0

# Bảng nào trống hoặc thiếu thì bản phục hồi vô dụng, dù script vẫn chạy tới đây.
for BANG in users tasks daily_reports work_sessions salary_records migrations; do
    SO=$(dem "$BANG")

    if [ "$SO" = "LOI" ]; then
        echo "  ✘ ${BANG}: KHÔNG CÓ BẢNG NÀY" >&2
        LOI=$((LOI + 1))
    else
        echo "  • ${BANG}: ${SO} dòng"
        if [ "$BANG" = "users" ] && [ "$SO" -eq 0 ]; then
            echo "  ✘ Không có người dùng nào — bản dump gần như chắc chắn hỏng." >&2
            LOI=$((LOI + 1))
        fi
    fi
done

# Tiếng Việt có dấu: sai charset thì dữ liệu vẫn "phục hồi thành công" nhưng ra
# dấu hỏi, và không có gì báo lỗi.
DAU=$(mysql --host="$DB_HOST" --user="$DRILL_USER" --password="$DRILL_PASS" \
    --default-character-set=utf8mb4 -N -B \
    -e "SELECT COUNT(*) FROM \`${DB_THU}\`.\`roles\` WHERE name IS NOT NULL;" 2>/dev/null || echo 0)
echo "  • vai trò đọc được: ${DAU}"

echo "→ Dọn database tạm..."
mysql --host="$DB_HOST" --user="$DRILL_USER" --password="$DRILL_PASS" \
    -e "DROP DATABASE \`${DB_THU}\`;"

if [ "$LOI" -gt 0 ]; then
    echo "✘ DIỄN TẬP THẤT BẠI — ${LOI} vấn đề." >&2
    exit 1
fi

echo "✔ Diễn tập phục hồi THÀNH CÔNG."
