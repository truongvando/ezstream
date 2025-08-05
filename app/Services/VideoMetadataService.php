<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class VideoMetadataService
{
    /**
     * Get video metadata using FFprobe
     */
    public function getVideoMetadata($filePath)
    {
        try {
            // Check if file exists
            if (!file_exists($filePath)) {
                throw new Exception("File not found: {$filePath}");
            }

            // Try to get metadata using getID3 library first (if available)
            if (class_exists('\getID3')) {
                return $this->getMetadataWithGetID3($filePath);
            }

            // Fallback to FFprobe if available
            if ($this->isFFprobeAvailable()) {
                return $this->getMetadataWithFFprobe($filePath);
            }

            // Fallback to basic file info
            return $this->getBasicFileInfo($filePath);

        } catch (Exception $e) {
            Log::error('Video metadata extraction failed', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);

            // Return basic info on error
            return $this->getBasicFileInfo($filePath);
        }
    }

    /**
     * Get metadata using getID3 library
     */
    private function getMetadataWithGetID3($filePath)
    {
        $getID3 = new \getID3();
        $fileInfo = $getID3->analyze($filePath);

        $metadata = [
            'width' => $fileInfo['video']['resolution_x'] ?? null,
            'height' => $fileInfo['video']['resolution_y'] ?? null,
            'duration' => $fileInfo['playtime_seconds'] ?? null,
            'bitrate' => $fileInfo['bitrate'] ?? null,
            'codec' => $fileInfo['video']['codec'] ?? null,
            'fps' => $fileInfo['video']['frame_rate'] ?? null,
            'filesize' => $fileInfo['filesize'] ?? filesize($filePath),
            'format' => $fileInfo['fileformat'] ?? null
        ];

        return $metadata;
    }

    /**
     * Get metadata using FFprobe
     */
    private function getMetadataWithFFprobe($filePath)
    {
        $command = sprintf(
            'ffprobe -v quiet -print_format json -show_format -show_streams "%s"',
            escapeshellarg($filePath)
        );

        $output = shell_exec($command);
        $data = json_decode($output, true);

        if (!$data) {
            throw new Exception('FFprobe failed to parse video');
        }

        // Find video stream
        $videoStream = null;
        foreach ($data['streams'] as $stream) {
            if ($stream['codec_type'] === 'video') {
                $videoStream = $stream;
                break;
            }
        }

        if (!$videoStream) {
            throw new Exception('No video stream found');
        }

        return [
            'width' => $videoStream['width'] ?? null,
            'height' => $videoStream['height'] ?? null,
            'duration' => $data['format']['duration'] ?? null,
            'bitrate' => $data['format']['bit_rate'] ?? null,
            'codec' => $videoStream['codec_name'] ?? null,
            'fps' => $this->parseFPS($videoStream['r_frame_rate'] ?? null),
            'filesize' => $data['format']['size'] ?? filesize($filePath),
            'format' => $data['format']['format_name'] ?? null
        ];
    }

    /**
     * Get basic file info as fallback
     */
    private function getBasicFileInfo($filePath)
    {
        return [
            'width' => null,
            'height' => null,
            'duration' => null,
            'bitrate' => null,
            'codec' => null,
            'fps' => null,
            'filesize' => filesize($filePath),
            'format' => pathinfo($filePath, PATHINFO_EXTENSION)
        ];
    }

    /**
     * Check if FFprobe is available
     */
    private function isFFprobeAvailable()
    {
        $output = shell_exec('ffprobe -version 2>&1');
        return strpos($output, 'ffprobe version') !== false;
    }

    /**
     * Parse FPS from fraction format
     */
    private function parseFPS($fpsString)
    {
        if (!$fpsString || $fpsString === '0/0') {
            return null;
        }

        if (strpos($fpsString, '/') !== false) {
            list($num, $den) = explode('/', $fpsString);
            return $den > 0 ? round($num / $den, 2) : null;
        }

        return (float) $fpsString;
    }

    /**
     * Validate video against package limits
     */
    public function validateVideoLimits($metadata, $package)
    {
        $errors = [];

        // Check resolution limits
        if ($package->max_video_width && $package->max_video_height) {
            if ($metadata['width'] && $metadata['height']) {
                if ($metadata['width'] > $package->max_video_width || 
                    $metadata['height'] > $package->max_video_height) {
                    
                    $currentRes = $metadata['width'] . 'x' . $metadata['height'];
                    $maxRes = $package->max_video_width . 'x' . $package->max_video_height;
                    
                    $errors[] = [
                        'type' => 'resolution',
                        'message' => "Video resolution {$currentRes} vượt quá giới hạn {$maxRes}",
                        'current' => $currentRes,
                        'limit' => $maxRes
                    ];
                }
            }
        }

        // Check duration limits (if any)
        if ($package->max_video_duration && $metadata['duration']) {
            if ($metadata['duration'] > $package->max_video_duration) {
                $errors[] = [
                    'type' => 'duration',
                    'message' => "Video dài " . gmdate('H:i:s', $metadata['duration']) . " vượt quá giới hạn " . gmdate('H:i:s', $package->max_video_duration),
                    'current' => $metadata['duration'],
                    'limit' => $package->max_video_duration
                ];
            }
        }

        return $errors;
    }

    /**
     * Get resolution name for display
     */
    public function getResolutionName($width, $height)
    {
        if ($width >= 3840 && $height >= 2160) return '4K UHD';
        if ($width >= 2560 && $height >= 1440) return '2K QHD';
        if ($width >= 1920 && $height >= 1080) return 'Full HD 1080p';
        if ($width >= 1280 && $height >= 720) return 'HD 720p';
        if ($width >= 854 && $height >= 480) return 'SD 480p';
        return 'Low Quality';
    }
}
