<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\UserFile;
use App\Jobs\TransferVideoToVpsJob;
use App\Services\FileSecurityService;

class ChunkedUploadController extends Controller
{
    private FileSecurityService $fileSecurityService;

    public function __construct(FileSecurityService $fileSecurityService)
    {
        $this->fileSecurityService = $fileSecurityService;
    }

    // Allowed video signatures (magic bytes)
    private const VIDEO_SIGNATURES = [
        'mp4' => [
            ['offset' => 4, 'signature' => 'ftyp'],
            ['offset' => 0, 'signature' => "\x00\x00\x00"],
        ],
        'mov' => [
            ['offset' => 4, 'signature' => 'ftyp'],
            ['offset' => 4, 'signature' => 'moov'],
        ],
        'avi' => [
            ['offset' => 0, 'signature' => 'RIFF'],
            ['offset' => 8, 'signature' => 'AVI '],
        ],
        'mkv' => [
            ['offset' => 0, 'signature' => "\x1A\x45\xDF\xA3"],
        ]
    ];

    // Dangerous file signatures to block
    private const DANGEROUS_SIGNATURES = [
        'exe' => ["\x4D\x5A"], // MZ header
        'php' => ["<?php", "<?="],
        'js' => ["<script"],
        'html' => ["<html", "<!DOCTYPE"],
        'bat' => ["@echo"],
        'cmd' => ["@echo"],
        'sh' => ["#!/bin/sh", "#!/bin/bash"],
        'py' => ["#!/usr/bin/python", "import "],
    ];

