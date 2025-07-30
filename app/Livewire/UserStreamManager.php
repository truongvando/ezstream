<?php

namespace App\Livewire;

use App\Models\StreamConfiguration;
use App\Models\UserFile;
use App\Services\Stream\StreamAllocation;
use App\Services\StreamProgressService;
use App\Jobs\StartMultistreamJob;
use App\Jobs\StopMultistreamJob;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Livewire\Component;
use Livewire\WithPagination;

class UserStreamManager extends BaseStreamManager
{
    // Override abstract methods for user permissions
    protected function canManageAllStreams(): bool
    {
        return false; // Users can only manage their own streams
    }

    protected function canForceStop(): bool
    {
        return false; // Users cannot force stop streams
    }

    protected function requiresSubscription(): bool
    {
        return true; // Users need active subscription
    }

    protected function getLayoutName(): string
    {
        return 'layouts.sidebar';
    }

    /**
     * Livewire event listeners
     */
    protected $listeners = ['refreshStreams' => 'refreshStreams'];

    public $videoSource = 'upload'; // Quản lý tab active trong Quick Stream Modal

    /**
     * Refresh streams method for polling
     */
    public function refreshStreams()
    {
        // This method is called by wire:poll.3s="refreshStreams" or event dispatch
        // Clear any cached properties and force re-render
        $this->resetPage();

        // Force refresh streams property
        unset($this->streams);

        // Log for debugging
        \Log::debug("🔄 [UserStreamManager] Polling refresh triggered");
    }



    // All common properties and methods are now inherited from BaseStreamManager
    // Common methods inherited from BaseStreamManager

    public function store()
    {
        $this->validate();

        $rtmpUrl = $this->platform === 'custom' ? $this->rtmp_url : $this->getPlatformUrl($this->platform);

        // Prepare file list for JSON storage
        $selectedFiles = UserFile::whereIn('id', $this->user_file_ids)->get();
        $fileList = $selectedFiles->map(function ($file) {
            return [
                'file_id' => $file->id,
                'filename' => $file->original_name,
                'path' => $file->disk === 'google_drive' ? $file->google_drive_file_id : $file->path,
                'disk' => $file->disk,
                'size' => $file->size,
            ];
        })->toArray();

        // Auto-generate backup URL based on platform
        $backupRtmpUrl = $this->platform !== 'custom' ? $this->getPlatformBackupUrl($this->platform) : '';

        // Create stream configuration (VPS allocation will be handled by StartStreamJob)
        Auth::user()->streamConfigurations()->create([
            'title' => $this->title,
            'description' => $this->description,
            'video_source_path' => $fileList, // Store file list as array (auto-cast to JSON)
            'rtmp_url' => $rtmpUrl,
            'rtmp_backup_url' => $backupRtmpUrl, // Auto-generated backup
            'stream_key' => $this->stream_key,
            'status' => 'INACTIVE',
            'loop' => $this->loop,
            'scheduled_at' => $this->enable_schedule ? $this->scheduled_at : null,
            'scheduled_end' => $this->enable_schedule ? $this->scheduled_end : null,
            'enable_schedule' => $this->enable_schedule,
            'playlist_order' => $this->playlist_order,
            'keep_files_on_agent' => $this->keep_files_on_agent,
            'user_file_id' => $selectedFiles->first()->id, // Primary file for backward compatibility
            'last_started_at' => $this->enable_schedule ? null : now(), // Don't set start time for scheduled streams
        ]);

        $this->showCreateModal = false;
        session()->flash('success', 'Stream đã được tạo thành công. Nhấn "Bắt đầu Stream" để khởi động.');
    }

