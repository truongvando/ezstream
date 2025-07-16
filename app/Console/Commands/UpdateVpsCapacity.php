<?php

namespace App\Console\Commands;

use App\Models\VpsServer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class UpdateVpsCapacity extends Command
{
    protected $signature = 'vps:update-capacity';
    protected $description = 'Dynamically calculates and updates the max_concurrent_streams for each VPS based on real-time resource usage.';

    // Ngưỡng an toàn
    private const MAX_CPU_TARGET = 80.0; // %
    private const MAX_RAM_TARGET = 85.0; // %
    private const BASE_RAM_USAGE = 20.0; // % RAM cơ bản mà OS sử dụng
    private const MIN_STREAMS_FOR_AVG = 2; // Cần ít nhất 2 stream để tính trung bình cho chính xác

    public function handle()
    {
        $this->info('Starting VPS capacity update...');
        $vpsServers = VpsServer::where('status', 'ACTIVE')->get();

        foreach ($vpsServers as $vps) {
            $this->info("Processing VPS: {$vps->name} (ID: {$vps->id})");

            $stats = $this->getVpsStatsFromRedis($vps->id);
            if (!$stats) {
                $this->warn("Could not get live stats for VPS #{$vps->id}. Skipping.");
                continue;
            }

            $predictedMax = $this->predictMaxStreams($stats);
            
            // Đảm bảo giá trị dự đoán không bao giờ thấp hơn số stream đang chạy
            $finalMax = max($stats['active_streams'], $predictedMax);

            if ($vps->max_concurrent_streams != $finalMax) {
                $vps->update(['max_concurrent_streams' => $finalMax]);
                $this->info(" -> Updated max_concurrent_streams from {$vps->max_concurrent_streams} to {$finalMax}");
            } else {
                $this->line(" -> Capacity is already up-to-date ({$finalMax}). No change needed.");
            }
        }

        $this->info('VPS capacity update finished.');
        return 0;
    }

    private function predictMaxStreams(array $stats): int
    {
        $currentStreams = $stats['active_streams'];
        $currentCpu = $stats['cpu_usage'];
        $currentRam = $stats['ram_usage'];

        // Trường hợp 1: Không có stream nào chạy -> Dùng giá trị mặc định an toàn
        if ($currentStreams < self::MIN_STREAMS_FOR_AVG) {
            return 10; // Giả định một VPS trống có thể chạy ít nhất 10 stream
        }

        // Trường hợp 2: Có stream đang chạy -> Tính toán
        // Tính toán tài nguyên trung bình mỗi stream đang tiêu thụ
        $avgCpuPerStream = $currentCpu / $currentStreams;
        $avgRamPerStream = ($currentRam - self::BASE_RAM_USAGE) / $currentStreams;
        
        if ($avgCpuPerStream <= 0 || $avgRamPerStream <= 0) {
            return $currentStreams + 1; // Nếu tài nguyên quá nhỏ, chỉ cho phép thêm 1 stream để kiểm tra
        }

        // Tính toán số stream có thể thêm dựa trên từng tài nguyên
        $potentialStreamsByCpu = (self::MAX_CPU_TARGET - $currentCpu) / $avgCpuPerStream;
        $potentialStreamsByRam = (self::MAX_RAM_TARGET - $currentRam) / $avgRamPerStream;

        // Chọn giá trị thấp nhất để đảm bảo an toàn
        $potentialNewStreams = floor(min($potentialStreamsByCpu, $potentialStreamsByRam));

        return $currentStreams + max(0, $potentialNewStreams);
    }

    private function getVpsStatsFromRedis(int $vpsId): ?array
    {
        try {
            $statsJson = Redis::hget('vps_live_stats', $vpsId);
            if ($statsJson) {
                return json_decode($statsJson, true);
            }
        } catch (\Exception $e) {
            Log::error("Failed to get live stats for VPS #{$vpsId} from Redis.", ['error' => $e->getMessage()]);
        }
        return null;
    }
} 