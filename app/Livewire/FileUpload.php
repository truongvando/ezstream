<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\UserFile;
use App\Services\BunnyStorageService;

class FileUpload extends Component
{
    public $storageUsage = 0;
    public $storageLimit = 0;
    public $canUpload = false;
    
    // Delete Modal
    public $showDeleteModal = false;
    public $deletingFileId = null;
    public $deletingFileName = '';

    protected $listeners = [
        'refreshFiles' => '$refresh',
        'fileUploaded' => 'handleFileUploaded'
    ];

    public function mount()
    {
        $this->calculateStorage();
    }

    public function calculateStorage()
    {
        $user = Auth::user();
        $this->storageUsage = $user->files()->sum('size');
        
        if ($user->isAdmin()) {
            $this->storageLimit = PHP_INT_MAX;
            $this->canUpload = true;
        } else {
            $activeSubscription = $user->subscriptions()
                ->where('status', 'active')
                ->where('ends_at', '>', now())
                ->first();
                
            if ($activeSubscription && $activeSubscription->servicePackage) {
                $this->storageLimit = $activeSubscription->servicePackage->storage_limit_gb * 1024 * 1024 * 1024;
                $this->canUpload = $this->storageUsage < $this->storageLimit;
            } else {
                $this->storageLimit = 0;
                $this->canUpload = false;
            }
        }
    }

    public function handleFileUploaded($data = null)
    {
        try {
            // Debug: Log all arguments received
            \Log::info('🔍 [FileUpload] handleFileUploaded called', [
                'data' => $data,
                'all_args' => func_get_args(),
                'data_type' => gettype($data)
            ]);

            // Handle both old format (individual params) and new format (array)
            if (is_array($data)) {
                $fileName = $data['file_name'] ?? 'unknown';
                $fileId = $data['file_id'] ?? null;
                $fileSize = $data['file_size'] ?? 0;
            } else {
                // Legacy support for old format
                $fileName = $data ?? 'unknown';
                $fileId = func_get_arg(1) ?? null;
                $fileSize = func_get_arg(2) ?? 0;
            }

            \Log::info('📁 [FileUpload] Processing file upload', [
                'file_name' => $fileName,
                'file_id' => $fileId,
                'file_size' => $fileSize
            ]);

            // Recalculate storage usage
            $this->calculateStorage();

            // Force refresh the component to show new file
            $this->dispatch('$refresh');

            // Show success message
            session()->flash('message', "✅ File '{$fileName}' đã upload thành công! Danh sách đã được cập nhật.");

            \Log::info('✅ [FileUpload] File upload handled successfully');

        } catch (\Exception $e) {
            \Log::error('❌ [FileUpload] Error handling file upload: ' . $e->getMessage(), [
                'exception' => $e,
                'data' => $data
            ]);
            session()->flash('error', 'Có lỗi xảy ra khi cập nhật danh sách file.');
        }
    }

    public function confirmDelete($fileId)
    {
        $file = Auth::user()->files()->find($fileId);
        if ($file) {
            $this->deletingFileId = $fileId;
            $this->deletingFileName = $file->original_name;
            $this->showDeleteModal = true;
        }
    }

    public function deleteFile()
    {
        try {
            $user = Auth::user();
            $file = $user->files()->find($this->deletingFileId);
            
            if (!$file) {
                session()->flash('error', 'File không tồn tại.');
                return;
            }
            
            $fileName = $file->original_name;

            // Delete from Bunny.net if exists
            if ($file->disk === 'bunny_cdn' && $file->path) {
                $bunnyService = app(BunnyStorageService::class);
                $result = $bunnyService->deleteFile($file->path);
                if (!$result['success']) {
                    \Log::warning('Failed to delete file from Bunny.net: ' . ($result['error'] ?? 'Unknown error'));
                }
            }
            
            // Delete database record
            $file->delete();
            
            $this->calculateStorage();
            $this->showDeleteModal = false;
            
            session()->flash('message', "File '{$fileName}' đã được xóa thành công!");
            
        } catch (\Exception $e) {
            \Log::error('Delete file error: ' . $e->getMessage());
            session()->flash('error', 'Lỗi khi xóa file: ' . $e->getMessage());
        }
    }


    
    private function guessMimeType($filename)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
            'wmv' => 'video/x-ms-wmv',
            'flv' => 'video/x-flv',
            'webm' => 'video/webm',
            'mkv' => 'video/x-matroska',
        ];
        
        return $mimeTypes[$extension] ?? 'video/mp4';
    }

    public function render()
    {
        $user = Auth::user();
        $files = $user->files()->latest()->get();

        return view('livewire.file-upload', [
            'files' => $files,
            'user' => $user,
        ])->layout('layouts.sidebar');
    }
}
