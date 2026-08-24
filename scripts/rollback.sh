#!/bin/bash
#
# Quay lui về phiên bản chạy được gần nhất.
#
# ── Điều script này KHÔNG làm, và vì sao ─────────────────────────────────────
#
# **Nó không quay lui database.**
#
# Nghe có vẻ thiếu, nhưng tự động phục hồi database khi deploy hỏng là cách gây
# ra một sự cố lớn hơn sự cố đang có: mọi thứ người dùng làm kể từ lúc deploy —
# task mới, báo cáo vừa nộp, giờ chấm công — biến mất, và không ai lấy lại
# được.
#
# Quy ước của dự án khiến điều đó gần như không cần: migration phá vỡ tương
# thích phải tách làm hai lần deploy (xem deploy.sh). Nghĩa là **image cũ luôn
# chạy được với schema mới**, và quay lui chỉ cần đổi image.
#
# Trường hợp thật sự phải phục hồi database thì đó là quyết định của con người,
# có cân nhắc mất mát, không phải một bước tự động trong script. Khi đó dùng:
#
#   docker compose -f docker-compose.prod.yml run --rm backup drill   # kiểm trước
#   # rồi phục hồi thủ công vào database thật, sau khi đã dừng ứng dụng
#
set -euo pipefail

cd "$(dirname "$0")/.."

COMPOSE="docker compose -f docker-compose.prod.yml"

if [ ! -f .last-known-good ]; then
    echo "✘ Không có .last-known-good — chưa từng deploy bằng scripts/deploy.sh." >&2
    echo "  Chỉ định tay:  APP_IMAGE=explus/app:<tag> $COMPOSE up -d app worker" >&2
    exit 1
fi

IMAGE=$(cat .last-known-good)

echo "→ Quay lui về ${IMAGE}"

if ! docker image inspect "$IMAGE" >/dev/null 2>&1; then
    echo "✘ Image ${IMAGE} không còn trên máy này." >&2
    echo "  Đây là lý do KHÔNG nên dọn image cũ ngay sau mỗi lần deploy." >&2
    exit 1
fi

APP_IMAGE="$IMAGE" $COMPOSE up -d --no-deps app nginx

echo "→ Chờ health check..."
for i in $(seq 1 30); do
    if curl -fsS "http://localhost:${APP_PORT:-8080}/api/v1/health" >/dev/null 2>&1; then
        echo "   ✔ Đã quay lui và web đang phục vụ."
        break
    fi
    if [ "$i" -eq 30 ]; then
        echo "   ✘ Quay lui rồi mà health check VẪN đỏ — vấn đề không nằm ở image." >&2
        echo "     Kiểm database, Redis, và biến môi trường." >&2
        exit 1
    fi
    sleep 2
done

APP_IMAGE="$IMAGE" $COMPOSE up -d --no-deps worker

echo ""
echo "✔ Đã quay lui về ${IMAGE}."
echo "  Database KHÔNG bị đụng tới — xem chú thích đầu file."
