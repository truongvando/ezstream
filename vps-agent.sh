#!/bin/bash

# ==============================================================================
# VPS-AGENT.SH - Script thu thập và báo cáo tài nguyên cho EzStream
# ==============================================================================
#
# CÁCH SỬ DỤNG:
# 1. Sao chép file này lên VPS.
# 2. Cấp quyền thực thi: chmod +x vps-agent.sh
# 3. Chạy script với các tham số cần thiết:
#    ./vps-agent.sh <VPS_ID> <REDIS_HOST> <REDIS_PORT> [REDIS_PASSWORD]
#
# VÍ DỤ:
#    ./vps-agent.sh 1 127.0.0.1 6379
#    ./vps-agent.sh 2 192.168.1.100 6379 mysecretpassword
#
# YÊU CẦU:
# - redis-cli: (Cài đặt bằng: sudo apt update && sudo apt install redis-tools -y)
#
# MÔ TẢ:
# Script này sẽ định kỳ (mặc định 15 giây) thu thập các thông số hệ thống
# (CPU, RAM, số lượng stream) và gửi chúng đến Redis Pub/Sub channel 'vps-stats'
# để ứng dụng Laravel có thể nhận và xử lý.
#
# ==============================================================================

# --- CẤU HÌNH ---
VPS_ID="$1"
REDIS_HOST="$2"
REDIS_PORT="$3"
REDIS_PASSWORD="$4"

CHANNEL="vps-stats"
INTERVAL=15 # Giây

# --- KIỂM TRA ĐẦU VÀO ---
if [ -z "$VPS_ID" ] || [ -z "$REDIS_HOST" ] || [ -z "$REDIS_PORT" ]; then
  echo "Lỗi: Vui lòng cung cấp đủ các tham số."
  echo "Cú pháp: $0 <VPS_ID> <REDIS_HOST> <REDIS_PORT> [REDIS_PASSWORD]"
  exit 1
fi

# --- Xây dựng câu lệnh redis-cli với xác thực (nếu có) ---
REDIS_CMD="redis-cli -h $REDIS_HOST -p $REDIS_PORT"
if [ ! -z "$REDIS_PASSWORD" ]; then
  REDIS_CMD="$REDIS_CMD -a $REDIS_PASSWORD"
fi

# --- KIỂM TRA KẾT NỐI REDIS ---
echo "Đang kiểm tra kết nối tới Redis tại $REDIS_HOST:$REDIS_PORT..."
PING_RESULT=$($REDIS_CMD PING)
if [ "$PING_RESULT" != "PONG" ]; then
    echo "Lỗi: Không thể kết nối tới Redis. Phản hồi: $PING_RESULT"
    exit 1
fi
echo "Kết nối Redis thành công!"


# --- VÒNG LẶP CHÍNH ---
echo "Bắt đầu gửi báo cáo tài nguyên cho VPS ID: $VPS_ID mỗi $INTERVAL giây..."
while true; do
  # 1. Thu thập CPU usage (%)
  # Lấy % CPU đang ở trạng thái "idle", sau đó lấy 100 trừ đi để ra % đang sử dụng
  CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | sed "s/.*, *\([0-9.]*\)%* id.*/\1/" | awk '{print 100 - $1}')

  # 2. Thu thập RAM usage (%)
  # Lấy tổng bộ nhớ và bộ nhớ đã sử dụng từ lệnh `free` để tính phần trăm
  RAM_USAGE=$(free -m | awk 'NR==2{printf "%.2f", $3*100/$2}')

  # 3. Đếm số lượng stream đang hoạt động (giả định mỗi stream là 1 process ffmpeg)
  ACTIVE_STREAMS=$(pgrep -c ffmpeg || echo 0)

  # 4. Lấy timestamp hiện tại
  TIMESTAMP=$(date +%s)

  # 5. Tạo chuỗi JSON payload
  PAYLOAD=$(printf '{"vps_id":%s,"cpu_usage":%s,"ram_usage":%s,"active_streams":%s,"timestamp":%s}' \
    "$VPS_ID" \
    "$CPU_USAGE" \
    "$RAM_USAGE" \
    "$ACTIVE_STREAMS" \
    "$TIMESTAMP")

  # 6. Gửi dữ liệu tới Redis
  echo "Gửi: $PAYLOAD"
  $REDIS_CMD PUBLISH "$CHANNEL" "$PAYLOAD"

  # 7. Chờ cho lần lặp tiếp theo
  sleep $INTERVAL
done 