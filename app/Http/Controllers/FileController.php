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
        $files = $user->files()->latest()->get();

        // Calculate storage usage and limits
        $storageUsage = $user->files()->sum('size');

        // Admin has unlimited storage, regular users follow package limits
        if ($user->hasRole('admin')) {
            $storageLimit = null; // Unlimited for admin
            $canUpload = true;
            $maxFileSize = 10 * 1024 * 1024 * 1024; // 10GB for admin
        } else {
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
            $file = $user->files()->find($request->file_id);
            
            if (!$file) {
                return response()->json(['error' => 'File không tồn tại.'], 404);
            }
            
            $fileName = $file->original_name;

            // Delete from Bunny.net if exists
            if ($file->disk === 'bunny_cdn' && $file->path) {
                $bunnyService = app(\App\Services\BunnyStorageService::class);
                $result = $bunnyService->deleteFile($file->path);
                if (!$result['success']) {
                    \Log::warning('Failed to delete file from Bunny.net: ' . ($result['error'] ?? 'Unknown error'));
                }
            }
            
            // Delete database record
            $file->delete();
            
            return response()->json([
                'success' => true,
                'message' => "File '{$fileName}' đã được xóa thành công!"
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Delete file error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Lỗi khi xóa file: ' . $e->getMessage()
            ], 500);
        }
    }
}
