# Enhanced Stream Management Architecture

## Tổng quan

Đã thiết kế và implement một cơ chế quản lý stream thông minh, rõ ràng để giải quyết các vấn đề:
- Stuck heartbeat và ghost stream cleanup
- Lỗi I/O operation on closed file
- Lỗi NoneType deregister_connect_callback
- Thiếu đồng bộ giữa Laravel và Agent

## Kiến trúc mới

### 1. Stream State Machine (`stream_state_machine.py`)

**States:**
- `INACTIVE`: Stream không hoạt động
- `STARTING`: Laravel đã gửi lệnh start, Agent đang xử lý
- `STREAMING`: Agent đã start thành công và đang stream
- `STOPPING`: Laravel đã gửi lệnh stop, Agent đang xử lý
- `ERROR`: Có lỗi xảy ra, cần cleanup
- `MAINTENANCE`: Đang bảo trì

**Transition Rules:**
- `INACTIVE → STARTING` (Laravel sends START command)
- `STARTING → STREAMING` (Agent confirms start success)
- `STARTING → ERROR` (Agent reports start failure)
- `STREAMING → STOPPING` (Laravel sends STOP command)
- `STOPPING → INACTIVE` (Agent confirms stop success)
- `STOPPING → ERROR` (Agent reports stop failure)
- `Any state → ERROR` (on critical errors)
- `ERROR → INACTIVE` (after cleanup)

**Timeouts:**
- `STARTING`: 5 phút (file download + FFmpeg start)
- `STOPPING`: 30 giây (graceful FFmpeg shutdown)
- `ERROR`: 10 phút (before auto-recovery attempt)

### 2. Enhanced Command Handler (`command_handler.py`)

**Features:**
- **Command Acknowledgment**: Gửi ACK ngay khi nhận command
- **Command Result**: Báo cáo kết quả thực thi command
- **State Machine Integration**: Validate transitions trước khi thực thi
- **Ghost Stream Handling**: Xử lý STOP commands ngay cả khi state không hợp lệ
- **Improved Error Handling**: Safe shutdown với proper cleanup

**Command Flow:**
1. Nhận command từ Laravel
2. Gửi COMMAND_ACK ngay lập tức
3. Validate state transition
4. Thực thi command
5. Gửi COMMAND_RESULT với kết quả

### 3. Enhanced Status Reporter (`status_reporter.py`)

**Improvements:**
- **Enhanced Heartbeat**: Bao gồm stream states, uptime, failure count
- **Dual-Channel Communication**: Tách biệt commands và reports
- **Connection Recovery**: Auto-reconnect Redis khi có lỗi
- **Safe Shutdown**: Proper cleanup của Redis connections

**Heartbeat Data:**
```json
{
  "type": "HEARTBEAT",
  "vps_id": 1,
  "active_streams": [94, 95],
  "stream_states": {
    "94": {
      "state": "STREAMING",
      "state_duration": 120.5,
      "retry_count": 0
    }
  },
  "timestamp": 1753606142,
  "agent_uptime": 3600,
  "consecutive_failures": 0
}
```

### 4. Enhanced Laravel Side

**Simplified Jobs:**
- Removed `ProcessCommandAckJob` and `ProcessCommandResultJob` (redundant with heartbeat system)

**Enhanced ProcessHeartbeatJob:**
- **Enhanced Ghost Detection**: Phân biệt ghost streams và recovery cases
- **Missing Stream Detection**: Tìm streams should be running nhưng không báo cáo
- **State Validation**: Validate và correct stream states
- **Command Tracking**: Track command execution via Redis

**Simplified StreamStatusListener:**
- Only handles STATUS_UPDATE and HEARTBEAT message types
- Removed COMMAND_ACK and COMMAND_RESULT (redundant with heartbeat)

### 5. Error Recovery Mechanisms

**Categorized Errors:**
- **RECOVERABLE**: Network issues, temporary file problems → Auto-retry
- **CRITICAL**: FFmpeg crash, configuration errors → Mark as ERROR
- **SYSTEM**: Agent shutdown, Redis connection loss → Graceful cleanup

**Recovery Strategies:**
- **Auto-retry**: Exponential backoff cho recoverable errors
- **Graceful Fallback**: Force operations khi graceful methods fail
- **State Recovery**: Detect và recover ghost streams
- **Connection Recovery**: Auto-reconnect Redis connections

### 6. Improved Shutdown Process

**Agent Shutdown:**
- Safe component stopping với error handling
- Proper Redis connection cleanup
- File handle safety checks
- Graceful FFmpeg termination

**FFmpeg Process Management:**
- Enhanced stdin 'q' command handling
- Better SIGINT handling
- Improved error catching (ValueError, BrokenPipeError)

## Communication Protocol

### Master-Slave Architecture
- **Laravel (Master)**: Quyết định tất cả state changes
- **Agent (Slave)**: Chỉ báo cáo progress và confirm state changes
- **Clear Separation**: Laravel không bao giờ force sync, Agent không tự quyết định states

### Command Tracking
```
Laravel → Redis → Agent: COMMAND
Agent → Redis → Laravel: STATUS_UPDATE (progress updates)
Agent → Redis → Laravel: HEARTBEAT (every 10s with active_streams)
```

### Redis Keys
- `command_ack:{command_id}`: Command acknowledgments
- `command_result:{command_id}`: Command results
- `command_tracking:{stream_id}:{command_id}`: Full command tracking
- `agent_state:{vps_id}`: Enhanced agent state
- `heartbeat_count:{vps_id}`: Heartbeat counter

## Benefits

### 1. Reliability
- **No More Stuck Heartbeats**: Enhanced error recovery và reconnection
- **No More Ghost Streams**: Smart detection và cleanup
- **No More I/O Errors**: Safe file handle management
- **No More Redis Errors**: Proper connection cleanup

### 2. Observability
- **Command Tracking**: Track toàn bộ command lifecycle
- **Enhanced Logging**: Detailed logs cho debugging
- **State Visibility**: Clear stream state information
- **Performance Metrics**: Uptime, failure counts, durations

### 3. Maintainability
- **Clear Architecture**: Separation of concerns
- **State Machine**: Predictable state transitions
- **Error Categorization**: Structured error handling
- **Comprehensive Testing**: Testable components

### 4. Scalability
- **Concurrent Processing**: ThreadPoolExecutor cho commands
- **Non-blocking Operations**: Async status reporting
- **Resource Management**: Proper cleanup và timeouts
- **Load Balancing**: VPS assignment và tracking

## Implementation Status

✅ **Completed:**
- Stream State Machine implementation
- Enhanced Command Handler với acknowledgment
- Enhanced Status Reporter và Heartbeat mechanism
- Enhanced Laravel side ProcessHeartbeatJob
- Command tracking system
- Error recovery mechanisms
- Safe shutdown improvements

🔄 **In Progress:**
- Comprehensive testing
- Monitoring improvements
- Documentation updates

## Next Steps

1. **Testing**: Comprehensive testing của new architecture
2. **Monitoring**: Add metrics và alerting
3. **Documentation**: Update user documentation
4. **Performance**: Optimize performance bottlenecks
5. **Deployment**: Rolling deployment strategy
