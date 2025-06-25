<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\UserFile;
use App\Services\GoogleDriveService;

class FileManager extends Component
{
    // Bỏ WithFileUploads để tránh memory issue với file lớn

    public $storageUsage = 0;
    public $storageLimit = 0;
    public $canUpload = false;
    
    // Upload status
    public $uploadMessage = '';
    public $uploadStatus = ''; // success, error, uploading

    /**
     * Listeners for Livewire events
     */
    protected $listeners = [
        'refreshFiles' => '$refresh'
    ];

    public function mount()
    {
        $this->calculateStorage();
    }

    public function calculateStorage()
    {
        $user = Auth::user();
        $this->storageUsage = $user->files()->sum('size');
        
        // Get storage limit from active subscription
        if ($user->isAdmin()) {
            $this->storageLimit = PHP_INT_MAX; // Unlimited for admin
            $this->canUpload = true;
        } else {
            // Get active subscription
            $activeSubscription = $user->subscriptions()
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->first();
                
            if ($activeSubscription && $activeSubscription->servicePackage) {
                $this->storageLimit = $activeSubscription->servicePackage->storage_limit * 1024 * 1024 * 1024; // Convert GB to bytes
                $this->canUpload = $this->storageUsage < $this->storageLimit;
            } else {
                $this->storageLimit = 0;
                $this->canUpload = false;
            }
        }
    }

    /**
     * Handle upload success từ form submit
     */
    public function uploadSuccess($fileName, $fileId, $fileSize)
    {
        $this->uploadStatus = 'success';
        $this->uploadMessage = "✅ File '{$fileName}' đã upload thành công!";
        $this->calculateStorage();
        $this->dispatch('file-uploaded');
    }

    /**
     * Handle upload error từ form submit  
     */
    public function uploadError($message)
    {
        $this->uploadStatus = 'error';
        $this->uploadMessage = "❌ Lỗi upload: {$message}";
    }

    /**
     * Clear upload status
     */
    public function clearStatus()
    {
        $this->uploadStatus = '';
        $this->uploadMessage = '';
    }
    
    /**
     * Delete a file
     */
    public function deleteFile($fileId)
    {
        try {
            $user = Auth::user();
            $file = $user->files()->find($fileId);
            
            if (!$file) {
                $this->dispatch('show-error', message: 'File không tồn tại.');
                return;
            }
            
            // Delete from Google Drive if exists
            if ($file->google_drive_file_id) {
                try {
                    $googleDriveService = app(GoogleDriveService::class);
                    $googleDriveService->deleteFile($file->google_drive_file_id);
                } catch (\Exception $e) {
                    \Log::warning('Failed to delete file from Google Drive: ' . $e->getMessage());
                }
            }
            
            // Delete from local storage if exists
            if ($file->path && Storage::exists($file->path)) {
                Storage::delete($file->path);
            }
            
            // Delete database record
            $fileName = $file->original_name;
            $file->delete();
            
            // Recalculate storage
            $this->calculateStorage();
            
            $this->dispatch('show-success', message: "File '{$fileName}' đã được xóa thành công!");
            
        } catch (\Exception $e) {
            \Log::error('Delete file error: ' . $e->getMessage());
            $this->dispatch('show-error', message: 'Lỗi khi xóa file: ' . $e->getMessage());
        }
    }

    /**
     * Initialize chunked upload for large files
     */
    public function initChunkedUpload($fileName, $fileSize, $fileType)
    {
        $user = Auth::user();
        
        // Check permissions
        if (!$user->isAdmin() && !$this->canUpload) {
            return ['error' => 'Bạn không có quyền upload file.'];
        }
        
        // Check storage limit for non-admin users
        if (!$user->isAdmin() && ($this->storageUsage + $fileSize > $this->storageLimit)) {
            return ['error' => 'Vượt quá giới hạn lưu trữ.'];
        }
        
        $this->uploadId = uniqid('upload_');
        $this->isChunkedUpload = true;
        $this->chunkIndex = 0;
        $this->totalChunks = 0;
        $this->uploadProgress = 0;
        
        // Create temp directory for chunks
        $tempDir = storage_path('app/temp_uploads/' . $this->uploadId);
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        return [
            'uploadId' => $this->uploadId,
            'chunkSize' => 5 * 1024 * 1024, // 5MB chunks
        ];
    }
    
    /**
     * Handle chunk upload
     */
    public function uploadChunk($uploadId, $chunkIndex, $chunkData, $isLastChunk = false)
    {
        $tempDir = storage_path('app/temp_uploads/' . $uploadId);
        $chunkFile = $tempDir . '/chunk_' . $chunkIndex;
        
        // Save chunk
        file_put_contents($chunkFile, base64_decode($chunkData));
        
        $this->chunkIndex = $chunkIndex;
        $this->uploadProgress = $isLastChunk ? 100 : (($chunkIndex + 1) / $this->totalChunks) * 100;
        
        if ($isLastChunk) {
            return $this->finalizeChunkedUpload($uploadId);
        }
        
        return ['status' => 'chunk_uploaded', 'progress' => $this->uploadProgress];
    }
    
    /**
     * Combine all chunks into final file
     */
    private function finalizeChunkedUpload($uploadId)
    {
        $user = Auth::user();
        $tempDir = storage_path('app/temp_uploads/' . $uploadId);
        
        // Get all chunk files
        $chunkFiles = glob($tempDir . '/chunk_*');
        sort($chunkFiles, SORT_NATURAL);
        
        // Create final file
        $finalFileName = uniqid() . '_' . time() . '.tmp';
        $finalPath = storage_path('app/user_uploads/' . $finalFileName);
        
        $finalFile = fopen($finalPath, 'wb');
        
        foreach ($chunkFiles as $chunkFile) {
            $chunk = fopen($chunkFile, 'rb');
            stream_copy_to_stream($chunk, $finalFile);
            fclose($chunk);
            unlink($chunkFile); // Delete chunk after combining
        }
        
        fclose($finalFile);
        rmdir($tempDir); // Remove temp directory
        
        // Create database record
        $fileSize = filesize($finalPath);
        $originalName = session('chunked_upload_original_name', 'uploaded_file.mp4');
        
        $userFile = $user->files()->create([
            'disk' => 'local',
            'path' => 'user_uploads/' . $finalFileName,
            'original_name' => $originalName,
            'mime_type' => 'video/mp4',
            'size' => $fileSize,
            'status' => 'PENDING_TRANSFER',
        ]);
        
        // Dispatch transfer job
        TransferVideoToVpsJob::dispatch($userFile);
        
        $this->isChunkedUpload = false;
        $this->uploadProgress = 0;
        $this->calculateStorage();
        
        return [
            'status' => 'upload_complete',
            'message' => 'File đã được upload thành công!',
            'fileId' => $userFile->id
        ];
    }

    public function render()
    {
        $files = Auth::user()->files()->latest()->get();

        return view('livewire.file-manager', [
            'files' => $files
        ])->layout('layouts.app');
    }
}
