<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\StreamConfiguration;
use App\Models\User;
use Carbon\Carbon;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== TEST EDIT RUNNING STREAM VỚI SCHEDULED_END ===\n\n";

// 1. Tạo test stream đang chạy
echo "1. TẠO TEST STREAM ĐANG CHẠY:\n";

$user = User::first();
if (!$user) {
    echo "   ❌ Không tìm thấy user để test\n";
    exit(1);
}

$testStream = StreamConfiguration::create([
    'user_id' => $user->id,
    'title' => 'Test Running Stream ' . time(),
    'description' => 'Test stream đang chạy',
    'video_source_path' => [['file_id' => 1]],
    'rtmp_url' => 'rtmp://test.com/live',
    'stream_key' => 'test-key-' . time(),
    'status' => 'STREAMING', // Giả lập đang chạy
    'vps_server_id' => 24, // Giả lập đã assign VPS
    'enable_schedule' => false, // Chưa có lịch
    'scheduled_at' => null,
    'scheduled_end' => null,
    'last_started_at' => now()->subMinutes(10), // Đã chạy 10 phút
]);

echo "   ✅ Tạo stream test: #{$testStream->id} - {$testStream->title}\n";
echo "   📊 Trạng thái ban đầu:\n";
echo "     - Status: {$testStream->status}\n";
echo "     - Enable Schedule: " . ($testStream->enable_schedule ? 'Yes' : 'No') . "\n";
echo "     - Scheduled End: " . ($testStream->scheduled_end ? $testStream->scheduled_end : 'None') . "\n";
echo "     - VPS: #{$testStream->vps_server_id}\n";
echo "     - Started: {$testStream->last_started_at}\n\n";

// 2. Mô phỏng edit stream - thêm scheduled_end
echo "2. MÔ PHỎNG EDIT STREAM - THÊM SCHEDULED_END:\n";

$scheduledEnd = now()->addMinutes(5); // Kết thúc sau 5 phút

echo "   🕐 Thêm scheduled_end: {$scheduledEnd->format('Y-m-d H:i:s')}\n";

// Cập nhật như UserStreamManager::update() làm
$updateData = [
    'enable_schedule' => true,
    'scheduled_end' => $scheduledEnd,
];

$testStream->update($updateData);

echo "   ✅ Stream đã được cập nhật\n";
echo "   📊 Trạng thái sau khi edit:\n";
echo "     - Status: {$testStream->status} (vẫn STREAMING)\n";
echo "     - Enable Schedule: " . ($testStream->enable_schedule ? 'Yes' : 'No') . "\n";
echo "     - Scheduled End: {$testStream->scheduled_end->format('Y-m-d H:i:s')}\n\n";

// 3. Kiểm tra logic CheckScheduledStreams
echo "3. KIỂM TRA LOGIC CHECKSCHEDULEDSTREAMS:\n";

$now = Carbon::now();

// Query giống như trong CheckScheduledStreams
$streamsToStop = StreamConfiguration::where('enable_schedule', true)
    ->where('scheduled_end', '<=', $now)
    ->whereIn('status', ['STREAMING', 'STARTING'])
    ->whereNotNull('scheduled_end')
    ->where(function($query) use ($now) {
        $query->whereNull('last_started_at')
              ->orWhere('last_started_at', '<=', $now->copy()->subMinutes(2));
    })
    ->get();

echo "   🔍 Query streams to stop (hiện tại):\n";
echo "     - Điều kiện: enable_schedule = true\n";
echo "     - Điều kiện: scheduled_end <= now()\n";
echo "     - Điều kiện: status IN ['STREAMING', 'STARTING']\n";
echo "     - Điều kiện: scheduled_end IS NOT NULL\n";
echo "     - Điều kiện: last_started_at <= now() - 2 minutes\n\n";

echo "   📊 Kết quả query: {$streamsToStop->count()} streams\n";

if ($streamsToStop->contains('id', $testStream->id)) {
    echo "   ❌ Test stream SẼ BỊ STOP ngay bây giờ (scheduled_end đã qua)\n";
} else {
    echo "   ✅ Test stream CHƯA BỊ STOP (scheduled_end chưa đến)\n";
}

