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

    public function handleFileUploaded($file_name, $file_id, $file_size)
    {
        $this->calculateStorage();
        session()->flash('message', "✅ File '{$file_name}' đã upload thành công!");
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
