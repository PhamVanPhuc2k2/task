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

APP_IMAGE=""
FRONTEND_IMAGE=""

# Định dạng hiện tại là KEY=giá trị, mỗi dòng một image.
#
# Định dạng CŨ chỉ có đúng một dòng là tên image của app. Vẫn phải đọc được,
# vì file này nằm trên máy chủ chứ không nằm trong repo — lần deploy đầu tiên
# sau khi đổi định dạng sẽ đọc phải file cũ.
# `|| [ -n "$DONG" ]`: không có nó thì file thiếu ký tự xuống dòng ở cuối sẽ
# **mất dòng cuối** — `read` trả về khác 0 ở EOF và thân vòng lặp không chạy.
while IFS= read -r DONG || [ -n "$DONG" ]; do
    case "$DONG" in
        APP_IMAGE=*)      APP_IMAGE="${DONG#APP_IMAGE=}" ;;
        FRONTEND_IMAGE=*) FRONTEND_IMAGE="${DONG#FRONTEND_IMAGE=}" ;;
        ?*)               [ -z "$APP_IMAGE" ] && APP_IMAGE="$DONG" ;;
    esac
done < .last-known-good

if [ -z "$APP_IMAGE" ]; then
    echo "✘ .last-known-good rỗng hoặc không đọc được." >&2
    exit 1
fi

echo "→ Quay lui về:"
echo "   app      ${APP_IMAGE}"
if [ -n "$FRONTEND_IMAGE" ]; then
    echo "   frontend ${FRONTEND_IMAGE}"
else
    # Nói to. Quay lui một nửa còn khó gỡ hơn không quay lui: backend bản cũ
    # nói chuyện với giao diện bản mới, và cả hai đều "chạy bình thường".
    echo "   frontend ⚠️  KHÔNG BIẾT — file ghi theo định dạng cũ." >&2
    echo "            Giao diện sẽ GIỮ NGUYÊN bản mới. Nếu lỗi nằm ở giao diện" >&2
    echo "            thì quay lui thế này không sửa được gì." >&2
fi

for ANH in "$APP_IMAGE" ${FRONTEND_IMAGE:+"$FRONTEND_IMAGE"}; do
    if ! docker image inspect "$ANH" >/dev/null 2>&1; then
        echo "✘ Image ${ANH} không còn trên máy này." >&2
        echo "  deploy.sh giữ 3 bản gần nhất và không bao giờ xoá bản ghi trong" >&2
        echo "  .last-known-good — nếu vẫn mất thì đã có ai dọn tay." >&2
        echo "  Kéo lại:  docker pull ${ANH}" >&2
        exit 1
    fi
done

if [ -n "$FRONTEND_IMAGE" ]; then
    APP_IMAGE="$APP_IMAGE" FRONTEND_IMAGE="$FRONTEND_IMAGE" \
        $COMPOSE up -d --no-deps app frontend nginx
else
    APP_IMAGE="$APP_IMAGE" $COMPOSE up -d --no-deps app nginx
fi

echo "→ Chờ health check..."
for i in $(seq 1 30); do
    # Kiểm CẢ HAI, giống deploy.sh.
    #
    # Bản trước chỉ kiểm API. Nghĩa là quay lui xong mà container frontend chết
    # thì script vẫn in dấu tích, trong khi người dùng mở trình duyệt ra thấy
    # 502 — đúng loại hỏng im lặng mà cả hai script này sinh ra để chặn.
    if curl -fsS "http://localhost:${APP_PORT:-8080}/api/v1/health" >/dev/null 2>&1 \
        && curl -fsS -o /dev/null "http://localhost:${APP_PORT:-8080}/login"; then
        echo "   ✔ API và giao diện đều đã phục vụ trở lại."
        break
    fi
    if [ "$i" -eq 30 ]; then
        echo "   ✘ Quay lui rồi mà health check VẪN đỏ — vấn đề không nằm ở image." >&2
        echo "     Kiểm database, Redis, và biến môi trường." >&2
        exit 1
    fi
    sleep 2
done

APP_IMAGE="$APP_IMAGE" $COMPOSE up -d --no-deps worker

echo ""
echo "✔ Đã quay lui về ${APP_IMAGE}."
echo "  Database KHÔNG bị đụng tới — xem chú thích đầu file."