    public function edit($streamId)
    {
        $stream = StreamConfiguration::where('id', $streamId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // Check if this is a quick stream - use quick stream modal
        if ($stream->is_quick_stream) {
            $this->editQuickStream($stream);
            return;
        }

        // Reset all form state first
        $this->resetValidation();
        $this->reset(['showCreateModal', 'showEditModal', 'showQuickStreamModal']);

        $this->editingStream = $stream;
        $this->title = $stream->title;
        $this->description = $stream->description;
        
        // Load file IDs from video_source_path (JSON array)
        try {
            if (is_array($stream->video_source_path)) {
                // Already an array from database cast
                $this->user_file_ids = collect($stream->video_source_path)->pluck('file_id')->filter()->values()->toArray();
            } elseif (is_string($stream->video_source_path)) {
                // JSON string, decode it
                $fileList = json_decode($stream->video_source_path, true);
                $this->user_file_ids = $fileList ? collect($fileList)->pluck('file_id')->filter()->values()->toArray() : [];
            } else {
                // Fallback to single file
                $this->user_file_ids = $stream->user_file_id ? [$stream->user_file_id] : [];
            }
        } catch (\Exception $e) {
            // Fallback for any errors
            $this->user_file_ids = $stream->user_file_id ? [$stream->user_file_id] : [];
        }

        // Load platform and RTMP settings
        $this->platform = $this->detectPlatformFromUrl($stream->rtmp_url) ?? 'youtube';
        $this->rtmp_url = $stream->rtmp_url;
        $this->stream_key = $stream->stream_key;

        // Load advanced settings
        $this->loop = $stream->loop ?? false;
        $this->playlist_order = $stream->playlist_order ?? 'sequential';
        $this->keep_files_on_agent = $stream->keep_files_on_agent ?? false;

        // Load schedule settings
        $this->enable_schedule = $stream->enable_schedule ?? false;
        $this->scheduled_at = $stream->scheduled_at ? $stream->scheduled_at->format('Y-m-d\TH:i') : null;
        $this->scheduled_end = $stream->scheduled_end ? $stream->scheduled_end->format('Y-m-d\TH:i') : null;

        // Load user files for display
        $this->loadUserFiles();

        $this->showEditModal = true;
    }

    public function update()
    {
        \Illuminate\Support\Facades\Log::info('UserStreamManager update called', [
            'editing_stream_id' => $this->editingStream->id ?? 'null',
            'user_file_ids' => $this->user_file_ids,
            'title' => $this->title,
            'platform' => $this->platform
        ]);

        if ($this->editingStream->user_id !== Auth::id()) {
            abort(403);
        }
        $this->validate();
        
        $rtmpUrl = $this->platform === 'custom' ? $this->rtmp_url : $this->getPlatformUrl($this->platform);

        // Prepare file list for JSON storage
        $selectedFiles = UserFile::whereIn('id', $this->user_file_ids)->get();
        $fileList = $selectedFiles->map(function ($file) {
            return [
                'file_id' => $file->id,
                'filename' => $file->original_name,
                'path' => $file->disk === 'google_drive' ? $file->google_drive_file_id : $file->path,
                'disk' => $file->disk,
                'size' => $file->size,
            ];
        })->toArray();

        // Auto-generate backup URL based on platform
        $backupRtmpUrl = $this->platform !== 'custom' ? $this->getPlatformBackupUrl($this->platform) : '';

        $updateData = [
            'title' => $this->title,
            'description' => $this->description,
            'video_source_path' => $fileList, // Store file list as array (auto-cast to JSON)
            'rtmp_url' => $rtmpUrl,
            'rtmp_backup_url' => $backupRtmpUrl, // Auto-generated backup
            'stream_key' => $this->stream_key,
            'loop' => $this->loop,
            'enable_schedule' => $this->enable_schedule,
            'scheduled_at' => $this->enable_schedule ? $this->scheduled_at : null,
            'scheduled_end' => $this->enable_schedule ? $this->scheduled_end : null,
            'playlist_order' => $this->playlist_order,
            'keep_files_on_agent' => $this->keep_files_on_agent,
            'user_file_id' => $selectedFiles->first()->id,
            // ✅ Add last_started_at like in create method
            'last_started_at' => $this->enable_schedule ? null : ($this->editingStream->last_started_at ?: now()),
        ];

        // ✅ FIXED: Smart status handling to prevent auto-restart
        $currentStatus = $this->editingStream->status;
        if (in_array($currentStatus, ['STOPPED', 'ERROR'])) {
            // Only reset to INACTIVE if schedule is disabled
            // If schedule is enabled, keep STOPPED to prevent auto-restart
            if (!$this->enable_schedule) {
                $updateData['status'] = 'INACTIVE';
            } else {
                // Keep STOPPED status for scheduled streams to prevent auto-restart
                // User must manually start or wait for next scheduled time
                $updateData['status'] = 'STOPPED';
            }
        }
        // Keep STREAMING, STARTING, STOPPING, INACTIVE unchanged

        // Log the update for debugging
        Log::info("🔧 [UserStreamManager] Updating stream", [
            'stream_id' => $this->editingStream->id,
            'title' => $this->title,
            'old_status' => $currentStatus,
            'new_status' => $updateData['status'] ?? $currentStatus,
            'enable_schedule' => $this->enable_schedule,
            'user_id' => Auth::id()
        ]);

        $this->editingStream->update($updateData);

        // If stream is currently running, dispatch update job
        if ($this->editingStream->status === 'STREAMING') {
            // Count file changes for better user feedback
            $oldFileIds = collect($this->editingStream->getOriginal('video_source_path'))->pluck('file_id')->toArray();
            $newFileIds = $this->user_file_ids;
            $addedFiles = array_diff($newFileIds, $oldFileIds);
            $removedFiles = array_diff($oldFileIds, $newFileIds);

            \App\Jobs\UpdateMultistreamJob::dispatch($this->editingStream);

            $message = 'Stream đang chạy đã được cập nhật! ';
            if (count($addedFiles) > 0) {
                $message .= '✅ Đã thêm ' . count($addedFiles) . ' file mới. ';
            }
            if (count($removedFiles) > 0) {
                $message .= '🗑️ Đã xóa ' . count($removedFiles) . ' file. ';
            }
            $message .= 'Thay đổi sẽ có hiệu lực trong vài giây.';

            session()->flash('success', $message);
        } else {
            $message = 'Đã cập nhật cấu hình stream thành công.';

            // Add warning for scheduled streams that were stopped
            if ($this->enable_schedule && in_array($currentStatus, ['STOPPED', 'ERROR'])) {
                $message .= ' ⚠️ Stream có lịch phát được giữ ở trạng thái STOPPED để tránh tự khởi động. Bạn có thể start thủ công hoặc chờ lịch phát tiếp theo.';
            }

            session()->flash('success', $message);
        }

        $this->showEditModal = false;
        $this->editingStream = null;
    }

    public function closeModal()
    {
        $this->reset(['showCreateModal', 'showEditModal', 'showQuickStreamModal']);
        $this->editingStream = null;
        $this->resetValidation();
    }

    public function toggleFileSelection($fileId)
    {
        $fileId = (int) $fileId;
        if (in_array($fileId, $this->quickSelectedFiles)) {
            $this->quickSelectedFiles = array_diff($this->quickSelectedFiles, [$fileId]);
        } else {
            $this->quickSelectedFiles[] = $fileId;
        }
        $this->quickSelectedFiles = array_values($this->quickSelectedFiles); // Re-index array
    }

    public function switchTab($tab)
    {
        $this->videoSource = $tab;
    }

    // confirmDelete and delete methods inherited from BaseStreamManager

    public function confirmDelete(StreamConfiguration $stream)
    {
        \Illuminate\Support\Facades\Log::info('UserStreamManager confirmDelete called', [
            'stream_id' => $stream->id,
            'user_id' => \Illuminate\Support\Facades\Auth::id(),
            'stream_user_id' => $stream->user_id
        ]);

        // Call parent method
        parent::confirmDelete($stream);
    }

    public function startStream($stream)
    {
        // Handle array, ID, or model object
        if (is_array($stream)) {
            $stream = StreamConfiguration::find($stream['id']);
        } elseif (is_numeric($stream)) {
            $stream = StreamConfiguration::find($stream);
        }

        if (!$stream) {
            session()->flash('error', 'Stream không tồn tại.');
            return;
        }

        // Log to both Laravel log and error_log for debugging
        $logMessage = "🎯 [UserStreamManager] startStream called - Stream ID: {$stream->id}, Status: {$stream->status}";
        Log::info($logMessage);
        error_log($logMessage);

        Log::info("🎯 [UserStreamManager] startStream called", [
            'stream_id' => $stream->id,
            'current_status' => $stream->status,
            'user_id' => Auth::id(),
            'stream_user_id' => $stream->user_id
        ]);

        if ($stream->user_id !== Auth::id()) {
            Log::warning("🚫 [UserStreamManager] Unauthorized access attempt", [
                'stream_id' => $stream->id,
                'auth_user' => Auth::id(),
                'stream_owner' => $stream->user_id
            ]);
            abort(403);
        }

        // Refresh stream to get latest status
        $stream->refresh();

        Log::info("🔄 [UserStreamManager] Stream refreshed", [
            'stream_id' => $stream->id,
            'status_after_refresh' => $stream->status
        ]);

        // Check if already starting/streaming
        if (in_array($stream->status, ['STARTING', 'STREAMING'])) {
            Log::warning("⚠️ [UserStreamManager] Stream already in progress", [
                'stream_id' => $stream->id,
                'current_status' => $stream->status
            ]);
            session()->flash('error', 'Stream đã đang được xử lý.');
            return;
        }

        // ✅ Check concurrent streams limit
        $user = Auth::user();
        $currentActiveStreams = $user->streamConfigurations()
            ->whereIn('status', ['STREAMING', 'STARTING'])
            ->count();
        $allowedStreams = $user->getTotalAllowedStreams();

        if ($currentActiveStreams >= $allowedStreams) {
            Log::warning("🚫 [UserStreamManager] Concurrent streams limit exceeded", [
                'user_id' => $user->id,
                'current_streams' => $currentActiveStreams,
                'allowed_streams' => $allowedStreams,
                'stream_id' => $stream->id
            ]);

            $package = $user->currentPackage();
            $packageName = $package ? $package->name : 'Không xác định';

            // Emit event for immediate notification
            $this->dispatch('show-limit-exceeded', [
                'message' => "❌ Vượt quá giới hạn streams đồng thời. Gói {$packageName} cho phép tối đa {$allowedStreams} streams. Hiện tại: {$currentActiveStreams} streams đang chạy.",
                'current' => $currentActiveStreams,
                'allowed' => $allowedStreams,
                'package' => $packageName
            ]);
            return;
        }

        $stream->update(['status' => 'STARTING']);

        Log::info("📤 [UserStreamManager] Dispatching StartMultistreamJob", [
            'stream_id' => $stream->id
        ]);

        StartMultistreamJob::dispatch($stream);
        session()->flash('message', 'Lệnh bắt đầu stream đã được gửi đi.');

        // Trigger JavaScript event for progress tracking
        $this->dispatch('stream-started', streamId: $stream->id);

        // Sửa: Thêm refresh để cập nhật UI ngay lập tức
        $this->dispatch('$refresh');
    }


    public function stopStream(StreamConfiguration $stream)
    {
        if ($stream->user_id !== Auth::id()) {
            abort(403);
        }

        $stream->update(['status' => 'STOPPING']);
        StopMultistreamJob::dispatch($stream);

        // Force immediate UI refresh
        $this->dispatch('$refresh');

        session()->flash('message', 'Lệnh dừng stream đã được gửi đi.');
    }

    // openQuickStreamModal and updateQuickPlatformUrl methods inherited from BaseStreamManager

    public function selectUploadedFile($fileId)
    {
        if (!in_array($fileId, $this->quickSelectedFiles)) {
            $this->quickSelectedFiles[] = $fileId;
        }
        $this->dispatch('refreshComponent');
    }

    /**
     * Edit Quick Stream - Load data into quick stream modal
     */
    public function editQuickStream($stream)
    {
        // Reset other modals
        $this->showCreateModal = false;
        $this->showEditModal = false;

        // Load stream data into quick stream fields
        $this->editingStream = $stream;
        $this->quickTitle = $stream->title;
        $this->quickDescription = $stream->description;
        $this->quickPlatform = $this->detectPlatformFromUrl($stream->rtmp_url) ?? 'youtube';
        $this->quickRtmpUrl = $stream->rtmp_url;
        $this->quickStreamKey = $stream->stream_key;
        $this->quickLoop = $stream->loop ?? false;
        $this->quickPlaylistOrder = $stream->playlist_order ?? 'sequential';
        $this->quickEnableSchedule = $stream->enable_schedule ?? false;
        $this->quickScheduledAt = $stream->scheduled_at ? $stream->scheduled_at->format('Y-m-d\TH:i') : '';
        $this->quickScheduledEnd = $stream->scheduled_end ? $stream->scheduled_end->format('Y-m-d\TH:i') : '';
        $this->quickAutoDelete = $stream->auto_delete_from_cdn ?? true;

        // Load selected files
        $this->quickSelectedFiles = collect($stream->video_source_path)->pluck('file_id')->toArray();

        // Load user files for selection
        $this->loadUserFiles();

        // Open quick stream modal
        $this->showQuickStreamModal = true;

        Log::info('✏️ Quick Stream loaded for editing', [
            'stream_id' => $stream->id,
            'title' => $stream->title
        ]);
    }

    public function createQuickStream()
    {
        // This method should ONLY create new streams
        // Edit functionality is handled by updateQuickStream() method

        Log::info(' [QuickStream] Starting createQuickStream', [
            'quickTitle' => $this->quickTitle,
            'quickPlatform' => $this->quickPlatform,
            'quickSelectedFiles' => $this->quickSelectedFiles,
            'video_source_id' => $this->video_source_id,
        ]);

        // 🔍 DEBUG: Log form data before validation
        Log::info('🔍 [QuickStream] Form data debug', [
            'quickSelectedFiles' => $this->quickSelectedFiles,
            'quickSelectedFiles_type' => gettype($this->quickSelectedFiles),
            'quickSelectedFiles_count' => is_array($this->quickSelectedFiles) ? count($this->quickSelectedFiles) : 'not_array',
            'video_source_id' => $this->video_source_id,
        ]);

        // 🔧 CRITICAL FIX: Convert Proxy(Array) to real array
        if (is_object($this->quickSelectedFiles) && method_exists($this->quickSelectedFiles, 'toArray')) {
            $this->quickSelectedFiles = $this->quickSelectedFiles->toArray();
        } elseif (is_object($this->quickSelectedFiles)) {
            // Handle Livewire Proxy Array
            $this->quickSelectedFiles = collect($this->quickSelectedFiles)->toArray();
        }

        // Ensure it's an array
        if (!is_array($this->quickSelectedFiles)) {
            $this->quickSelectedFiles = [];
        }

        Log::info('🔧 [QuickStream] After conversion', [
            'quickSelectedFiles_type' => gettype($this->quickSelectedFiles),
            'quickSelectedFiles_count' => count($this->quickSelectedFiles),
            'quickSelectedFiles_values' => $this->quickSelectedFiles,
        ]);

        // 🚨 CRITICAL FIX: Validate files FIRST before other fields
        if (empty($this->video_source_id) && count($this->quickSelectedFiles) === 0) {
            session()->flash('error', '❌ Vui lòng chọn ít nhất một video từ thư viện hoặc upload file mới.');
            Log::warning('🚨 [QuickStream] No files selected in validation', [
                'video_source_id' => $this->video_source_id,
                'quickSelectedFiles_count' => count($this->quickSelectedFiles)
            ]);
            return;
        }

        // First validate basic fields (OUTSIDE try-catch so validation errors show)
        $this->validate([
            'quickTitle' => 'required|string|max:255',
            'quickDescription' => 'nullable|string',
            'quickPlatform' => 'required|string',
            'quickRtmpUrl' => 'required_if:quickPlatform,custom|nullable|url',
            'quickStreamKey' => 'required|string',
            // 🚨 CRITICAL: Add schedule validation
            'quickScheduledAt' => 'required_if:quickEnableSchedule,true|nullable|date|after:now',
            'quickScheduledEnd' => 'nullable|date|after:quickScheduledAt',
        ], [
            'quickTitle.required' => 'Tiêu đề stream là bắt buộc.',
            'quickTitle.max' => 'Tiêu đề stream không được vượt quá 255 ký tự.',
            'quickPlatform.required' => 'Vui lòng chọn nền tảng stream.',
            'quickRtmpUrl.required_if' => 'URL RTMP là bắt buộc khi chọn Custom.',
            'quickRtmpUrl.url' => 'URL RTMP không hợp lệ.',
            'quickStreamKey.required' => 'Stream key là bắt buộc.',
            'quickScheduledAt.required_if' => 'Thời gian bắt đầu là bắt buộc khi bật lịch phát.',
            'quickScheduledAt.after' => 'Thời gian bắt đầu phải sau thời điểm hiện tại.',
            'quickScheduledEnd.after' => 'Thời gian kết thúc phải sau thời gian bắt đầu.',
        ]);

        try {

        $user = Auth::user();
        $userFiles = collect();

        // Check if we have uploaded file (from quick upload)
        if (!empty($this->video_source_id)) {
            $uploadedFile = $user->files()->where('id', $this->video_source_id)->first();
            if ($uploadedFile) {
                $userFiles->push($uploadedFile);
                Log::info('🎬 [QuickStream] Found uploaded file', ['file_id' => $uploadedFile->id, 'name' => $uploadedFile->original_name]);
            }
        }

        // Check if we have selected files from library
        if (!empty($this->quickSelectedFiles)) {
            $libraryFiles = $user->files()->whereIn('id', $this->quickSelectedFiles)->get();
            $userFiles = $userFiles->merge($libraryFiles);
            Log::info('🎬 [QuickStream] Found library files', ['file_count' => $libraryFiles->count(), 'file_ids' => $this->quickSelectedFiles]);
        }

        // Validate that we have at least one file
        if ($userFiles->isEmpty()) {
            Log::warning('🎬 [QuickStream] No files selected', [
                'video_source_id' => $this->video_source_id,
                'quickSelectedFiles' => $this->quickSelectedFiles
            ]);
            session()->flash('error', 'Vui lòng chọn ít nhất một video hoặc upload file mới.');
            return;
        }

        // ✅ Check concurrent streams limit for Quick Stream
        $currentActiveStreams = $user->streamConfigurations()
            ->whereIn('status', ['STREAMING', 'STARTING'])
            ->count();
        $allowedStreams = $user->getTotalAllowedStreams();

        if ($currentActiveStreams >= $allowedStreams) {
            Log::warning("🚫 [UserStreamManager] Quick Stream: Concurrent streams limit exceeded", [
                'user_id' => $user->id,
                'current_streams' => $currentActiveStreams,
                'allowed_streams' => $allowedStreams
            ]);

            $package = $user->currentPackage();
            $packageName = $package ? $package->name : 'Không xác định';

            // Emit event for immediate notification
            $this->dispatch('show-limit-exceeded', [
                'message' => "❌ Vượt quá giới hạn streams đồng thời. Gói {$packageName} cho phép tối đa {$allowedStreams} streams. Hiện tại: {$currentActiveStreams} streams đang chạy.",
                'current' => $currentActiveStreams,
                'allowed' => $allowedStreams,
                'package' => $packageName
            ]);
            return;
        }

        // Mark files for auto-deletion (based on user choice)
        foreach ($userFiles as $userFile) {
            $userFile->update([
                'auto_delete_after_stream' => $this->quickAutoDelete,
                'scheduled_deletion_at' => $this->quickAutoDelete ? now()->addDays(1) : null // Grace period
            ]);
        }

        // Prepare file list for stream
        $fileList = [];
        foreach ($userFiles as $index => $userFile) {
            $fileList[] = [
                'file_id' => $userFile->id,
                'filename' => $userFile->original_name,
                'order' => $index + 1
            ];
        }

        // Get platform URLs
        $rtmpUrl = $this->quickPlatform === 'custom' ? $this->quickRtmpUrl : $this->getPlatformUrl($this->quickPlatform);
        $backupRtmpUrl = $this->getPlatformBackupUrl($this->quickPlatform);

        // 🚨 CRITICAL: Properly handle schedule data
        $scheduledAt = null;
        $scheduledEnd = null;
        $enableSchedule = (bool) $this->quickEnableSchedule;

        if ($enableSchedule) {
            // Validate schedule times are not empty
            if (empty($this->quickScheduledAt)) {
                session()->flash('error', 'Vui lòng chọn thời gian bắt đầu khi bật lịch phát.');
                return;
            }

            try {
                $scheduledAt = \Carbon\Carbon::parse($this->quickScheduledAt);
                if ($this->quickScheduledEnd) {
                    $scheduledEnd = \Carbon\Carbon::parse($this->quickScheduledEnd);
                }

                // Additional validation
                if ($scheduledAt->isPast()) {
                    session()->flash('error', 'Thời gian bắt đầu phải trong tương lai.');
                    return;
                }

            } catch (\Exception $e) {
                session()->flash('error', 'Định dạng thời gian không hợp lệ.');
                return;
            }
        }

        // Create quick stream configuration
        $stream = $userFiles->first()->user->streamConfigurations()->create([
            'title' => $this->quickTitle,
            'description' => $this->quickDescription,
            'video_source_path' => $fileList,
            'rtmp_url' => $rtmpUrl,
            'rtmp_backup_url' => $backupRtmpUrl,
            'stream_key' => $this->quickStreamKey,
            'status' => $enableSchedule ? 'INACTIVE' : 'STARTING', // Respect schedule
            'loop' => $this->quickLoop,
            'playlist_order' => 'sequential',
            'is_quick_stream' => true,
            'auto_delete_from_cdn' => $this->quickAutoDelete, // User choice
            'user_file_id' => $userFiles->first()->id,
            'scheduled_at' => $scheduledAt,
            'scheduled_end' => $scheduledEnd,
            'enable_schedule' => $enableSchedule,
            'last_started_at' => $enableSchedule ? null : now()
        ]);

        Log::info("🎬 [QuickStream] Stream created successfully", [
            'stream_id' => $stream->id,
            'title' => $stream->title,
            'status' => $stream->status,
            'file_count' => count($fileList),
            'scheduled' => $enableSchedule,
            'scheduled_at' => $scheduledAt ? $scheduledAt->toDateTimeString() : null,
            'scheduled_end' => $scheduledEnd ? $scheduledEnd->toDateTimeString() : null
        ]);

        // Only start immediately if not scheduled
        if (!$enableSchedule) {
            StartMultistreamJob::dispatch($stream);
            $message = 'Quick Stream đã được tạo và bắt đầu ngay!';
        } else {
            $message = 'Quick Stream đã được tạo và sẽ bắt đầu vào ' . $scheduledAt->format('d/m/Y H:i');
        }

        Log::info("✅ [QuickStream] Stream created", [
            'stream_id' => $stream->id,
            'user_id' => $userFiles->first()->user_id,
            'title' => $stream->title,
            'platform' => $this->quickPlatform,
            'immediate_start' => !$this->quickEnableSchedule
        ]);

        $this->showQuickStreamModal = false;
        $this->resetQuickStreamForm();

        session()->flash('success', $message);

        // Sửa: Dùng $this->dispatch thay vì 'refreshStreams'
        $this->dispatch('$refresh');

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions so they show in UI
            throw $e;
        } catch (\Exception $e) {
            Log::error('❌ [QuickStream] Failed to create quick stream', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'quickTitle' => $this->quickTitle,
                'quickPlatform' => $this->quickPlatform
            ]);

            session()->flash('error', 'Có lỗi xảy ra khi tạo Quick Stream: ' . $e->getMessage());
        }
    }

