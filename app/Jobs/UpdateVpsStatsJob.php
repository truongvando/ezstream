<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class UpdateVpsStatsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Dữ liệu stats nhận được từ VPS.
     *
     * @var array
     */
    protected $data;

    /**
     * Create a new job instance.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $vpsId = $this->data['vps_id'] ?? null;

            if (!$vpsId) {
                Log::warning('Received VPS stats without vps_id.', $this->data);
                return;
            }

            // Thêm timestamp lúc nhận được để kiểm tra sau này
            $this->data['received_at'] = now()->timestamp;

            // Lưu dữ liệu vào Redis Hash
            // Key: 'vps_live_stats'
            // Field: vps_id
            // Value: JSON string chứa toàn bộ thông tin stats
            Redis::hset('vps_live_stats', $vpsId, json_encode($this->data));

        } catch (\Exception $e) {
            Log::error('Failed to process VPS stats job.', [
                'error' => $e->getMessage(),
                'data' => $this->data
            ]);
        }
    }
}
