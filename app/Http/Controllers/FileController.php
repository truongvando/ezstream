<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\UserFile;
use App\Http\Requests\FileDeleteRequest;
use App\Http\Responses\ApiResponse;
use App\Services\FileDeleteService;
use Illuminate\Support\Facades\Log;

class FileController extends Controller
{
    protected FileDeleteService $fileDeleteService;

    public function __construct(FileDeleteService $fileDeleteService)
    {
        $this->fileDeleteService = $fileDeleteService;
    }

    public function index()
    {
        $user = Auth::user();

        // Admin sees all files with pagination, regular users see only their files
        if ($user->hasRole('admin')) {
            $files = UserFile::with('user')->latest()->paginate(20);
            $storageUsage = UserFile::sum('size');
            $storageLimit = null; // Unlimited for admin
            $canUpload = true;
            $maxFileSize = 10 * 1024 * 1024 * 1024; // 10GB for admin
        } else {
            $files = $user->files()->latest()->paginate(20);
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

    /**
     * Delete a single file or multiple files.
     */
    public function delete(FileDeleteRequest $request)
    {
        try {
            Log::info('🗑️ [FileController] Delete request received', [
                'user_id' => Auth::id(),
                'is_bulk' => $request->isBulkDeletion(),
                'request_data' => $request->only(['file_id', 'bulk_ids'])
            ]);

            $files = $request->getFilesToDelete();

            if ($files->isEmpty()) {
                return ApiResponse::notFound('Không tìm thấy file để xóa');
            }

            // Use sync deletion for immediate UI update
            $result = $this->fileDeleteService->deleteFiles($files, false);

            if ($result['success']) {
                return ApiResponse::success([
                    'deleted_count' => $result['successful'],
                    'failed_count' => $result['failed']
                ], $result['message']);
            } else {
                return ApiResponse::error($result['message'], 500, $result['errors'] ?? null);
            }

        } catch (\Exception $e) {
            Log::error('❌ [FileController] Delete operation failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ApiResponse::serverError('Lỗi khi xóa file: ' . $e->getMessage());
        }
    }

    /**
     * Get file statistics for the current user.
     */
    public function stats()
    {
        try {
            $user = Auth::user();

            if ($user->hasRole('admin')) {
                $stats = [
                    'total_files' => UserFile::count(),
                    'total_size' => UserFile::sum('size'),
                    'storage_limit' => null,
                    'can_upload' => true,
                    'users_count' => UserFile::distinct('user_id')->count('user_id')
                ];
            } else {
                $userFiles = $user->files();
                $package = $user->currentPackage();
                $storageLimit = $package ? $package->storage_limit_gb * 1024 * 1024 * 1024 : 5 * 1024 * 1024 * 1024;
                $totalSize = $userFiles->sum('size');

                $stats = [
                    'total_files' => $userFiles->count(),
                    'total_size' => $totalSize,
                    'storage_limit' => $storageLimit,
                    'can_upload' => $totalSize < $storageLimit,
                    'usage_percentage' => round(($totalSize / $storageLimit) * 100, 2)
                ];
            }

            return ApiResponse::success($stats, 'Thống kê file');

        } catch (\Exception $e) {
            Log::error('❌ [FileController] Stats operation failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return ApiResponse::serverError('Lỗi khi lấy thống kê');
        }
    }
}