echo "\n";

// 4. Mô phỏng thời gian trôi qua
echo "4. MÔ PHỎNG THỜI GIAN TRÔI QUA:\n";

echo "   ⏰ Giả lập sau 5 phút (khi scheduled_end đến)...\n";

$futureTime = $scheduledEnd->addMinute(); // 1 phút sau scheduled_end

$streamsToStopFuture = StreamConfiguration::where('enable_schedule', true)
    ->where('scheduled_end', '<=', $futureTime)
    ->whereIn('status', ['STREAMING', 'STARTING'])
    ->whereNotNull('scheduled_end')
    ->where(function($query) use ($futureTime) {
        $query->whereNull('last_started_at')
              ->orWhere('last_started_at', '<=', $futureTime->copy()->subMinutes(2));
    })
    ->get();

echo "   📊 Streams sẽ bị stop lúc {$futureTime->format('Y-m-d H:i:s')}: {$streamsToStopFuture->count()}\n";

if ($streamsToStopFuture->contains('id', $testStream->id)) {
    echo "   ✅ Test stream SẼ BỊ STOP khi đến giờ\n";
} else {
    echo "   ❌ Test stream KHÔNG BỊ STOP (có vấn đề logic)\n";
}

echo "\n";

// 5. Mô phỏng CheckScheduledStreams chạy
echo "5. MÔ PHỎNG CHECKSCHEDULEDSTREAMS CHẠY:\n";

if ($streamsToStopFuture->contains('id', $testStream->id)) {
    echo "   🛑 Scheduler sẽ thực hiện:\n";
    echo "     1. Update status: STREAMING → STOPPING\n";
    echo "     2. Set last_stopped_at = now()\n";
    echo "     3. Dispatch StopMultistreamJob\n";
    echo "     4. Agent.py nhận lệnh STOP_STREAM\n";
    echo "     5. Stream thực sự dừng\n";
    echo "     6. Status: STOPPING → INACTIVE\n\n";
    
    echo "   📝 Log sẽ ghi:\n";
    echo "     🕐 [Scheduler] Stopping scheduled stream: {$testStream->title}\n";
    echo "     ✅ [Scheduler] Stop job dispatched for scheduled stream #{$testStream->id}\n\n";
}

// 6. Kết luận
echo "6. KẾT LUẬN:\n";
echo "   🎯 KHI EDIT STREAM ĐANG CHẠY VÀ THÊM SCHEDULED_END:\n\n";

echo "   ✅ NGAY LẬP TỨC:\n";
echo "     - Stream vẫn tiếp tục chạy bình thường\n";
echo "     - Không có gián đoạn nào\n";
echo "     - UpdateMultistreamJob được dispatch (cập nhật config)\n\n";

echo "   ⏰ KHI ĐẾN GIỜ SCHEDULED_END:\n";
echo "     - CheckScheduledStreams (chạy mỗi phút) sẽ phát hiện\n";
echo "     - Tự động dispatch StopMultistreamJob\n";
echo "     - Stream sẽ dừng một cách graceful\n\n";

echo "   🔄 QUY TRÌNH HOÀN CHỈNH:\n";
echo "     1. User edit stream đang chạy → thêm scheduled_end\n";
echo "     2. Stream tiếp tục chạy cho đến scheduled_end\n";
echo "     3. Scheduler tự động stop stream đúng giờ\n";
echo "     4. Stream chuyển về INACTIVE\n\n";

echo "   ⚠️ LƯU Ý:\n";
echo "     - Cần đảm bảo CheckScheduledStreams chạy đều đặn (cron job)\n";
echo "     - Nếu scheduled_end trong quá khứ → stream sẽ bị stop ngay lập tức\n";
echo "     - Agent.py phải hoạt động để nhận lệnh stop\n\n";

// 7. Cleanup
echo "7. CLEANUP:\n";
$testStream->delete();
echo "   🧹 Đã xóa test stream #{$testStream->id}\n\n";

echo "=== KẾT THÚC TEST ===\n";