    protected function detectPlatformFromUrl($url)
    {
        if (str_contains($url, 'youtube.com')) return 'youtube';
        return 'custom';
    }

    protected function getPlatformUrl($platform)
    {
        $platforms = [
            'youtube' => 'rtmp://a.rtmp.youtube.com/live2',
            'custom' => ''
        ];
        return $platforms[$platform] ?? '';
    }

    protected function getPlatformBackupUrl($platform)
    {
        $backupPlatforms = [
            'youtube' => 'rtmp://b.rtmp.youtube.com/live2',
            'custom' => ''
        ];
        return $backupPlatforms[$platform] ?? '';
    }

    private function getQuickUploadedFileId()
    {
        // Return the file ID set by JavaScript
        return $this->quickUploadedFileId;
    }

    protected function resetQuickStreamForm()
    {
        $this->quickTitle = '';
        $this->quickDescription = '';
        $this->quickPlatform = 'youtube';
        $this->quickRtmpUrl = '';
        $this->quickStreamKey = '';
        $this->quickLoop = false;
        $this->quickPlaylistOrder = 'sequential';
        $this->quickEnableSchedule = false;
        $this->quickScheduledAt = '';
        $this->quickScheduledEnd = '';
        $this->quickAutoDelete = true; // Default to auto-delete
        $this->quickSelectedFiles = [];
        $this->video_source_id = null;
    }