    /**
     * Initialize chunked upload session
     */
    public function initUpload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fileName' => 'required|string|max:255',
            'fileSize' => 'required|integer|min:1024|max:107374182400', // 1KB to 100GB
            'fileType' => 'required|string',
            'totalChunks' => 'required|integer|min:1|max:20000'
        ]);

        if ($validator->fails()) {
            Log::warning('Chunked upload init failed - validation', [
                'user_id' => Auth::id(),
                'errors' => $validator->errors(),
                'request_data' => $request->except(['_token'])
            ]);
            return response()->json(['error' => 'Invalid parameters'], 400);
        }

        $user = Auth::user();
        
        // Security: Sanitize filename
        $fileName = $this->fileSecurityService->sanitizeFileName($request->fileName);
        if (!$fileName) {
            Log::warning('Chunked upload init failed - dangerous filename', [
                'user_id' => Auth::id(),
                'original_filename' => $request->fileName,
                'ip' => $request->ip()
            ]);
            return response()->json(['error' => 'Invalid or dangerous filename'], 400);
        }

        // Security: Check file extension
        if (!$this->fileSecurityService->isAllowedExtension($fileName)) {
            Log::warning('Chunked upload init failed - invalid extension', [
                'user_id' => Auth::id(),
                'filename' => $fileName,
                'ip' => $request->ip()
            ]);
            return response()->json(['error' => 'File type not allowed. Only MP4, MOV, AVI, MKV are supported.'], 400);
        }

        // Security: Check MIME type
        if (!$this->fileSecurityService->isAllowedMimeType($request->fileType)) {
            Log::warning('Chunked upload init failed - invalid MIME', [
                'user_id' => Auth::id(),
                'filename' => $fileName,
                'mime_type' => $request->fileType,
                'ip' => $request->ip()
            ]);
            return response()->json(['error' => 'Invalid MIME type'], 400);
        }

        // Security: Rate limiting - max 5 concurrent uploads per user
        $activeUploads = glob(storage_path('app/temp_uploads/upload_' . $user->id . '_*'));
        if (count($activeUploads) >= 5) {
            Log::warning('Chunked upload init failed - too many concurrent uploads', [
                'user_id' => Auth::id(),
                'active_uploads' => count($activeUploads),
                'ip' => $request->ip()
            ]);
            return response()->json(['error' => 'Too many concurrent uploads. Please wait for current uploads to complete.'], 429);
        }

        // No storage limits for video streaming system

        // Create upload session
        $uploadId = uniqid('upload_' . $user->id . '_');
        $tempDir = storage_path('app/temp_uploads/' . $uploadId);
        
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Store upload metadata with security info
        $metadata = [
            'user_id' => $user->id,
            'file_name' => $fileName,
            'original_file_name' => $request->fileName,
            'file_size' => $request->fileSize,
            'file_type' => $request->fileType,
            'file_extension' => strtolower(pathinfo($fileName, PATHINFO_EXTENSION)),
            'total_chunks' => $request->totalChunks,
            'uploaded_chunks' => [],
            'created_at' => now()->toISOString(),
            'user_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'security_validated' => false
        ];

        file_put_contents($tempDir . '/metadata.json', json_encode($metadata));

        Log::info('Chunked upload initialized', [
            'user_id' => $user->id,
            'upload_id' => $uploadId,
            'filename' => $fileName,
            'file_size' => $request->fileSize,
            'ip' => $request->ip()
        ]);

        return response()->json([
            'uploadId' => $uploadId,
            'chunkSize' => 5 * 1024 * 1024, // 5MB chunks
            'message' => 'Upload session initialized successfully'
        ]);
    }

    /**
     * Upload a single chunk
     */
    public function uploadChunk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'uploadId' => 'required|string|max:100',
            'chunkIndex' => 'required|integer|min:0|max:19999',
            'chunk' => 'required|file' // No size limit per chunk
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid parameters'], 400);
        }

        $uploadId = $request->uploadId;
        $chunkIndex = $request->chunkIndex;
        $tempDir = storage_path('app/temp_uploads/' . $uploadId);
        
        // Check if upload session exists
        if (!file_exists($tempDir . '/metadata.json')) {
            Log::warning('Chunk upload failed - session not found', [
                'user_id' => Auth::id(),
                'upload_id' => $uploadId,
                'chunk_index' => $chunkIndex,
                'ip' => $request->ip()
            ]);
            return response()->json(['error' => 'Upload session not found'], 404);
        }

        // Load metadata
        $metadata = json_decode(file_get_contents($tempDir . '/metadata.json'), true);
        
        // Verify user ownership
        if ($metadata['user_id'] !== Auth::id()) {
            Log::warning('Chunk upload failed - unauthorized access', [
                'user_id' => Auth::id(),
                'upload_id' => $uploadId,
                'owner_id' => $metadata['user_id'],
                'ip' => $request->ip()
            ]);
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Security: Check chunk index bounds
        if ($chunkIndex >= $metadata['total_chunks']) {
            Log::warning('Chunk upload failed - invalid chunk index', [
                'user_id' => Auth::id(),
                'upload_id' => $uploadId,
                'chunk_index' => $chunkIndex,
                'total_chunks' => $metadata['total_chunks'],
                'ip' => $request->ip()
            ]);
            return response()->json(['error' => 'Invalid chunk index'], 400);
        }

        // Security: Check for duplicate chunks
        if (in_array($chunkIndex, $metadata['uploaded_chunks'])) {
            Log::warning('Chunk upload - duplicate chunk attempted', [
                'user_id' => Auth::id(),
                'upload_id' => $uploadId,
                'chunk_index' => $chunkIndex,
                'ip' => $request->ip()
            ]);
            return response()->json(['error' => 'Chunk already uploaded'], 400);
        }

        $chunkFile = $tempDir . '/chunk_' . str_pad($chunkIndex, 6, '0', STR_PAD_LEFT);
        $uploadedFile = $request->file('chunk');

        // Security: Validate chunk content
        $chunkContent = file_get_contents($uploadedFile->getPathname());
        
        // Check for dangerous content in chunk
        if ($this->fileSecurityService->containsDangerousContent($chunkContent)) {
            Log::alert('SECURITY ALERT - Dangerous content detected in chunk', [
                'user_id' => Auth::id(),
                'upload_id' => $uploadId,
                'chunk_index' => $chunkIndex,
                'user_ip' => $request->ip(),
                'filename' => $metadata['file_name']
            ]);
            
            // Cleanup and block
            $this->cleanupTempFiles($tempDir);
            return response()->json(['error' => 'Security violation detected. Upload blocked and reported.'], 403);
        }

        // Save chunk
        $uploadedFile->move($tempDir, basename($chunkFile));

        // Update metadata
        $metadata['uploaded_chunks'][] = $chunkIndex;
        $metadata['uploaded_chunks'] = array_unique($metadata['uploaded_chunks']);
        sort($metadata['uploaded_chunks']);
        $metadata['last_chunk_at'] = now()->toISOString();
        
        file_put_contents($tempDir . '/metadata.json', json_encode($metadata));

        $progress = (count($metadata['uploaded_chunks']) / $metadata['total_chunks']) * 100;

        return response()->json([
            'chunkIndex' => $chunkIndex,
            'progress' => round($progress, 2),
            'uploadedChunks' => count($metadata['uploaded_chunks']),
            'totalChunks' => $metadata['total_chunks']
        ]);
    }

    /**
     * Complete the upload by combining chunks
     */
    public function completeUpload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'uploadId' => 'required|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid parameters'], 400);
        }

        $uploadId = $request->uploadId;
        $tempDir = storage_path('app/temp_uploads/' . $uploadId);
        
        // Check if upload session exists
        if (!file_exists($tempDir . '/metadata.json')) {
            return response()->json(['error' => 'Upload session not found'], 404);
        }

        // Load metadata
        $metadata = json_decode(file_get_contents($tempDir . '/metadata.json'), true);
        
        // Verify user ownership
        if ($metadata['user_id'] !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Check if all chunks are uploaded
        if (count($metadata['uploaded_chunks']) !== $metadata['total_chunks']) {
            return response()->json(['error' => 'Not all chunks uploaded'], 400);
        }

        // Combine chunks
        $finalFileName = uniqid() . '_' . time() . '_' . $metadata['file_name'];
        $finalPath = storage_path('app/user_uploads/' . $finalFileName);
        
        $finalFile = fopen($finalPath, 'wb');
        
        for ($i = 0; $i < $metadata['total_chunks']; $i++) {
            $chunkFile = $tempDir . '/chunk_' . str_pad($i, 6, '0', STR_PAD_LEFT);
            
            if (!file_exists($chunkFile)) {
                fclose($finalFile);
                unlink($finalPath);
                return response()->json(['error' => "Chunk $i not found"], 400);
            }
            
            $chunk = fopen($chunkFile, 'rb');
            stream_copy_to_stream($chunk, $finalFile);
            fclose($chunk);
        }
        
        fclose($finalFile);

        // Verify file size
        $actualSize = filesize($finalPath);
        if ($actualSize !== $metadata['file_size']) {
            unlink($finalPath);
            Log::warning('Upload failed - file size mismatch', [
                'user_id' => Auth::id(),
                'upload_id' => $uploadId,
                'expected_size' => $metadata['file_size'],
                'actual_size' => $actualSize,
                'ip' => $request->ip()
            ]);
            return response()->json(['error' => 'File size mismatch'], 400);
        }

        // CRITICAL: Validate final file content using FileSecurityService
        $validationResult = $this->fileSecurityService->validateVideoFile($finalPath, $metadata['file_extension']);
        if (!$validationResult['valid']) {
            unlink($finalPath);
            Log::alert('SECURITY ALERT - Invalid video file detected', [
                'user_id' => Auth::id(),
                'upload_id' => $uploadId,
                'filename' => $metadata['file_name'],
                'reason' => $validationResult['reason'],
                'user_ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            return response()->json(['error' => 'File validation failed: ' . $validationResult['reason']], 403);
        }

        // Create database record
        $user = Auth::user();
        $userFile = $user->files()->create([
            'disk' => 'local',
            'path' => 'user_uploads/' . $finalFileName,
            'original_name' => $metadata['file_name'],
            'mime_type' => $metadata['file_type'],
            'size' => $actualSize,
            'status' => 'PENDING_TRANSFER',
        ]);

        // Clean up temp files
        $this->cleanupTempFiles($tempDir);

        // Dispatch transfer job
        TransferVideoToVpsJob::dispatch($userFile);

        Log::info('Chunked upload completed successfully', [
            'user_id' => Auth::id(),
            'upload_id' => $uploadId,
            'file_id' => $userFile->id,
            'filename' => $metadata['file_name'],
            'size' => $actualSize,
            'ip' => $request->ip()
        ]);

        return response()->json([
            'fileId' => $userFile->id,
            'fileName' => $userFile->original_name,
            'fileSize' => $userFile->size,
            'message' => 'File uploaded successfully and is being transferred to server'
        ]);
    }

    /**
     * Cancel upload and cleanup
     */
    public function cancelUpload($uploadId)
    {
        $tempDir = storage_path('app/temp_uploads/' . $uploadId);
        
        if (file_exists($tempDir . '/metadata.json')) {
            $metadata = json_decode(file_get_contents($tempDir . '/metadata.json'), true);
            
            // Verify user ownership
            if ($metadata['user_id'] !== Auth::id()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        $this->cleanupTempFiles($tempDir);

        Log::info('Chunked upload cancelled', [
            'user_id' => Auth::id(),
            'upload_id' => $uploadId
        ]);

        return response()->json(['message' => 'Upload cancelled successfully']);
    }

    /**
     * Clean up temporary files
     */
    private function cleanupTempFiles($tempDir)
    {
        if (file_exists($tempDir)) {
            $files = glob($tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($tempDir);
        }
    }
}
