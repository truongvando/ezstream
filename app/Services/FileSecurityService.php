<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class FileSecurityService
{
    // Video file signatures (magic bytes)
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

    // Dangerous file signatures
    private const DANGEROUS_SIGNATURES = [
        'exe' => ["\x4D\x5A"], // MZ header
        'php' => ["<?php", "<?=", "<script"],
        'js' => ["<script", "javascript:"],
        'html' => ["<html", "<!DOCTYPE", "<script"],
        'bat' => ["@echo", "cmd.exe"],
        'cmd' => ["@echo", "cmd.exe"],
        'sh' => ["#!/bin/sh", "#!/bin/bash"],
        'py' => ["#!/usr/bin/python", "import os", "import sys"],
        'jar' => ["PK\x03\x04"],
        'zip' => ["PK\x03\x04"],
    ];

    // Malicious patterns
    private const MALICIOUS_PATTERNS = [
        '/eval\s*\(/i',
        '/exec\s*\(/i',
        '/system\s*\(/i',
        '/shell_exec\s*\(/i',
        '/passthru\s*\(/i',
        '/file_get_contents\s*\(/i',
        '/file_put_contents\s*\(/i',
        '/fopen\s*\(/i',
        '/fwrite\s*\(/i',
        '/base64_decode\s*\(/i',
        '/<\?php/i',
        '/<script/i',
        '/javascript:/i',
        '/vbscript:/i',
        '/onload\s*=/i',
        '/onerror\s*=/i',
        '/\$_GET/i',
        '/\$_POST/i',
        '/\$_REQUEST/i',
        '/\$_COOKIE/i',
        '/\$_SERVER/i',
    ];

    /**
     * Validate if file is a legitimate video file
     */
    public function validateVideoFile(string $filePath, string $expectedExtension): array
    {
        if (!file_exists($filePath)) {
            return ['valid' => false, 'reason' => 'File not found'];
        }

        // Get file size
        $fileSize = filesize($filePath);
        if ($fileSize === false || $fileSize < 1024) {
            return ['valid' => false, 'reason' => 'File too small for video'];
        }

        // Read file header for analysis
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return ['valid' => false, 'reason' => 'Cannot read file'];
        }

        $header = fread($handle, 4096); // Read first 4KB
        fclose($handle);

        // Check for dangerous content first
        if ($this->containsDangerousContent($header)) {
            return ['valid' => false, 'reason' => 'Dangerous content detected'];
        }

        // Validate video signature
        $signatureResult = $this->validateVideoSignature($header, $expectedExtension);
        if (!$signatureResult['valid']) {
            return $signatureResult;
        }

        // Deep content analysis
        $deepAnalysis = $this->performDeepAnalysis($filePath);
        if (!$deepAnalysis['valid']) {
            return $deepAnalysis;
        }

        return ['valid' => true, 'reason' => 'Valid video file'];
    }

    /**
     * Check for dangerous content in file data
     */
    public function containsDangerousContent(string $content): bool
    {
        // Check for dangerous file signatures
        foreach (self::DANGEROUS_SIGNATURES as $type => $signatures) {
            foreach ($signatures as $signature) {
                if (strpos($content, $signature) !== false) {
                    Log::alert("Dangerous signature detected: $type");
                    return true;
                }
            }
        }

        // Check for malicious patterns
        foreach (self::MALICIOUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                Log::alert("Malicious pattern detected: $pattern");
                return true;
            }
        }

        // Check for embedded scripts or code
        if ($this->containsEmbeddedCode($content)) {
            return true;
        }

        return false;
    }

    /**
     * Validate video file signature
     */
    private function validateVideoSignature(string $header, string $expectedExtension): array
    {
        if (!isset(self::VIDEO_SIGNATURES[$expectedExtension])) {
            return ['valid' => false, 'reason' => 'Unknown video format'];
        }

        $signatures = self::VIDEO_SIGNATURES[$expectedExtension];
        $validSignature = false;

        foreach ($signatures as $sig) {
            $offset = $sig['offset'];
            $signature = $sig['signature'];
            
            if (strlen($header) > $offset + strlen($signature)) {
                $fileSignature = substr($header, $offset, strlen($signature));
                if ($fileSignature === $signature) {
                    $validSignature = true;
                    break;
                }
            }
        }

        if (!$validSignature) {
            return ['valid' => false, 'reason' => 'Invalid video file signature'];
        }

        // Additional format-specific checks
        switch ($expectedExtension) {
            case 'mp4':
            case 'mov':
                if (strpos($header, 'ftyp') === false && strpos($header, 'moov') === false) {
                    return ['valid' => false, 'reason' => 'Invalid MP4/MOV structure'];
                }
                break;
                
            case 'avi':
                if (strpos($header, 'RIFF') !== 0 || strpos($header, 'AVI ') !== 8) {
                    return ['valid' => false, 'reason' => 'Invalid AVI structure'];
                }
                break;
                
            case 'mkv':
                if (substr($header, 0, 4) !== "\x1A\x45\xDF\xA3") {
                    return ['valid' => false, 'reason' => 'Invalid MKV signature'];
                }
                break;
        }

        return ['valid' => true, 'reason' => 'Valid signature'];
    }

    /**
     * Perform deep analysis of file content
     */
    private function performDeepAnalysis(string $filePath): array
    {
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return ['valid' => false, 'reason' => 'Cannot read file for analysis'];
        }

        $chunkSize = 8192; // 8KB chunks
        $totalChecked = 0;
        $maxCheck = 1024 * 1024; // Check first 1MB max

        while (!feof($handle) && $totalChecked < $maxCheck) {
            $chunk = fread($handle, $chunkSize);
            if ($chunk === false) break;

            // Check this chunk for dangerous content
            if ($this->containsDangerousContent($chunk)) {
                fclose($handle);
                return ['valid' => false, 'reason' => 'Dangerous content found in file'];
            }

            $totalChecked += strlen($chunk);
        }

        fclose($handle);
        return ['valid' => true, 'reason' => 'Deep analysis passed'];
    }

    /**
     * Check for embedded code or scripts
     */
    private function containsEmbeddedCode(string $content): bool
    {
        // Look for script tags
        if (preg_match('/<script[^>]*>/i', $content)) {
            return true;
        }

        // Look for PHP tags
        if (preg_match('/<\?(?:php|=)/i', $content)) {
            return true;
        }

        // Look for suspicious base64 patterns
        if (preg_match('/[A-Za-z0-9+\/]{100,}={0,2}/', $content)) {
            // Try to decode and check if it contains code
            $matches = [];
            preg_match_all('/[A-Za-z0-9+\/]{100,}={0,2}/', $content, $matches);
            foreach ($matches[0] as $match) {
                $decoded = @base64_decode($match);
                if ($decoded && $this->containsDangerousContent($decoded)) {
                    return true;
                }
            }
        }

        // Look for URL schemes that could be dangerous
        $dangerousSchemes = ['javascript:', 'vbscript:', 'data:', 'file:'];
        foreach ($dangerousSchemes as $scheme) {
            if (stripos($content, $scheme) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize filename
     */
    public function sanitizeFileName(string $filename): ?string
    {
        // Remove dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Remove multiple dots and dashes
        $filename = preg_replace('/\.{2,}/', '.', $filename);
        $filename = preg_replace('/_{2,}/', '_', $filename);
        
        // Check for dangerous filenames
        $dangerousNames = [
            'con', 'prn', 'aux', 'nul', 'com1', 'com2', 'com3', 'com4', 'com5', 
            'com6', 'com7', 'com8', 'com9', 'lpt1', 'lpt2', 'lpt3', 'lpt4', 'lpt5', 
            'lpt6', 'lpt7', 'lpt8', 'lpt9', '.htaccess', 'web.config', 'index.php'
        ];
        
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        if (in_array(strtolower($nameWithoutExt), $dangerousNames)) {
            return null;
        }
        
        // Check length
        if (strlen($filename) > 255 || strlen($filename) < 1) {
            return null;
        }
        
        return $filename;
    }

    /**
     * Check if file extension is allowed
     */
    public function isAllowedExtension(string $filename): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowedExtensions = ['mp4', 'mov', 'avi', 'mkv'];
        
        return in_array($extension, $allowedExtensions);
    }

    /**
     * Check if MIME type is allowed
     */
    public function isAllowedMimeType(string $mimeType): bool
    {
        $allowedMimes = [
            'video/mp4', 
            'video/quicktime', 
            'video/x-msvideo', 
            'video/x-matroska'
        ];
        
        return in_array($mimeType, $allowedMimes);
    }
} 