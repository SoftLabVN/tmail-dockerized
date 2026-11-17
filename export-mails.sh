#!/usr/bin/env bash
# ==============================================================================
# Script xuất tất cả lịch sử email nhận từ TMail Dockerized ra file
# CÔNG TY TNHH GIẢI PHÁP SỐ SOFTLAB
# ==============================================================================

set -e

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
cd "$DIR"

# Kiểm tra docker compose đang chạy
if ! docker compose ps >/dev/null 2>&1; then
    echo "[!] Lỗi: Docker compose chưa khởi chạy hoặc không tìm thấy container!"
    exit 1
fi

# Chạy python export script
python3 "$DIR/scripts/export_mails.py" "$@"
