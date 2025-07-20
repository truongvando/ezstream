# Test Plan: Fix Giao Tiếp Laravel-Agent

## Các vấn đề đã fix:

### 1. ✅ Bug active_streams trong stream_heartbeat_reporter
- **Vấn đề**: Biến `active_streams` được khai báo trong try block nhưng sử dụng ngoài scope
- **Fix**: Di chuyển khai báo biến ra ngoài try block và reset khi có lỗi

### 2. ✅ Cải thiện xử lý lệnh STOP_STREAM trong agent
- **Vấn đề**: Agent không gửi status STOPPED ngay lập tức, gây conflict với heartbeat
- **Fix**: 
  - Gửi status STOPPED ngay khi nhận lệnh
  - Xóa stream khỏi dict ngay lập tức để tránh heartbeat báo cáo
  - Thêm logging chi tiết

### 3. ✅ Thêm timeout mechanism cho STOPPING status
- **Vấn đề**: Stream có thể bị treo ở STOPPING nếu agent không phản hồi
- **Fix**: Thêm logic timeout 2 phút trong StreamStatusListener

### 4. ✅ Cải thiện logging và debugging
- **Fix**: Thêm logging chi tiết trong cả Laravel và Agent để debug

## Test Cases:

### Test Case 1: Normal Stop Flow
1. Start một stream
2. Stop stream qua Laravel
3. Verify:
   - Laravel gửi STOP_STREAM command
   - Agent nhận và xử lý ngay
   - Agent gửi STOPPED status về Laravel
   - Không có conflict trong heartbeat

### Test Case 2: Agent Offline Scenario
1. Start một stream
2. Tắt agent
3. Stop stream qua Laravel
4. Verify:
   - Laravel đặt status STOPPING
   - Sau 2 phút, timeout mechanism chuyển thành INACTIVE

### Test Case 3: Race Condition Test
1. Start nhiều streams
2. Stop tất cả cùng lúc
3. Verify:
   - Không có conflict giữa stop commands và heartbeat
   - Tất cả streams đều chuyển thành STOPPED/INACTIVE

### Test Case 4: Scheduler Stop Test
1. Tạo stream với scheduled_end trong quá khứ
2. Chạy scheduler: `php artisan streams:check-scheduled`
3. Verify:
   - Scheduler đặt status STOPPING
   - Agent nhận và xử lý STOP_STREAM
   - Không có conflict với heartbeat
   - Stream chuyển thành INACTIVE

## Monitoring Commands:

```bash
# Monitor Laravel logs
tail -f storage/logs/laravel.log | grep -E "(STOP_STREAM|STOPPING|Heartbeat|Scheduler)"

# Monitor Agent logs
tail -f /path/to/agent.log | grep -E "(STOP_STREAM|STOPPED|Heartbeat)"

# Monitor Redis traffic
redis-cli monitor | grep -E "(vps-commands|stream-status)"

# Test scheduler stop flow
php artisan test:scheduler-stop-flow

# Test specific stream stop
php artisan test:stop-stream-fix 123
```

## Expected Behavior After Fix:

1. **Immediate Response**: Agent gửi STOPPED status ngay khi nhận STOP_STREAM
2. **No Conflicts**: Heartbeat không báo cáo streams đã được stop
3. **Timeout Protection**: STOPPING status tự động chuyển INACTIVE sau 2 phút
4. **Better Logging**: Chi tiết đầy đủ để debug

## Files Modified:

1. `storage/app/ezstream-agent/agent.py`:
   - Fix bug active_streams scope
   - Cải thiện stop_stream method
   - Cải thiện heartbeat_reporter
   - Thêm logging chi tiết

2. `app/Jobs/StopMultistreamJob.php`:
   - Thêm logging chi tiết

3. `app/Console/Commands/StreamStatusListener.php`:
   - Thêm timeout mechanism cho STOPPING status

4. `app/Jobs/UpdateStreamStatusJob.php` (mới):
   - Xử lý status updates từ agent

5. `app/Console/Commands/CheckScheduledStreams.php`:
   - Fix race condition trong scheduler stop
   - Thêm logging chi tiết
   - Tránh dừng streams vừa mới start

6. `app/Console/Commands/TestSchedulerStopFlow.php` (mới):
   - Test scheduler stop flow
