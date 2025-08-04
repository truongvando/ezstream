<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\UserFile;

class FileController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Admin sees all files, regular users see only their files
        if ($user->hasRole('admin')) {
            $files = UserFile::with('user')->latest()->get();
            $storageUsage = UserFile::sum('size');
            $storageLimit = null; // Unlimited for admin
            $canUpload = true;
            $maxFileSize = 10 * 1024 * 1024 * 1024; // 10GB for admin
        } else {
            $files = $user->files()->latest()->get();
            $storageUsage = $user->files()->sum('size');

            // Get user's package storage limit
            $package = $user->currentPackage();
            $storageLimit = $package ? $package->storage_limit_gb * 1024 * 1024 * 1024 : 5 * 1024 * 1024 * 1024; // Default 5GB
            $canUpload = $storageUsage < $storageLimit;
            $maxFileSize = 10 * 1024 * 1024 * 1024; // 10GB for regular users
        }

        return view('files.index', [
            'files' => $files,
            'user' => $user,
            'storageUsage' => $storageUsage,
            'storageLimit' => $storageLimit,
            'canUpload' => $canUpload,
            'maxFileSize' => $maxFileSize,
            'isAdmin' => $user->hasRole('admin')
        ]);
    }

    public function delete(Request $request)
    {
        $request->validate([
            'file_id' => 'required|integer|exists:user_files,id'
        ]);

        try {
            $user = Auth::user();

            // Admin can delete any file, regular users can only delete their own files
            if ($user->hasRole('admin')) {
                $file = UserFile::find($request->file_id);
            } else {
                $file = $user->files()->find($request->file_id);
            }

            if (!$file) {
                return response()->json(['error' => 'File không tồn tại hoặc bạn không có quyền xóa file này.'], 404);
            }

            $fileName = $file->original_name;
            $fileOwner = $file->user->name ?? 'Unknown';

            // Delete from storage based on disk type
            if ($file->disk === 'bunny_cdn' && $file->path) {
                $bunnyService = app(\App\Services\BunnyStorageService::class);
                $result = $bunnyService->deleteFile($file->path);
                if (!$result['success']) {
                    \Log::warning('Failed to delete file from Bunny CDN: ' . ($result['error'] ?? 'Unknown error'));
                }
            } elseif ($file->disk === 'bunny_stream' && $file->stream_video_id) {
                // Delete from Bunny Stream Library
                $streamService = app(\App\Services\BunnyStreamService::class);
                $result = $streamService->deleteVideo($file->stream_video_id);
                if (!$result['success']) {
                    \Log::warning('Failed to delete video from Bunny Stream: ' . ($result['error'] ?? 'Unknown error'));
                }
            } elseif ($file->disk === 'local' && $file->path) {
                // Delete from local storage
                $localPath = storage_path('app/files/' . $file->path);
                if (file_exists($localPath)) {
                    unlink($localPath);
                }
            }

            // Delete database record
            $file->delete();

            $message = $user->hasRole('admin')
                ? "File '{$fileName}' của user '{$fileOwner}' đã được xóa thành công!"
                : "File '{$fileName}' đã được xóa thành công!";

            return response()->json([
                'success' => true,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            \Log::error('Delete file error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Lỗi khi xóa file: ' . $e->getMessage()
            ], 500);
        }
    }
}
