<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserFile;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SecureDownloadController extends Controller
{
    public function download(Request $request, string $token)
    {
        try {
            // Validate download token
            $fileId = cache("download_token_{$token}");
            if (!$fileId) {
                Log::warning("Invalid or expired download token", ['token' => $token]);
                return response()->json(['error' => 'Invalid or expired token'], 403);
            }

            // Get file record
            $userFile = UserFile::find($fileId);
            if (!$userFile) {
                Log::warning("File not found for download", ['file_id' => $fileId]);
                return response()->json(['error' => 'File not found'], 404);
            }

            Log::info("Secure download initiated", [
                'file_id' => $fileId,
                'filename' => $userFile->original_name,
                'disk' => $userFile->disk
            ]);

            // Handle different storage types
            if ($userFile->disk === 'google_drive') {
                return $this->downloadFromGoogleDrive($userFile);
            } else {
                return $this->downloadFromLocal($userFile);
            }

        } catch (\Exception $e) {
            Log::error("Secure download error", [
                'token' => $token,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Download failed'], 500);
        }
    }

    protected function downloadFromGoogleDrive(UserFile $userFile)
    {
        $googleDriveService = app(GoogleDriveService::class);
        $response = $googleDriveService->getDirectDownloadLink($userFile->google_drive_file_id);
        
        if (!$response['success']) {
            throw new \Exception('Cannot get Google Drive download link: ' . ($response['error'] ?? 'Unknown error'));
        }

        // Return redirect to direct download URL
        return redirect($response['download_link']);
    }

    protected function downloadFromLocal(UserFile $userFile)
    {
        $filePath = Storage::disk('local')->path($userFile->path);
        
        if (!file_exists($filePath)) {
            throw new \Exception('Local file not found');
        }

        return response()->download($filePath, $userFile->original_name);
    }
}
