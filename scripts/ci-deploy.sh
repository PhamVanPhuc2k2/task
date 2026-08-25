#!/bin/bash
#
# Lệnh ép buộc (forced command) cho khoá SSH mà GitHub Actions dùng.
#
# ── Vì sao không cho CI một khoá SSH bình thường ─────────────────────────────
#
# Một khoá root bình thường nằm trong GitHub Secrets nghĩa là: ai vào được tài
# khoản GitHub, hoặc ai đẩy được một workflow độc hại vào repo, đều có shell
# root trên máy chủ — mà máy chủ đó còn chạy vài dự án khác.
#
# Với `command="..."` trong authorized_keys, khoá đó không mở được shell nữa.
# Gửi lệnh gì cũng không quan trọng: sshd luôn chạy đúng script này, còn lệnh
# người gọi gõ chỉ nằm trong $SSH_ORIGINAL_COMMAND cho script tự đọc và tự
# quyết. Kèm no-pty và tắt mọi kiểu chuyển tiếp thì bề mặt còn lại đúng bằng
# những gì viết trong file này.
#
# Dòng cần đặt trong ~/.ssh/authorized_keys (một dòng duy nhất):
#
#   command="/srv/explus/scripts/ci-deploy.sh",no-agent-forwarding,\
#   no-port-forwarding,no-pty,no-user-rc,no-X11-forwarding ssh-ed25519 AAAA... ci@explus
#
# ── Nó nhận gì ───────────────────────────────────────────────────────────────
#
#   $SSH_ORIGINAL_COMMAND = "deploy <sha 40 ký tự>"
#   stdin                 = token đọc GHCR, dùng một lần rồi đăng xuất
#
# Token đi qua stdin chứ không nằm trong dòng lệnh: dòng lệnh hiện trong
# `ps` cho mọi user trên máy đọc được, còn stdin thì không.
#
# ── Vì sao tự sao chép rồi exec ──────────────────────────────────────────────
#
# Script này chạy `git reset --hard`, mà chính nó nằm trong repo. Bash đọc file
# script theo từng đoạn TRONG LÚC chạy, nên sửa file đang chạy làm bash đọc tiếp
# từ một vị trí byte không còn ý nghĩa gì — có thể chạy nhầm một nửa câu lệnh.
# Chép sang /tmp rồi exec bản sao là cắt đứt hẳn chuyện đó.
set -euo pipefail

if [ "${EXPLUS_BAN_SAO:-}" != "1" ]; then
    BAN_SAO=$(mktemp /tmp/ci-deploy.XXXXXX)
    cat "$0" > "$BAN_SAO"
    chmod +x "$BAN_SAO"
    export EXPLUS_BAN_SAO=1
    exec "$BAN_SAO" "$@"
fi

# Từ đây trở đi là bản sao trong /tmp — xoá mình đi khi xong, dù xong kiểu gì.
trap 'rm -f "$0"' EXIT

THU_MUC="${EXPLUS_DIR:-/srv/explus}"
REGISTRY="${REGISTRY:-ghcr.io/phamvanphuc2k2/task}"

LENH="${SSH_ORIGINAL_COMMAND:-}"

# Chỉ nhận đúng một dạng lệnh, và kiểm bằng danh sách cho phép chứ không phải
# danh sách cấm. Cắt chuỗi rồi ghép vào lệnh khác là cách mà một forced command
# viết ẩu vẫn trở thành shell đầy đủ.
DUOI="${LENH#deploy }"

if [ "$LENH" = "$DUOI" ] || ! printf '%s' "$DUOI" | grep -qE '^[0-9a-f]{40}$'; then
    echo "✘ Khoá này chỉ chạy được: deploy <sha 40 ký tự hệ 16>" >&2
    echo "  Nhận được: ${LENH}" >&2
    exit 2
fi

SHA="$DUOI"

cd "$THU_MUC"

echo "════════════════════════════════════════════"
echo "  CI triển khai ${SHA}"
echo "════════════════════════════════════════════"

# Đăng nhập GHCR bằng token đọc từ stdin.
#
# Token này là GITHUB_TOKEN của chính lần chạy workflow đó: nó hết hạn khi job
# kết thúc. Không có gì lâu dài phải cất trên máy chủ, và cũng không có gì để
# lộ nếu ai đó đọc được đĩa sau này.
if [ -t 0 ]; then
    echo "✘ Không có token trên stdin." >&2
    exit 2
fi

read -r TOKEN || true

if [ -z "${TOKEN:-}" ]; then
    echo "✘ Token rỗng." >&2
    exit 2
fi

# `docker logout` chạy trong MỌI đường thoát, kể cả khi deploy hỏng giữa chừng.
# Thiếu nó thì thông tin đăng nhập nằm lại trong /root/.docker/config.json cho
# tới lần deploy sau — một token hết hạn thì vô hại, nhưng để lại thói quen đó
# là để lại một chỗ rò cho lần sau.
trap 'rm -f "$0"; docker logout ghcr.io >/dev/null 2>&1 || true' EXIT

printf '%s' "$TOKEN" | docker login ghcr.io -u ci --password-stdin
unset TOKEN

echo "→ Lấy mã nguồn ${SHA}..."
git fetch --quiet origin

# `reset --hard` chứ không `pull`: máy chủ không phải chỗ để sửa code, và một
# thay đổi tay còn sót lại ở đây sẽ làm mọi lần deploy sau lệch khỏi thứ CI đã
# kiểm. Chỉ đụng tới file ĐƯỢC GIT THEO DÕI — .env và .last-known-good không
# nằm trong đó nên không bị chạm.
git reset --hard --quiet "$SHA"

echo "   $(git log --oneline -1)"

REGISTRY="$REGISTRY" ./scripts/deploy.sh --pull "$SHA"
