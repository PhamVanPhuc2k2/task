#!/bin/sh
#
# Chạy backup theo lịch, và diễn tập phục hồi theo lịch riêng.
#
# Diễn tập là một dòng cron RIÊNG chứ không phải một bước cuối của backup. Lý
# do: nếu gộp, một bài diễn tập hỏng sẽ làm cả job backup báo lỗi, và phản xạ
# tự nhiên của người trực là tắt bớt phần gây ồn — thường là tắt đúng phần diễn
# tập. Hai lịch tách nhau thì hỏng cái nào biết cái đó.
#
set -eu

: "${BACKUP_CRON:=15 2 * * *}"        # 02:15 mỗi ngày
: "${DRILL_CRON:=45 3 * * 0}"          # 03:45 Chủ nhật

if [ "${1:-}" = "once" ]; then
    exec /usr/local/bin/backup
fi

if [ "${1:-}" = "drill" ]; then
    exec /usr/local/bin/restore-drill
fi

# Cron của Alpine không thừa hưởng biến môi trường của tiến trình cha, nên phải
# ghi chúng ra file rồi nạp lại trong từng dòng lịch. Thiếu bước này thì job
# chạy đúng giờ và thất bại lặng lẽ vì không biết DB_HOST là gì.
env | grep -E '^(DB_|BACKUP_|DRILL_|TZ)' | sed 's/^/export /' > /etc/backup.env

cat > /etc/cron.d/explus-backup <<EOF
${BACKUP_CRON} root . /etc/backup.env && /usr/local/bin/backup >> /var/log/backup.log 2>&1
${DRILL_CRON} root . /etc/backup.env && /usr/local/bin/restore-drill >> /var/log/backup.log 2>&1
EOF
chmod 0644 /etc/cron.d/explus-backup

touch /var/log/backup.log

echo "Lịch sao lưu   : ${BACKUP_CRON}"
echo "Lịch diễn tập  : ${DRILL_CRON}"
echo "Nơi lưu        : ${BACKUP_DIR:-/backups}, giữ ${BACKUP_RETENTION_DAYS:-30} ngày"

if [ -z "${BACKUP_AGE_PUBLIC_KEY:-}" ]; then
    echo "⚠️  CHƯA cấu hình khoá mã hoá — bản sao lưu sẽ để trần."
fi

crond -n &
exec tail -f /var/log/backup.log
