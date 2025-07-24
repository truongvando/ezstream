#!/usr/bin/env python3
"""
Test script để gửi status update giả qua Redis để debug
"""
import redis
import json
import time

# Redis connection
redis_conn = redis.Redis(host='localhost', port=6379, decode_responses=True)

def send_test_status_update(stream_id, status, message):
    """Gửi test status update"""
    payload = {
        'type': 'STATUS_UPDATE',
        'stream_id': int(stream_id),
        'vps_id': 44,  # VPS ID test
        'status': status,
        'message': message,
        'timestamp': int(time.time()),
        'extra_data': {}
    }
    
    json_payload = json.dumps(payload)
    channel = 'agent-reports'
    
    print(f"🔄 Sending test status update:")
    print(f"   Channel: {channel}")
    print(f"   Payload: {json_payload}")
    
    subscribers = redis_conn.publish(channel, json_payload)
    print(f"   Sent to {subscribers} subscribers")
    
    return subscribers > 0

if __name__ == "__main__":
    print("🧪 Test Status Update Script")
    print("=" * 50)

    # Wait a bit for Redis connection to stabilize
    print("\n⏳ Waiting 3 seconds for Redis connection...")
    time.sleep(3)

    # Test flow đơn giản như Agent thực tế
    print("\n1. Testing STARTING status...")
    result = send_test_status_update(93, 'STARTING', 'Đang khởi động stream...')
    if not result:
        print("   ⚠️ No subscribers found!")

    time.sleep(2)

    # Test 2: Send PROGRESS status (should not override STARTING)
    print("\n2. Testing PROGRESS status...")
    payload = {
        'type': 'STATUS_UPDATE',
        'stream_id': 93,
        'vps_id': 44,
        'status': 'PROGRESS',
        'message': 'Đang khởi động FFmpeg...',
        'timestamp': int(time.time()),
        'extra_data': {'progress_data': {'stage': 'starting_ffmpeg', 'progress_percentage': 80}}
    }
    json_payload = json.dumps(payload)
    subscribers = redis_conn.publish('agent-reports', json_payload)
    print(f"   Sent PROGRESS to {subscribers} subscribers")

    time.sleep(2)

    # Test 3: Send STREAMING status
    print("\n3. Testing STREAMING status...")
    result = send_test_status_update(93, 'STREAMING', 'Stream đang phát trực tiếp!')
    if not result:
        print("   ⚠️ No subscribers found!")

    time.sleep(2)

    # Test 4: Send STOPPED status
    print("\n4. Testing STOPPED status...")
    result = send_test_status_update(93, 'STOPPED', 'Test stream đã dừng')
    if not result:
        print("   ⚠️ No subscribers found!")

    print("\n✅ Test completed!")
