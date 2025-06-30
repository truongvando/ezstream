<?php

namespace App\Services;

use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use Illuminate\Support\Facades\Log;

class VideoValidationService
{
    protected $ffmpeg;
    protected $ffprobe;

    public function __construct()
    {
        try {
            // Cấu hình FFmpeg. Đường dẫn có thể cần thay đổi tùy thuộc vào môi trường server.
            $this->ffprobe = FFProbe::create([
                'ffmpeg.binaries' => env('FFMPEG_PATH', '/usr/bin/ffmpeg'),
                'ffprobe.binaries' => env('FFPROBE_PATH', '/usr/bin/ffprobe'),
            ]);
        } catch (\Exception $e) {
            Log::error('FFMpeg/FFProbe could not be initialized. Please check paths.', ['error' => $e->getMessage()]);
            $this->ffprobe = null;
        }
    }

    /**
     * Validate video resolution.
     *
     * @param string $filePath Path to the temporary video file.
     * @param int $maxWidth The maximum allowed width.
     * @param int $maxHeight The maximum allowed height.
     * @return array An array containing 'valid' (boolean) and 'reason' (string).
     */
    public function validateResolution(string $filePath, int $maxWidth, int $maxHeight): array
    {
        if (!$this->ffprobe) {
            // Nếu FFprobe không được cài đặt, tạm thời bỏ qua kiểm tra phía server
            // và log lại một cảnh báo.
            Log::warning('FFProbe is not available. Skipping server-side video resolution validation.');
            return ['valid' => true, 'reason' => 'FFProbe not available'];
        }

        try {
            if (!$this->ffprobe->isValid($filePath)) {
                return ['valid' => false, 'reason' => 'Invalid video file format.'];
            }

            $videoStream = $this->ffprobe
                ->streams($filePath) // Lấy tất cả stream
                ->videos()           // Chỉ lọc video stream
                ->first();            // Lấy stream đầu tiên

            if (!$videoStream) {
                return ['valid' => false, 'reason' => 'No video stream found in the file.'];
            }
            
            $dimensions = $videoStream->getDimensions();
            $width = $dimensions->getWidth();
            $height = $dimensions->getHeight();
            
            Log::info('Server-side validation', [
                'file' => $filePath,
                'detected_w' => $width,
                'detected_h' => $height,
                'max_w' => $maxWidth,
                'max_h' => $maxHeight
            ]);

            if ($width > $maxWidth || $height > $maxHeight) {
                return [
                    'valid' => false,
                    'reason' => "Video resolution ({$width}x{$height}) exceeds the allowed limit ({$maxWidth}x{$maxHeight})."
                ];
            }

            return ['valid' => true, 'reason' => 'Resolution is within limits.'];

        } catch (\Exception $e) {
            Log::error('Error during video validation', ['error' => $e->getMessage()]);
            return ['valid' => false, 'reason' => 'Could not analyze video file.'];
        }
    }
} 