    /**
     * Get real-time progress for a stream from Redis
     */
    public function getStreamProgress($streamId)
    {
        try {
            $progress = StreamProgressService::getProgress($streamId);

            if ($progress) {
                return [
                    'stage' => $progress['stage'] ?? 'starting',
                    'progress_percentage' => $progress['progress_percentage'] ?? 0,
                    'message' => $progress['message'] ?? 'Đang chuẩn bị...',
                    'details' => $progress['details'] ?? [],
                    'updated_at' => $progress['updated_at'] ?? now()->toISOString()
                ];
            }

            // Fallback: Check stream status
            $stream = StreamConfiguration::find($streamId);
            if ($stream) {
                switch ($stream->status) {
                    case 'STARTING':
                        return [
                            'stage' => 'starting',
                            'progress_percentage' => 10,
                            'message' => 'Đang chuẩn bị...',
                            'details' => [],
                            'updated_at' => now()->toISOString()
                        ];
                    case 'STREAMING':
                        return [
                            'stage' => 'streaming',
                            'progress_percentage' => 100,
                            'message' => 'Đang phát trực tiếp!',
                            'details' => [],
                            'updated_at' => now()->toISOString()
                        ];
                    default:
                        return null;
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error("Error getting stream progress for stream {$streamId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update Quick Stream
     */
    public function updateQuickStream()
    {
        // 🔧 CRITICAL FIX: Convert Proxy(Array) to real array for update
        if (is_object($this->quickSelectedFiles) && method_exists($this->quickSelectedFiles, 'toArray')) {
            $this->quickSelectedFiles = $this->quickSelectedFiles->toArray();
        } elseif (is_object($this->quickSelectedFiles)) {
            $this->quickSelectedFiles = collect($this->quickSelectedFiles)->toArray();
        }

        if (!is_array($this->quickSelectedFiles)) {
            $this->quickSelectedFiles = [];
        }

        Log::info('✏️ [QuickStream] Starting updateQuickStream', [
            'stream_id' => $this->editingStream->id,
            'quickTitle' => $this->quickTitle,
            'quickSelectedFiles' => $this->quickSelectedFiles,
            'quickSelectedFiles_count' => count($this->quickSelectedFiles),
        ]);

        try {
            // Validation
            $this->validate([
                'quickTitle' => 'required|string|max:255',
                'quickPlatform' => 'required|string',
                'quickStreamKey' => 'required|string',
                'quickSelectedFiles' => 'required|array|min:1',
            ]);

            // Get user files
            $userFiles = UserFile::whereIn('id', $this->quickSelectedFiles)
                                ->where('user_id', Auth::id())
                                ->get();

            if ($userFiles->isEmpty()) {
                session()->flash('error', 'Không tìm thấy file nào được chọn.');
                return;
            }

            // Build file list to be consistent with normal stream updates
            $fileList = $userFiles->map(function ($file) {
                return [
                    'file_id' => $file->id,
                    'filename' => $file->original_name,
                    'path' => $file->disk === 'google_drive' ? $file->google_drive_file_id : $file->path,
                    'disk' => $file->disk,
                    'size' => $file->size,
                ];
            })->toArray();

            // Handle scheduling
            $enableSchedule = $this->quickEnableSchedule;
            $scheduledAt = null;
            $scheduledEnd = null;

            if ($enableSchedule && !empty($this->quickScheduledAt)) {
                $scheduledAt = Carbon::parse($this->quickScheduledAt);
                if (!empty($this->quickScheduledEnd)) {
                    $scheduledEnd = Carbon::parse($this->quickScheduledEnd);
                }
            }

            // Determine RTMP URLs
            $rtmpUrl = $this->quickPlatform === 'custom' ? $this->quickRtmpUrl : $this->getPlatformUrl($this->quickPlatform);
            $backupRtmpUrl = $this->quickPlatform === 'youtube' ? 'rtmp://b.rtmp.youtube.com/live2' : null;

            // Update stream
            $this->editingStream->update([
                'title' => $this->quickTitle,
                'description' => $this->quickDescription,
                'video_source_path' => $fileList,
                'rtmp_url' => $rtmpUrl,
                'rtmp_backup_url' => $backupRtmpUrl,
                'stream_key' => $this->quickStreamKey,
                'loop' => $this->quickLoop,
                'playlist_order' => 'sequential',
                'scheduled_at' => $scheduledAt,
                'scheduled_end' => $scheduledEnd,
                'enable_schedule' => $enableSchedule,
                // Quick stream auto-delete based on user choice
                'is_quick_stream' => true,
                'auto_delete_from_cdn' => $this->quickAutoDelete,
            ]);

            // Mark files for auto-deletion (based on user choice)
            $userFiles->each(function ($file) {
                $file->update([
                    'auto_delete_after_stream' => $this->quickAutoDelete,
                    'scheduled_deletion_at' => $this->quickAutoDelete ? now()->addDays(1) : null
                ]);
            });

            // If stream is running, dispatch update job
            if ($this->editingStream->status === 'STREAMING') {
                \App\Jobs\UpdateMultistreamJob::dispatch($this->editingStream);
                session()->flash('success', 'Quick Stream đang chạy đã được cập nhật! Thay đổi sẽ có hiệu lực trong vài giây.');
            } else {
                session()->flash('success', 'Quick Stream đã được cập nhật thành công!');
            }

            $this->showQuickStreamModal = false;
            $this->resetQuickStreamForm();

            Log::info('✅ [QuickStream] Updated successfully', [
                'stream_id' => $this->editingStream->id,
                'title' => $this->quickTitle
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to update Quick Stream', [
                'stream_id' => $this->editingStream->id ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            session()->flash('error', 'Có lỗi xảy ra khi cập nhật Quick Stream: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $query = $this->getStreamsQuery();
        $streams = $query->paginate($this->streamsPerPage);

        // Load files into the public property for the modals to use.
        $this->userFiles = $this->getUserFiles();

        return view('livewire.user-stream-manager', [
            'streams' => $streams,
        ])->layout($this->getLayoutName());
    }
}
